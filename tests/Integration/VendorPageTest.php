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
 * The vendor page enforces the honesty contract: only the live partner gets a
 * menu + reviews; directory listings are info pages (no menu/reviews). And the
 * "open" status is tri-state — computed from hours, partner flag, or null
 * (unknown) so "Open now" is never faked.
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

        $meedjims = new Vendor(['name' => 'Meedjims Foodland', 'slug' => 'meedjims-foodland', 'is_partner' => 1, 'is_open' => 1]);
        $wing = new Vendor(['name' => 'Wing House', 'slug' => 'wing-house', 'is_partner' => 0]);
        $tim = new Vendor(['name' => 'Tim Hortons', 'slug' => 'tim-hortons-espanola', 'is_partner' => 0, 'hours_json' => self::TIM_HOURS]);
        $vendors->save($meedjims);
        $vendors->save($wing);
        $vendors->save($tim);

        $menu->save(new MenuItem(['vendor_id' => (int) $meedjims->id(), 'name' => 'Scone', 'price_cents' => 400, 'available' => 1]));
        $menu->save(new MenuItem(['vendor_id' => (int) $wing->id(), 'name' => 'Should never show', 'price_cents' => 999, 'available' => 1]));
        $reviews->save(new Review(['vendor_id' => (int) $meedjims->id(), 'author_name' => 'June', 'rating' => 5, 'body' => 'Great', 'status' => 'visible', 'created_at' => 1]));

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
    public function the_partner_page_shows_the_menu_and_reviews(): void
    {
        $props = $this->props('meedjims-foodland');
        $this->assertNotEmpty($props['menu']);
        $this->assertCount(1, $props['reviews']);
        $this->assertTrue($props['pricingDraft']);
        $this->assertTrue($props['vendor']['is_partner']);
        $this->assertTrue($props['vendor']['open'], 'partner with no hours falls back to its open flag');
    }

    #[Test]
    public function a_directory_listing_has_no_menu_or_reviews(): void
    {
        $props = $this->props('wing-house');
        $this->assertSame([], $props['menu'], 'non-partner listings never show a menu');
        $this->assertSame([], $props['reviews'], 'non-partner listings never show reviews');
        $this->assertNotNull($props['vendor']);
        $this->assertFalse($props['vendor']['is_partner']);
        $this->assertNull($props['vendor']['open'], 'unknown hours -> null (never fake Open now)');
    }

    #[Test]
    public function a_listing_with_hours_computes_open_closed(): void
    {
        // Tim Hortons is a non-partner WITH hours: hours take precedence over the
        // null default, so it shows a real open/closed state.
        $props = $this->props('tim-hortons-espanola');
        $this->assertSame([], $props['menu']);
        $this->assertTrue($props['vendor']['open'], 'open at Wed 10:00 within 05:30–23:00');
    }
}
