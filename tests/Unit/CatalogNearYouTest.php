<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Catalog\Catalog;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Taxonomy\Term;

/**
 * The location-first "near you" read model: distance sort (geo), community
 * filter, and search-result restriction.
 */
final class CatalogNearYouTest extends TestCase
{
    private InMemoryEntityRepository $vendors;
    private InMemoryEntityRepository $menuItems;
    private InMemoryEntityRepository $terms;
    private int $sagamokTid;
    private int $masseyTid;
    private int $meedjimsId;

    protected function setUp(): void
    {
        $this->vendors = new InMemoryEntityRepository();
        $this->menuItems = new InMemoryEntityRepository();
        $this->terms = new InMemoryEntityRepository();

        $sagamok = new Term(['vid' => 'community', 'name' => 'Sagamok']);
        $massey = new Term(['vid' => 'community', 'name' => 'Massey']);
        $this->terms->save($sagamok);
        $this->terms->save($massey);
        $this->sagamokTid = (int) $sagamok->id();
        $this->masseyTid = (int) $massey->id();

        // Meedjims in Sagamok (≈46.131,-82.573); a sample in Massey (≈46.214,-82.083).
        $meedjims = new Vendor(['name' => 'Partner Kitchen', 'slug' => 'partner-kitchen', 'is_partner' => 1, 'is_open' => 1, 'community_tid' => $this->sagamokTid, 'latitude' => 46.131, 'longitude' => -82.573]);
        $massyV = new Vendor(['name' => 'Back Home Bistro', 'slug' => 'back-home-bistro', 'is_partner' => 0, 'is_open' => 1, 'community_tid' => $this->masseyTid, 'latitude' => 46.214, 'longitude' => -82.083]);
        $this->vendors->save($meedjims);
        $this->vendors->save($massyV);
        $this->meedjimsId = (int) $meedjims->id();
    }

    private function catalog(): Catalog
    {
        return new Catalog($this->vendors, $this->menuItems, $this->terms);
    }

    #[Test]
    public function vendors_are_sorted_nearest_first_with_a_distance_badge(): void
    {
        // A user standing in Sagamok: Meedjims must be first, with a small distance.
        $list = $this->catalog()->vendorsNear(46.131, -82.573, null, null);

        $this->assertSame('Partner Kitchen', $list[0]['name']);
        $this->assertNotNull($list[0]['distance_km']);
        $this->assertLessThan($list[1]['distance_km'], $list[0]['distance_km']);
        $this->assertTrue($list[0]['is_partner']);
        $this->assertFalse($list[1]['is_partner']);
    }

    #[Test]
    public function community_filter_restricts_the_list(): void
    {
        $list = $this->catalog()->vendorsNear(46.131, -82.573, 'Massey', null);
        $this->assertCount(1, $list);
        $this->assertSame('Back Home Bistro', $list[0]['community'] === 'Massey' ? $list[0]['name'] : null);
    }

    #[Test]
    public function search_result_ids_restrict_the_list(): void
    {
        $list = $this->catalog()->vendorsNear(46.131, -82.573, null, [$this->meedjimsId]);
        $this->assertCount(1, $list);
        $this->assertSame('Partner Kitchen', $list[0]['name']);
    }
}
