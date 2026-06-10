<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Analytics\AnalyticsReport;
use App\Entity\Event;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The insights aggregation: pageview/unique counts and the top-N rollups that
 * drive Russell's outreach list.
 */
final class AnalyticsReportTest extends TestCase
{
    private InMemoryEntityRepository $events;

    protected function setUp(): void
    {
        $this->events = new InMemoryEntityRepository();
    }

    private function add(array $fields): void
    {
        $this->events->save(new Event($fields + ['created_at' => 1_700_000_000]));
    }

    #[Test]
    public function it_counts_pageviews_and_unique_views(): void
    {
        $this->add(['event_type' => 'pageview', 'path' => '/', 'view_id' => 'a']);
        $this->add(['event_type' => 'pageview', 'path' => '/c/massey', 'view_id' => 'a']); // same view
        $this->add(['event_type' => 'pageview', 'path' => '/', 'view_id' => 'b']);
        $this->add(['event_type' => 'engagement', 'view_id' => 'a', 'scroll_pct' => 50, 'dwell_ms' => 1000]);

        $report = new AnalyticsReport($this->events);
        $this->assertSame(3, $report->pageviews(), 'engagement is not a pageview');
        $this->assertSame(2, $report->uniqueViews(), 'distinct view ids: a, b');
    }

    #[Test]
    public function it_ranks_vendors_calls_directions_searches(): void
    {
        // wing-house viewed twice, dixie once.
        $this->add(['event_type' => 'vendor_view', 'view_id' => 'a', 'slug' => 'wing-house']);
        $this->add(['event_type' => 'vendor_view', 'view_id' => 'b', 'slug' => 'wing-house']);
        $this->add(['event_type' => 'vendor_view', 'view_id' => 'c', 'slug' => 'dixie-lee-chicken']);
        // calls: dixie twice, wing once.
        $this->add(['event_type' => 'call', 'view_id' => 'a', 'slug' => 'dixie-lee-chicken']);
        $this->add(['event_type' => 'call', 'view_id' => 'b', 'slug' => 'dixie-lee-chicken']);
        $this->add(['event_type' => 'call', 'view_id' => 'c', 'slug' => 'wing-house']);
        // directions: wing once.
        $this->add(['event_type' => 'directions', 'view_id' => 'a', 'slug' => 'wing-house']);
        // searches: "pizza" x2 (case-insensitive), "tacos" x1.
        $this->add(['event_type' => 'search', 'view_id' => 'a', 'query' => 'pizza']);
        $this->add(['event_type' => 'search', 'view_id' => 'b', 'query' => 'Pizza']);
        $this->add(['event_type' => 'search', 'view_id' => 'c', 'query' => 'tacos']);

        $report = new AnalyticsReport($this->events);

        $this->assertSame(['wing-house' => 2, 'dixie-lee-chicken' => 1], $report->topVendorsViewed());
        $this->assertSame(['dixie-lee-chicken' => 2, 'wing-house' => 1], $report->mostCalled());
        $this->assertSame(['wing-house' => 1], $report->mostDirections());
        $this->assertSame(['pizza' => 2, 'tacos' => 1], $report->topSearchTerms());
    }

    #[Test]
    public function the_top_n_limit_is_respected(): void
    {
        foreach (['a', 'b', 'c', 'd'] as $i => $slug) {
            for ($n = 0; $n <= $i; $n++) {
                $this->add(['event_type' => 'vendor_view', 'view_id' => "$slug$n", 'slug' => $slug]);
            }
        }
        $report = new AnalyticsReport($this->events);
        $top2 = $report->topVendorsViewed(2);
        $this->assertCount(2, $top2);
        $this->assertSame(['d', 'c'], array_keys($top2), 'highest counts first');
    }
}
