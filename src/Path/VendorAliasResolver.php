<?php

declare(strict_types=1);

namespace App\Path;

use Waaseyaa\Path\PathAliasResolver;

/**
 * Production AliasLookup backed by the path package's PathAliasResolver, which
 * reads `path_alias` entities from storage. Vendor aliases are stored as
 * alias "/{slug-or-short}" → systemPath "/vendor/{slug}" (seeded in WiisninSeeder).
 */
final class VendorAliasResolver implements AliasLookupInterface
{
    public function __construct(
        private readonly PathAliasResolver $resolver,
    ) {}

    public function vendorSlug(string $aliasPath): ?string
    {
        $resolved = $this->resolver->resolve($aliasPath);
        if ($resolved === null) {
            return null;
        }

        return self::slugFromSystemPath($resolved->systemPath);
    }

    /** Pure: extract the vendor slug from a "/vendor/{slug}" system path. */
    public static function slugFromSystemPath(string $systemPath): ?string
    {
        return preg_match('#^/vendor/([a-z0-9-]+)$#', $systemPath, $m) === 1 ? $m[1] : null;
    }
}
