<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\VendorController;
use App\Domain\Catalog\Catalog;
use App\Domain\Demand\DemandService;
use App\Domain\Review\ReviewService;
use App\Entity\MenuItem;
use App\Entity\Review;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Inertia\Inertia;

/**
 * The vendor page enforces the honesty contract: only an ordering partner gets a
 * menu + reviews. Today NO real vendor is a partner — Meedjims is an "opening
 * soon" info listing (no menu/reviews/order). The ordering code path stays in
 * place (proven by a synthetic partner) so it can be re-enabled in one step.
 * "open" is tri-state — computed from hours, partner flag, or null (never faked).
 */
final class VendorPageTest extends TestCase
{
    private const TIM_HOURS = '{"mon":[["05:30","23:00"]],"tue":[["05:30","23:00"]],"wed":[["05:30","23:00"]],"thu":[["05:30","23:00"]],"fri":[["05:30","23:00"]],"sat":[["05:30","23:00"]],"sun":[["05:30","23:00"]]}';

    private VendorController $controller;

    protected function setUp(): void
    {
        Inertia::reset();

        $vendors = new InMemoryEntityRepository();
        $menu = new InMemoryEntityRepository();
        $terms = new InMemoryEntityRepository();
        $reviews = new InMemoryEntityRepository();

        // Dormant ordering code path: a synthetic partner (no real vendor is a
        // partner now). Proves menu/reviews/ordering still work when re-enabled.
        $partner = new Vendor(['name' => 'Future Partner Kitchen', 'slug' => 'partner-kitchen', 'is_partner' => 1, 'is_open' => 1]);
        // Meedjims: demoted to an "opening soon" directory listing.
        $meedjims = new Vendor(['name' => 'Meedjims Foodland', 'slug' => 'meedjims-foodland', 'is_partner' => 0, 'opening_soon' => 1]);
        // A plain open business with real hours.
        $tim = new Vendor(['name' => 'Tim Hortons', 'slug' => 'tim-hortons-espanola', 'is_partner' => 0, 'hours_json' => self::TIM_HOURS]);
        $vendors->save($partner);
        $vendors->save($meedjims);
        $vendors->save($tim);

        $menu->save(new MenuItem(['vendor_id' => (int) $partner->id(), 'name' => 'Burger', 'price_cents' => 800, 'available' => 1]));
        // Meedjims keeps a (dormant) menu item that must NOT surface on its page.
        $menu->save(new MenuItem(['vendor_id' => (int) $meedjims->id(), 'name' => 'Scone', 'price_cents' => 400, 'available' => 1]));
        $reviews->save(new Review(['vendor_id' => (int) $partner->id(), 'author_name' => 'June', 'rating' => 5, 'body' => 'Great', 'status' => 'visible', 'created_at' => 1]));

        // Fixed clock: Wednesday 10:00 America/Toronto (inside Tim's 05:30–23:00).
        $clock = static fn (): int => (new \DateTimeImmutable('2026-06-10 10:00', new \DateTimeZone('America/Toronto')))->getTimestamp();
        $catalog = new Catalog($vendors, $menu, $terms, new ReviewService($reviews, $vendors), new DemandService(new InMemoryEntityRepository()), $clock);
        $this->controller = new VendorController($catalog);
    }

    /** @return array<string,mixed> */
    private function props(string $slug): array
    {
        return $this->controller->show($slug)->toPageObject()['props'];
    }

    #[Test]
    public function a_partner_page_still_shows_menu_and_reviews_when_one_exists(): void
    {
        $props = $this->props('partner-kitchen');
        $this->assertNotEmpty($props['menu']);
        $this->assertCount(1, $props['reviews']);
        $this->assertTrue($props['pricingDraft']);
        $this->assertTrue($props['vendor']['is_partner']);
        $this->assertTrue($props['vendor']['open'], 'partner with no hours falls back to its open flag');
    }

    #[Test]
    public function meedjims_is_an_opening_soon_info_page_with_no_menu_or_reviews(): void
    {
        $props = $this->props('meedjims-foodland');
        $this->assertFalse($props['vendor']['is_partner'], 'Meedjims is not an ordering partner');
        $this->assertTrue($props['vendor']['opening_soon'], 'Meedjims reads "Opening soon"');
        $this->assertSame([], $props['menu'], 'the dormant Meedjims menu must NOT show');
        $this->assertSame([], $props['reviews'], 'no reviews shown for a non-partner');
        $this->assertNull($props['vendor']['open'], 'never compute/fake open/closed for Meedjims');
        $this->assertNotNull($props['vendor']['image'], 'Meedjims keeps its building photo');
    }

    #[Test]
    public function a_listing_with_hours_computes_open_closed(): void
    {
        $props = $this->props('tim-hortons-espanola');
        $this->assertSame([], $props['menu']);
        $this->assertFalse($props['vendor']['opening_soon']);
        $this->assertTrue($props['vendor']['open'], 'open at Wed 10:00 within 05:30–23:00');
    }
}
