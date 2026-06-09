<?php

declare(strict_types=1);

namespace App\Http;

use Waaseyaa\Seo\MetaTagBuilder;

/**
 * Builds the server-rendered <head> SEO snippet (title + description + canonical
 * via the seo package's MetaTagBuilder, plus OpenGraph + Twitter card tags).
 *
 * These MUST be in the raw served HTML: social scrapers (Facebook, etc.) never
 * run the Vue <Head>, so the meta has to come from the server. Injected by
 * SeoMetaMiddleware.
 */
final class SeoMeta
{
    public static function forHome(string $baseUrl): string
    {
        return self::render(
            'Wiisnin — order food from North Shore kitchens',
            'Order pickup or delivery from local kitchens across Sagamok, Massey, Espanola and Spanish. Meedjims Foodland is live now.',
            rtrim($baseUrl, '/') . '/',
            $baseUrl,
        );
    }

    /**
     * @param array<string, mixed> $card a Catalog vendor card
     */
    public static function forVendor(array $card, string $baseUrl): string
    {
        $name = (string) ($card['name'] ?? 'Vendor');
        $community = (string) ($card['community'] ?? '');
        $cuisine = (string) ($card['cuisine'] ?? '');

        $title = trim("Order from {$name} — {$community}") . ' · Wiisnin';
        $desc = trim(($cuisine !== '' ? $cuisine . '. ' : '') . "Order pickup or delivery from {$name}"
            . ($community !== '' ? " in {$community}" : '') . ' on Wiisnin.');
        $url = rtrim($baseUrl, '/') . '/vendor/' . (string) ($card['slug'] ?? '');

        return self::render($title, $desc, $url, $baseUrl);
    }

    private static function render(string $title, string $description, string $url, string $baseUrl): string
    {
        $image = rtrim($baseUrl, '/') . '/img/og-default.png';
        $head = new MetaTagBuilder()->buildHeadSnippet($title, $description, $url);

        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $og = implode("\n", [
            '<meta property="og:type" content="website">',
            '<meta property="og:site_name" content="Wiisnin">',
            '<meta property="og:title" content="' . $e($title) . '">',
            '<meta property="og:description" content="' . $e($description) . '">',
            '<meta property="og:url" content="' . $e($url) . '">',
            '<meta property="og:image" content="' . $e($image) . '">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="twitter:title" content="' . $e($title) . '">',
            '<meta name="twitter:description" content="' . $e($description) . '">',
            '<meta name="twitter:image" content="' . $e($image) . '">',
        ]);

        return $head . "\n" . $og . "\n";
    }
}
