<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Demand\DemandService;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Demand votes: one per device per vendor, with running counts.
 */
final class DemandServiceTest extends TestCase
{
    private function service(): DemandService
    {
        return new DemandService(new InMemoryEntityRepository(), static fn (): int => 1_700_000_000);
    }

    #[Test]
    public function a_vote_increments_the_count(): void
    {
        $svc = $this->service();
        $this->assertSame(0, $svc->countFor('wing-house'));
        $this->assertSame(1, $svc->vote('wing-house', 'device-a'));
        $this->assertSame(2, $svc->vote('wing-house', 'device-b'));
        $this->assertSame(2, $svc->countFor('wing-house'));
    }

    #[Test]
    public function the_same_device_cannot_double_vote(): void
    {
        $svc = $this->service();
        $svc->vote('wing-house', 'device-a');
        $this->assertSame(1, $svc->vote('wing-house', 'device-a'), 'second vote from same device is a no-op');
        $this->assertSame(1, $svc->countFor('wing-house'));
        $this->assertTrue($svc->hasVoted('wing-house', 'device-a'));
        $this->assertFalse($svc->hasVoted('wing-house', 'device-z'));
    }

    #[Test]
    public function an_anonymous_device_cannot_be_deduped_so_each_vote_counts(): void
    {
        // Documented best-effort behavior: with no device id we can't dedupe, so
        // each anonymous vote counts. (The client always sends one; this pins the
        // fallback so it can't silently change.)
        $svc = $this->service();
        $this->assertSame(1, $svc->vote('wing-house', ''));
        $this->assertSame(2, $svc->vote('wing-house', ''));
        $this->assertFalse($svc->hasVoted('wing-house', ''));
    }

    #[Test]
    public function votes_are_scoped_per_vendor(): void
    {
        $svc = $this->service();
        $svc->vote('wing-house', 'device-a');
        $svc->vote('luckys-homestyle', 'device-a');
        $this->assertSame(1, $svc->countFor('wing-house'));
        $this->assertSame(1, $svc->countFor('luckys-homestyle'));
        $this->assertSame(['wing-house' => 1, 'luckys-homestyle' => 1], $svc->counts());
    }
}
