<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\SeoMeta;
use App\Path\VendorAliasResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Server-rendered SEO/OpenGraph meta (so social scrapers, which never run Vue,
 * get rich cards) and the vendor-alias path parsing.
 */
final class SeoMetaTest extends TestCase
{
    #[Test]
    public function vendor_meta_includes_opengraph_and_twitter_tags(): void
    {
        $html = SeoMeta::forVendor(
            ['name' => 'Meedjims Foodland', 'community' => 'Sagamok', 'cuisine' => 'Native cuisine & grill', 'slug' => 'meedjims-foodland'],
            'https://wiisnin.ca',
        );

        $this->assertStringContainsString('<meta property="og:title" content="Order from Meedjims Foodland', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://wiisnin.ca/vendor/meedjims-foodland">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('Sagamok', $html);
    }

    #[Test]
    public function home_meta_includes_opengraph_tags(): void
    {
        $html = SeoMeta::forHome('https://wiisnin.ca');
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://wiisnin.ca/">', $html);
    }

    #[Test]
    public function alias_system_path_parses_to_vendor_slug(): void
    {
        $this->assertSame('meedjims-foodland', VendorAliasResolver::slugFromSystemPath('/vendor/meedjims-foodland'));
        $this->assertNull(VendorAliasResolver::slugFromSystemPath('/node/42'));
        $this->assertNull(VendorAliasResolver::slugFromSystemPath('/vendor/bad/extra'));
    }
}
