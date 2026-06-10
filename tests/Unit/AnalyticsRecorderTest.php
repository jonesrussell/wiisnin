<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Analytics\AnalyticsRecorder;
use App\Entity\Event;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The analytics recorder validates beacons, stores no PII, filters bots, and
 * rate-limits per visitor.
 */
final class AnalyticsRecorderTest extends TestCase
{
    private InMemoryEntityRepository $events;

    protected function setUp(): void
    {
        $this->events = new InMemoryEntityRepository();
    }

    private function recorder(int $max = 120): AnalyticsRecorder
    {
        return new AnalyticsRecorder($this->events, 'test-secret', static fn (): int => 1_700_000_000, $max, 60);
    }

    private function rows(): array
    {
        return $this->events->findBy([]);
    }

    #[Test]
    public function a_valid_pageview_is_stored_without_pii(): void
    {
        $ok = $this->recorder()->record(
            ['t' => 'pageview', 'p' => '/vendor/wing-house', 'r' => 'https://www.google.com/search', 'v' => 'view-1'],
            '203.0.113.7',
            'Mozilla/5.0 (iPhone)',
        );
        $this->assertTrue($ok);
        $this->assertCount(1, $this->rows());

        $e = $this->rows()[0];
        $this->assertInstanceOf(Event::class, $e);
        $this->assertSame('pageview', $e->getEventType());
        $this->assertSame('/vendor/wing-house', $e->getPath());
        $this->assertSame('www.google.com', $e->getReferrerHost(), 'only the referrer host is kept');
        $this->assertSame('mobile', $e->getDevice());
        $this->assertNotSame('', $e->getVisitorHash());
        // No raw IP in the (hashed) visitor id — it's a salted one-way hash.
        $this->assertStringNotContainsString('203.0.113.7', $e->getVisitorHash());
        $this->assertSame(64, strlen($e->getVisitorHash()), 'sha256 hex');
    }

    #[Test]
    public function the_wiisnin_event_types_are_accepted(): void
    {
        $r = $this->recorder();
        $this->assertTrue($r->record(['t' => 'vendor_view', 'v' => 'v', 'slug' => 'wing-house'], '1.1.1.1', 'UA'));
        $this->assertTrue($r->record(['t' => 'call', 'v' => 'v', 'slug' => 'wing-house'], '1.1.1.1', 'UA'));
        $this->assertTrue($r->record(['t' => 'directions', 'v' => 'v', 'slug' => 'wing-house'], '1.1.1.1', 'UA'));
        $this->assertTrue($r->record(['t' => 'demand', 'v' => 'v', 'slug' => 'wing-house'], '1.1.1.1', 'UA'));
        $this->assertTrue($r->record(['t' => 'search', 'v' => 'v', 'q' => 'pizza'], '1.1.1.1', 'UA'));
        $this->assertTrue($r->record(['t' => 'engagement', 'v' => 'v', 's' => 80, 'd' => 4200], '1.1.1.1', 'UA'));
        $this->assertCount(6, $this->rows());

        $search = array_values(array_filter($this->rows(), static fn (Event $e): bool => $e->getEventType() === 'search'))[0];
        $this->assertSame('pizza', $search->getQuery());
        $eng = array_values(array_filter($this->rows(), static fn (Event $e): bool => $e->getEventType() === 'engagement'))[0];
        $this->assertSame(80, $eng->getScrollPct());
        $this->assertSame(4200, $eng->getDwellMs());
    }

    #[Test]
    public function malformed_beacons_are_rejected(): void
    {
        $r = $this->recorder();
        $this->assertFalse($r->record(['t' => 'evil', 'v' => 'v'], '1.1.1.1', 'UA'), 'unknown type');
        $this->assertFalse($r->record(['t' => 'pageview', 'p' => '/'], '1.1.1.1', 'UA'), 'missing view id');
        $this->assertFalse($r->record(['t' => 'pageview', 'v' => 'v'], '1.1.1.1', 'UA'), 'missing path');
        $this->assertFalse($r->record(['t' => 'call', 'v' => 'v'], '1.1.1.1', 'UA'), 'missing slug');
        $this->assertFalse($r->record(['t' => 'call', 'v' => 'v', 'slug' => 'Bad Slug!'], '1.1.1.1', 'UA'), 'invalid slug');
        $this->assertFalse($r->record(['t' => 'search', 'v' => 'v'], '1.1.1.1', 'UA'), 'missing query');
        $this->assertCount(0, $this->rows());
    }

    #[Test]
    public function bots_are_filtered(): void
    {
        $ok = $this->recorder()->record(['t' => 'pageview', 'p' => '/', 'v' => 'v'], '1.1.1.1', 'Googlebot/2.1');
        $this->assertFalse($ok);
        $this->assertCount(0, $this->rows());
    }

    #[Test]
    public function it_rate_limits_per_visitor(): void
    {
        $r = $this->recorder(3);
        $beacon = ['t' => 'pageview', 'p' => '/', 'v' => 'v'];
        $this->assertTrue($r->record($beacon, '9.9.9.9', 'UA'));
        $this->assertTrue($r->record($beacon, '9.9.9.9', 'UA'));
        $this->assertTrue($r->record($beacon, '9.9.9.9', 'UA'));
        $this->assertFalse($r->record($beacon, '9.9.9.9', 'UA'), '4th in the window is dropped');
        $this->assertCount(3, $this->rows());

        // A different visitor is unaffected.
        $this->assertTrue($r->record($beacon, '8.8.8.8', 'Other UA'));
    }
}
