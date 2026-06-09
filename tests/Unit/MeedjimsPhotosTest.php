<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Catalog\Catalog;
use App\Entity\MenuItem;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The real Meedjims photos exist as optimized JPEGs and are referenced by the
 * Meedjims read model (hero + the corn-soup/cheese-fries menu items + the
 * 1200x630 share crop). Sample listings keep placeholders (no image). HTTP 200
 * is additionally verified live over public HTTPS during deploy.
 */
final class MeedjimsPhotosTest extends TestCase
{
    private function publicDir(): string
    {
        return dirname(__DIR__, 2) . '/public';
    }

    #[Test]
    public function the_photo_files_exist_as_jpegs(): void
    {
        $expect = [
            'img/meedjims/building.jpg' => null,
            'img/meedjims/corn-soup.jpg' => null,
            'img/meedjims/cheese-fries.jpg' => null,
            'img/meedjims/og-meedjims.jpg' => [1200, 630], // share crop is exactly 1.91:1
        ];

        foreach ($expect as $rel => $dims) {
            $path = $this->publicDir() . '/' . $rel;
            $this->assertFileExists($path, "$rel should be deployed");
            $info = getimagesize($path);
            $this->assertNotFalse($info, "$rel should be a readable image");
            $this->assertSame(IMAGETYPE_JPEG, $info[2], "$rel should be a JPEG");
            if ($dims !== null) {
                $this->assertSame($dims[0], $info[0], "$rel width");
                $this->assertSame($dims[1], $info[1], "$rel height");
            }
        }
    }

    private function catalog(): Catalog
    {
        $vendors = new InMemoryEntityRepository();
        $menuItems = new InMemoryEntityRepository();
        $terms = new InMemoryEntityRepository();

        $meedjims = new Vendor(['name' => 'Meedjims Foodland', 'slug' => 'meedjims-foodland', 'is_partner' => 1]);
        $sample = new Vendor(['name' => "Tony V's Pizza", 'slug' => 'tony-vs-pizza', 'is_partner' => 0]);
        $vendors->save($meedjims);
        $vendors->save($sample);
        $this->meedjimsId = (int) $meedjims->id();

        $menuItems->save(new MenuItem(['vendor_id' => $this->meedjimsId, 'name' => 'Corn soup', 'price_cents' => 800, 'available' => 1]));
        $menuItems->save(new MenuItem(['vendor_id' => $this->meedjimsId, 'name' => 'Cheese fries', 'price_cents' => 900, 'available' => 1]));
        $menuItems->save(new MenuItem(['vendor_id' => $this->meedjimsId, 'name' => 'Scone', 'price_cents' => 400, 'available' => 1]));

        $this->sampleVendor = $sample;

        return new Catalog($vendors, $menuItems, $terms);
    }

    private int $meedjimsId = 0;
    private Vendor $sampleVendor;

    #[Test]
    public function the_meedjims_card_carries_the_building_photo_and_a_sample_does_not(): void
    {
        $catalog = $this->catalog();

        $meedjims = $catalog->vendorBySlug('meedjims-foodland');
        $this->assertNotNull($meedjims);
        $this->assertSame('/img/meedjims/building.jpg', $catalog->vendorCard($meedjims)['image']);

        $this->assertNull($catalog->vendorCard($this->sampleVendor)['image']);
    }

    #[Test]
    public function the_named_menu_items_carry_their_photos_and_others_dont(): void
    {
        $catalog = $this->catalog();
        $byName = [];
        foreach ($catalog->menuForVendor($this->meedjimsId) as $group) {
            foreach ($group['items'] as $item) {
                $byName[$item['name']] = $item['image'];
            }
        }

        $this->assertSame('/img/meedjims/corn-soup.jpg', $byName['Corn soup'] ?? 'missing');
        $this->assertSame('/img/meedjims/cheese-fries.jpg', $byName['Cheese fries'] ?? 'missing');
        $this->assertArrayHasKey('Scone', $byName);
        $this->assertNull($byName['Scone'], 'unphotographed items keep the placeholder');
    }
}
