<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\Communities;
use App\Support\VendorData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The verified seed dataset: only confirmed listings, the three flagged ones
 * excluded, exactly one ordering partner, and every vendor in a known town.
 */
final class VerifiedSeedDataTest extends TestCase
{
    /** @return list<array<string,mixed>> */
    private function vendors(): array
    {
        return VendorData::vendors();
    }

    #[Test]
    public function the_eight_served_towns_are_canonical(): void
    {
        $this->assertSame(
            ['Sagamok', 'Massey', 'Walford', 'Spanish', 'Webbwood', 'Espanola', 'McKerrow', 'Nairn Centre'],
            Communities::NAMES,
        );
        foreach (Communities::NAMES as $town) {
            $this->assertArrayHasKey($town, Communities::CENTROIDS, "$town needs a centroid");
        }
    }

    #[Test]
    public function no_vendor_is_an_ordering_partner_right_now(): void
    {
        foreach ($this->vendors() as $v) {
            $this->assertFalse($v['partner'], "{$v['slug']} must not be a live ordering partner");
        }
    }

    #[Test]
    public function meedjims_is_opening_soon_and_keeps_its_dormant_menu(): void
    {
        $byslug = [];
        foreach ($this->vendors() as $v) {
            $byslug[$v['slug']] = $v;
        }

        $meedjims = $byslug['meedjims-foodland'];
        $this->assertTrue($meedjims['opening_soon'] ?? false, 'Meedjims kitchen is opening soon');
        $this->assertFalse($meedjims['partner']);
        // Menu stays as dormant scaffolding so ordering re-enables in one step.
        $this->assertNotEmpty($meedjims['menu'], 'Meedjims keeps its (dormant) menu scaffolding');

        // It is the only one carrying a menu; only it is "opening soon".
        foreach ($this->vendors() as $v) {
            if ($v['slug'] !== 'meedjims-foodland') {
                $this->assertSame([], $v['menu'], "{$v['slug']} must have no menu (info listing)");
                $this->assertFalse($v['opening_soon'] ?? false, "{$v['slug']} reads 'Ordering coming soon', not 'Opening soon'");
            }
        }
    }

    #[Test]
    public function the_flagged_listings_are_excluded(): void
    {
        $slugs = array_column($this->vendors(), 'slug');
        foreach (['back-home-bistro', 'tony-vs-pizza', 'deluxe-drive-in'] as $dropped) {
            $this->assertNotContains($dropped, $slugs, "$dropped should be excluded (unconfirmed/closed)");
        }
        // Jones General Store is a shop, not an eatery — excluded.
        $names = array_column($this->vendors(), 'name');
        $this->assertNotContains('Jones General Store', $names);
    }

    #[Test]
    public function wing_house_is_included_and_township_verified(): void
    {
        $slugs = array_column($this->vendors(), 'slug');
        $this->assertContains('wing-house', $slugs);
    }

    #[Test]
    public function every_vendor_sits_in_a_known_town_and_has_a_unique_slug(): void
    {
        $slugs = array_column($this->vendors(), 'slug');
        $this->assertSame(count($slugs), count(array_unique($slugs)), 'slugs must be unique');

        foreach ($this->vendors() as $v) {
            $this->assertContains($v['community'], Communities::NAMES, "{$v['slug']} town must be canonical");
            $this->assertNotSame('', $v['name']);
            $this->assertNotSame('', $v['cuisine']);
        }
    }

    #[Test]
    public function only_listings_with_real_hours_carry_structured_hours(): void
    {
        $withHours = array_values(array_filter($this->vendors(), static fn (array $v): bool => $v['hours_json'] !== ''));
        $slugs = array_column($withHours, 'slug');
        sort($slugs);
        // Exactly the two the verified file gives hours for.
        $this->assertSame(['roger-rabbits', 'tim-hortons-espanola'], $slugs);
    }

    #[Test]
    public function the_count_is_one_partner_plus_twenty_listings(): void
    {
        $this->assertCount(21, $this->vendors());
    }
}
