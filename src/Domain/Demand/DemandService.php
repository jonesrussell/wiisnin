<?php

declare(strict_types=1);

namespace App\Domain\Demand;

use App\Entity\DemandVote;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * "I'd order here" demand signals for non-partner listings. One vote per device
 * per vendor (best-effort server guard; the client also keeps a localStorage
 * flag). Counts feed the app:demand outreach list.
 */
final class DemandService
{
    /** @var \Closure(): int */
    private \Closure $clock;

    public function __construct(
        private readonly EntityRepositoryInterface $votes,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Record a vote (idempotent per device) and return the vendor's new count.
     */
    public function vote(string $vendorSlug, string $deviceId): int
    {
        $deviceId = trim($deviceId);
        if (!$this->hasVoted($vendorSlug, $deviceId)) {
            $this->votes->save(new DemandVote([
                'vendor_slug' => $vendorSlug,
                'device_id' => $deviceId,
                'created_at' => ($this->clock)(),
            ]));
        }

        return $this->countFor($vendorSlug);
    }

    public function hasVoted(string $vendorSlug, string $deviceId): bool
    {
        if ($deviceId === '') {
            return false; // can't dedupe an anonymous device; count it
        }

        return $this->votes->findBy(['vendor_slug' => $vendorSlug, 'device_id' => $deviceId], null, 1) !== [];
    }

    public function countFor(string $vendorSlug): int
    {
        return $this->votes->count(['vendor_slug' => $vendorSlug]);
    }

    /**
     * Vote totals per vendor slug (for the app:demand ranking).
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $tally = [];
        foreach ($this->votes->findBy([]) as $vote) {
            if (!$vote instanceof DemandVote) {
                continue;
            }
            $slug = $vote->getVendorSlug();
            $tally[$slug] = ($tally[$slug] ?? 0) + 1;
        }

        return $tally;
    }
}
