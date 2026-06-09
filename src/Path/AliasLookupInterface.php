<?php

declare(strict_types=1);

namespace App\Path;

/**
 * Resolves a public alias path (e.g. "/meedjims") to a vendor slug, if any.
 * A seam over the path package's PathAliasResolver so routes + SEO can be tested
 * without a booted kernel.
 */
interface AliasLookupInterface
{
    public function vendorSlug(string $aliasPath): ?string;
}
