<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Catalog\Catalog;
use App\Path\AliasLookupInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Foundation\Middleware\HttpHandlerInterface;
use Waaseyaa\Foundation\Middleware\HttpMiddlewareInterface;

/**
 * Injects server-rendered SEO/OpenGraph meta into the served HTML <head> for
 * public GET pages, so pasted links unfurl in Facebook/social (which never run
 * the Vue <Head>). Order-independent: it post-processes the final HTML response.
 */
final class SeoMetaMiddleware implements HttpMiddlewareInterface
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly AliasLookupInterface $aliases,
        private readonly string $baseUrl = '',
    ) {}

    public function process(Request $request, HttpHandlerInterface $next): Response
    {
        $response = $next->handle($request);

        if ($request->getMethod() !== 'GET') {
            return $response;
        }
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }
        $html = (string) $response->getContent();
        if (!str_contains($html, '</head>')) {
            return $response;
        }

        $base = $this->baseUrl !== '' ? $this->baseUrl : $request->getSchemeAndHttpHost();
        $snippet = $this->snippetFor($request->getPathInfo(), $base);
        if ($snippet === '') {
            return $response;
        }

        $response->setContent(str_replace('</head>', $snippet . '</head>', $html));
        return $response;
    }

    private function snippetFor(string $path, string $base): string
    {
        if ($path === '/') {
            return SeoMeta::forHome($base);
        }

        $slug = null;
        if (preg_match('#^/vendor/([a-z0-9-]+)$#', $path, $m) === 1) {
            $slug = $m[1];
        } elseif (preg_match('#^/([a-z0-9-]+)$#', $path, $m) === 1) {
            // Single-segment public alias, e.g. /meedjims.
            $slug = $this->aliases->vendorSlug($path) ?? $m[1];
        }

        if ($slug !== null) {
            $vendor = $this->catalog->vendorBySlug($slug);
            if ($vendor !== null) {
                return SeoMeta::forVendor($this->catalog->vendorCard($vendor), $base);
            }
        }

        return '';
    }
}
