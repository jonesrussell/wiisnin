<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Review\ReviewService;
use App\Entity\Review;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ratings & reviews: partners accept reviews (average updates), sample listings
 * reject them, and hidden reviews drop out of the average.
 */
final class ReviewServiceTest extends TestCase
{
    private InMemoryEntityRepository $reviews;
    private InMemoryEntityRepository $vendors;
    private int $partnerId;
    private int $sampleId;

    protected function setUp(): void
    {
        $this->reviews = new InMemoryEntityRepository();
        $this->vendors = new InMemoryEntityRepository();

        $partner = new Vendor(['name' => 'Partner Kitchen', 'slug' => 'partner-kitchen', 'is_partner' => 1]);
        $sample = new Vendor(['name' => 'Tony V\'s Pizza', 'slug' => 'tony-vs-pizza', 'is_partner' => 0]);
        $this->vendors->save($partner);
        $this->vendors->save($sample);
        $this->partnerId = (int) $partner->id();
        $this->sampleId = (int) $sample->id();
    }

    private function service(): ReviewService
    {
        return new ReviewService($this->reviews, $this->vendors, static fn (): int => 1_700_000_000);
    }

    #[Test]
    public function a_review_on_a_partner_updates_the_average_and_shows(): void
    {
        $svc = $this->service();
        $svc->create($this->partnerId, 0, 'June', 5, 'Best scone on the North Shore');
        $svc->create($this->partnerId, 0, 'Pierce', 3, 'Good');

        $summary = $svc->summary($this->partnerId);
        $this->assertSame(2, $summary['count']);
        $this->assertSame(4.0, $summary['average']);

        $list = $svc->listFor($this->partnerId);
        $this->assertCount(2, $list);
        $this->assertSame('June', $list[0]['author_name']);
    }

    #[Test]
    public function a_sample_listing_rejects_reviews(): void
    {
        $this->expectException(\DomainException::class);
        $this->service()->create($this->sampleId, 0, 'June', 5, 'nope');
    }

    #[Test]
    public function hiding_a_review_drops_it_from_the_average(): void
    {
        $svc = $this->service();
        $five = $svc->create($this->partnerId, 0, 'June', 5, 'Great');
        $svc->create($this->partnerId, 0, 'Pierce', 1, 'Bad');
        $this->assertSame(3.0, $svc->summary($this->partnerId)['average']);

        $this->assertTrue($svc->hide((int) $five->id()));
        $summary = $svc->summary($this->partnerId);
        $this->assertSame(1, $summary['count']);
        $this->assertSame(1.0, $summary['average']);
    }

    #[Test]
    public function rating_is_clamped_to_1_5(): void
    {
        $r = $this->service()->create($this->partnerId, 0, 'June', 9, '');
        $this->assertInstanceOf(Review::class, $r);
        $this->assertSame(5, $r->getRating());
    }
}
