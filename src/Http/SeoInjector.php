<?php

declare(strict_types=1);

namespace App\Http;

use App\Path\VendorAliasResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects server-rendered SEO/OpenGraph meta into the HTML <head> from the front
 * controller (public/index.php), AFTER the kernel produced the response.
 *
 * Why here and not middleware: the framework's provider-middleware pipeline runs
 * BEFORE controller dispatch (its inner handler returns an empty 200), so it
 * never sees the rendered HTML; and the Inertia full-page renderer can't be
 * overridden by an app provider (package providers resolve first). The front
 * controller is the one post-dispatch hook the app owns. It reads the vendor
 * straight from SQLite (read-only) to stay self-contained. See WAASEYAA-FRICTION.md.
 */
final class SeoInjector
{
    public static function inject(Response $response, string $projectRoot): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }
        if (!str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')) {
            return;
        }
        $html = (string) $response->getContent();
        if (!str_contains($html, '</head>')) {
            return;
        }

        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $base = (string) (getenv('APP_URL') ?: self::host());

        $snippet = self::snippet($path, $base, $projectRoot);
        if ($snippet === '') {
            return;
        }

        $response->setContent(str_replace('</head>', $snippet . '</head>', $html));
    }

    private static function snippet(string $path, string $base, string $projectRoot): string
    {
        if ($path === '/') {
            return SeoMeta::forHome($base);
        }

        $slug = null;
        if (preg_match('#^/vendor/([a-z0-9-]+)$#', $path, $m) === 1) {
            $slug = $m[1];
        } elseif (preg_match('#^/([a-z0-9][a-z0-9-]*)$#', $path, $m) === 1) {
            $slug = self::aliasSlug($path, $projectRoot) ?? $m[1];
        }
        if ($slug === null) {
            return '';
        }

        $card = self::vendorCard($slug, $projectRoot);
        return $card !== null ? SeoMeta::forVendor($card, $base) : '';
    }

    private static function pdo(string $projectRoot): ?\PDO
    {
        $db = getenv('WAASEYAA_DB') ?: ($projectRoot . '/storage/waaseyaa.sqlite');
        if (!str_starts_with($db, '/') && preg_match('#^[A-Za-z]:#', $db) !== 1) {
            $db = $projectRoot . '/' . ltrim($db, './');
        }
        if (!is_file($db)) {
            return null;
        }
        try {
            return new \PDO('sqlite:' . $db);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function aliasSlug(string $aliasPath, string $projectRoot): ?string
    {
        $pdo = self::pdo($projectRoot);
        if ($pdo === null) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT _data FROM path_alias WHERE alias = ? LIMIT 1');
        $stmt->execute([$aliasPath]);
        $data = $stmt->fetchColumn();
        if (!is_string($data)) {
            return null;
        }
        $decoded = json_decode($data, true);
        return is_array($decoded) ? VendorAliasResolver::slugFromSystemPath((string) ($decoded['path'] ?? '')) : null;
    }

    /**
     * @return array{name: string, community: string, cuisine: string, slug: string}|null
     */
    private static function vendorCard(string $slug, string $projectRoot): ?array
    {
        $pdo = self::pdo($projectRoot);
        if ($pdo === null) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT name, _data FROM vendor WHERE json_extract(_data, '$.slug') = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $data = json_decode((string) ($row['_data'] ?? '{}'), true);
        $data = is_array($data) ? $data : [];

        $community = '';
        if (!empty($data['community_tid'])) {
            $t = $pdo->prepare('SELECT name FROM taxonomy_term WHERE tid = ? LIMIT 1');
            $t->execute([(int) $data['community_tid']]);
            $community = (string) ($t->fetchColumn() ?: '');
        }

        return [
            'name' => (string) ($row['name'] ?? ''),
            'community' => $community,
            'cuisine' => (string) ($data['cuisine'] ?? ''),
            'slug' => $slug,
        ];
    }

    private static function host(): string
    {
        $scheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host;
    }
}
