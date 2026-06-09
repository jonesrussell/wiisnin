<?php

declare(strict_types=1);

namespace App\Domain\Review;

use App\Entity\Review;
use App\Entity\Vendor;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Ratings & reviews. Only live partners accept reviews (honesty rule — sample
 * listings aren't real partners). `status` backs basic moderation (hide).
 */
final class ReviewService
{
    /** @var \Closure(): int */
    private \Closure $clock;

    public function __construct(
        private readonly EntityRepositoryInterface $reviews,
        private readonly EntityRepositoryInterface $vendors,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * @throws \DomainException if the vendor is unknown or a sample (non-partner)
     */
    public function create(int $vendorId, int $authorUid, string $authorName, int $rating, string $body): Review
    {
        $vendor = $this->vendors->find((string) $vendorId);
        if (!$vendor instanceof Vendor) {
            throw new \DomainException('Unknown vendor.');
        }
        if (!$vendor->isPartner()) {
            throw new \DomainException('This is a sample listing, not a live partner yet — reviews open when they join Wiisnin.');
        }

        $review = new Review([
            'vendor_id' => $vendorId,
            'author_uid' => $authorUid,
            'author_name' => trim($authorName) !== '' ? trim($authorName) : 'Guest',
            'rating' => max(1, min(5, $rating)),
            'body' => $body,
            'status' => 'visible',
            'created_at' => ($this->clock)(),
        ]);
        $this->reviews->save($review);

        return $review;
    }

    /**
     * @return array{average: float|null, count: int}
     */
    public function summary(int $vendorId): array
    {
        $visible = $this->visible($vendorId);
        $count = count($visible);
        if ($count === 0) {
            return ['average' => null, 'count' => 0];
        }
        $sum = array_sum(array_map(static fn (Review $r): int => $r->getRating(), $visible));

        return ['average' => round($sum / $count, 1), 'count' => $count];
    }

    /**
     * Visible reviews, newest first, as plain arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function listFor(int $vendorId): array
    {
        $rows = $this->visible($vendorId);
        usort($rows, static fn (Review $a, Review $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        return array_map(static fn (Review $r): array => [
            'id' => (int) $r->id(),
            'author_name' => $r->getAuthorName(),
            'rating' => $r->getRating(),
            'body' => $r->getBody(),
            'created_at' => $r->getCreatedAt(),
        ], $rows);
    }

    public function hide(int $reviewId): bool
    {
        $review = $this->reviews->find((string) $reviewId);
        if (!$review instanceof Review) {
            return false;
        }
        $review->set('status', 'hidden');
        $this->reviews->save($review);

        return true;
    }

    /**
     * @return list<Review>
     */
    private function visible(int $vendorId): array
    {
        return array_values(array_filter(
            $this->reviews->findBy(['vendor_id' => $vendorId, 'status' => 'visible']),
            static fn (object $r): bool => $r instanceof Review,
        ));
    }
}
