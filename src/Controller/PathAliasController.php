<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Catalog\Catalog;
use App\Path\AliasLookupInterface;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Inertia\InertiaResponse;

/**
 * Resolves a clean single-segment alias (e.g. /meedjims) to its vendor page via
 * the path package, and renders the Vendor component. 404 if no alias matches.
 */
final class PathAliasController
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly AliasLookupInterface $aliases,
    ) {}

    public function show(string $alias, string $locale = 'en'): Response|InertiaResponse
    {
        $slug = $this->aliases->vendorSlug('/' . $alias);
        if ($slug === null) {
            return new Response('Not found.', 404, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return new VendorController($this->catalog)->show($slug, $locale);
    }
}
