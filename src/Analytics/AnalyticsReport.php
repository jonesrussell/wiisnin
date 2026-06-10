<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Entity\Event;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Read-side aggregation of analytics Events for the app:insights CLI.
 * Loads events once; everything else is in-memory grouping. Fine at directory
 * scale; if the event table ever grows large, swap the full scan for windowed
 * SQL aggregates.
 */
final class AnalyticsReport
{
    /** @var list<Event> */
    private array $all;

    public function __construct(EntityRepositoryInterface $events)
    {
        $this->all = array_values(array_filter(
            $events->findBy([]),
            static fn (object $e): bool => $e instanceof Event,
        ));
    }

    public function pageviews(): int
    {
        return count($this->ofType('pageview'));
    }

    public function uniqueViews(): int
    {
        $ids = [];
        foreach ($this->ofType('pageview') as $e) {
            $vid = $e->getViewId();
            if ($vid !== '') {
                $ids[$vid] = true;
            }
        }

        return count($ids);
    }

    /** @return array<string,int> referrer host => count (top sources) */
    public function topReferrers(int $limit = 10): array
    {
        $tally = [];
        foreach ($this->ofType('pageview') as $e) {
            $host = $e->getReferrerHost();
            if ($host !== '') {
                $tally[$host] = ($tally[$host] ?? 0) + 1;
            }
        }

        return $this->topN($tally, $limit);
    }

    /** @return array<string,int> slug => count */
    public function topVendorsViewed(int $limit = 10): array
    {
        return $this->countSlugs('vendor_view', $limit);
    }

    /** @return array<string,int> slug => count */
    public function mostCalled(int $limit = 10): array
    {
        return $this->countSlugs('call', $limit);
    }

    /** @return array<string,int> slug => count */
    public function mostDirections(int $limit = 10): array
    {
        return $this->countSlugs('directions', $limit);
    }

    /** @return array<string,int> query => count */
    public function topSearchTerms(int $limit = 10): array
    {
        $tally = [];
        foreach ($this->ofType('search') as $e) {
            $q = strtolower(trim($e->getQuery()));
            if ($q !== '') {
                $tally[$q] = ($tally[$q] ?? 0) + 1;
            }
        }

        return $this->topN($tally, $limit);
    }

    /** @return array<string,int> slug => count */
    private function countSlugs(string $type, int $limit): array
    {
        $tally = [];
        foreach ($this->ofType($type) as $e) {
            $slug = $e->getSlug();
            if ($slug !== '') {
                $tally[$slug] = ($tally[$slug] ?? 0) + 1;
            }
        }

        return $this->topN($tally, $limit);
    }

    /** @return list<Event> */
    private function ofType(string $type): array
    {
        return array_values(array_filter($this->all, static fn (Event $e): bool => $e->getEventType() === $type));
    }

    /**
     * @param array<string,int> $tally
     * @return array<string,int>
     */
    private function topN(array $tally, int $limit): array
    {
        arsort($tally);

        return array_slice($tally, 0, $limit, true);
    }
}
