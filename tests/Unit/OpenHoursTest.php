<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\OpenHours;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Open/closed is computed only when hours are known; unknown hours return null so
 * the UI never fakes "Open now".
 */
final class OpenHoursTest extends TestCase
{
    private const ROGER = '{"mon":[["06:00","14:00"]],"tue":[["06:00","14:00"]],"wed":[["06:00","14:00"]],"thu":[["06:00","14:00"]],"fri":[["06:00","14:00"]],"sat":[["06:00","14:00"]],"sun":[["07:00","14:00"]]}';

    private function ts(string $local): int
    {
        return (new \DateTimeImmutable($local, new \DateTimeZone('America/Toronto')))->getTimestamp();
    }

    #[Test]
    public function unknown_hours_return_null(): void
    {
        $now = $this->ts('2026-06-10 10:00');
        $this->assertNull(OpenHours::isOpen('', $now));
        $this->assertNull(OpenHours::isOpen('{}', $now));
        $this->assertNull(OpenHours::isOpen('not json', $now));
    }

    #[Test]
    public function open_inside_the_window_closed_outside(): void
    {
        // Wednesday.
        $this->assertTrue(OpenHours::isOpen(self::ROGER, $this->ts('2026-06-10 10:00')));
        $this->assertFalse(OpenHours::isOpen(self::ROGER, $this->ts('2026-06-10 15:00')));
        $this->assertFalse(OpenHours::isOpen(self::ROGER, $this->ts('2026-06-10 05:00')));
    }

    #[Test]
    public function the_boundary_open_is_inclusive_close_is_exclusive(): void
    {
        $this->assertTrue(OpenHours::isOpen(self::ROGER, $this->ts('2026-06-10 06:00')));
        $this->assertFalse(OpenHours::isOpen(self::ROGER, $this->ts('2026-06-10 14:00')));
    }

    #[Test]
    public function per_day_hours_differ(): void
    {
        // Sunday opens 07:00, so 06:30 Sunday is closed.
        $this->assertFalse(OpenHours::isOpen(self::ROGER, $this->ts('2026-06-14 06:30')));
        $this->assertTrue(OpenHours::isOpen(self::ROGER, $this->ts('2026-06-14 08:00')));
    }

    #[Test]
    public function overnight_ranges_cross_midnight(): void
    {
        $late = '{"fri":[["22:00","02:00"]],"sat":[["22:00","02:00"]]}';
        // Saturday 01:00 — still open from Friday night's range.
        $this->assertTrue(OpenHours::isOpen($late, $this->ts('2026-06-13 01:00')));
        // Saturday 03:00 — closed.
        $this->assertFalse(OpenHours::isOpen($late, $this->ts('2026-06-13 03:00')));
        // Friday 23:00 — open.
        $this->assertTrue(OpenHours::isOpen($late, $this->ts('2026-06-12 23:00')));
    }
}
