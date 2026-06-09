<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\SeoMeta;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The default OpenGraph share card exists and is a valid 1200×630 PNG, served
 * from the public root at /img/og-default.png. (HTTP 200 + image content-type is
 * additionally verified live over public HTTPS during deploy.)
 */
final class OgImageTest extends TestCase
{
    private function path(): string
    {
        return dirname(__DIR__, 2) . '/public/img/og-default.png';
    }

    #[Test]
    public function default_share_card_is_a_1200x630_png(): void
    {
        $path = $this->path();
        $this->assertFileExists($path);

        $info = getimagesize($path);
        $this->assertNotFalse($info);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
        $this->assertSame('image/png', $info['mime']);
        $this->assertSame(1200, $info[0]);
        $this->assertSame(630, $info[1]);
    }

    #[Test]
    public function vendor_meta_uses_a_custom_image_when_present_else_the_default(): void
    {
        $withImage = SeoMeta::forVendor(
            ['name' => 'Meedjims Foodland', 'community' => 'Sagamok', 'slug' => 'meedjims-foodland', 'image' => 'https://wiisnin.ca/files/meedjims.png'],
            'https://wiisnin.ca',
        );
        $this->assertStringContainsString('<meta property="og:image" content="https://wiisnin.ca/files/meedjims.png">', $withImage);

        $default = SeoMeta::forVendor(
            ['name' => 'Meedjims Foodland', 'community' => 'Sagamok', 'slug' => 'meedjims-foodland'],
            'https://wiisnin.ca',
        );
        $this->assertStringContainsString('<meta property="og:image" content="https://wiisnin.ca/img/og-default.png">', $default);
    }
}
