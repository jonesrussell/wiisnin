<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Computes open/closed from a vendor's structured hours JSON.
 *
 * Honesty rule: returns null when hours are unknown (empty/invalid JSON) so the
 * UI shows NEITHER "Open now" nor "Closed" — we never invent hours. Only returns
 * a boolean when we actually have the vendor's hours.
 *
 * JSON shape: { "mon": [["HH:MM","HH:MM"], ...], "tue": [...], ... } in 24h local
 * time. A range whose end is <= start is treated as crossing midnight.
 */
final class OpenHours
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    private const TZ = 'America/Toronto';

    /**
     * @param string $hoursJson structured hours (may be empty)
     * @param int    $now       unix timestamp
     * @return bool|null true=open, false=closed, null=hours unknown
     */
    public static function isOpen(string $hoursJson, int $now, string $tz = self::TZ): ?bool
    {
        $hoursJson = trim($hoursJson);
        if ($hoursJson === '') {
            return null;
        }
        $data = json_decode($hoursJson, true);
        if (!is_array($data) || $data === []) {
            return null;
        }

        $dt = (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone($tz));
        $todayIdx = (int) $dt->format('N') - 1; // 1=Mon..7=Sun -> 0..6
        $today = self::DAYS[$todayIdx] ?? 'mon';
        $hm = $dt->format('H:i');

        // Open if inside a range today, or inside an overnight range that began
        // yesterday and crosses into today.
        if (self::inRanges($data[$today] ?? null, $hm, false)) {
            return true;
        }
        $yesterday = self::DAYS[($todayIdx + 6) % 7];
        if (self::inRanges($data[$yesterday] ?? null, $hm, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $ranges expected list of ["HH:MM","HH:MM"]
     */
    private static function inRanges(mixed $ranges, string $hm, bool $overnightTailOnly): bool
    {
        if (!is_array($ranges)) {
            return false;
        }
        foreach ($ranges as $range) {
            if (!is_array($range) || count($range) < 2) {
                continue;
            }
            $start = (string) $range[0];
            $end = (string) $range[1];
            $crossesMidnight = $end <= $start;

            if ($crossesMidnight) {
                // e.g. 22:00–02:00. Tail (00:00..end) belongs to the next day.
                if ($overnightTailOnly) {
                    if ($hm < $end) {
                        return true;
                    }
                } elseif ($hm >= $start) {
                    return true;
                }
            } elseif (!$overnightTailOnly && $hm >= $start && $hm < $end) {
                return true;
            }
        }

        return false;
    }
}
