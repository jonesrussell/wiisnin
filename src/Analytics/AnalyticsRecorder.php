<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Entity\Event;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Validates an incoming beacon and writes one append-only Event row.
 *
 * Privacy (mirrors oiatc): raw IP and user-agent are NEVER stored. A visitor is
 * identified only by a daily-rotating salted hash, so the same person on the
 * same day collapses to one hash and cannot be tracked across days or
 * de-anonymised. Bots are filtered by UA. A light per-visitor rate limit caps
 * abuse without persisting any PII.
 *
 * Accepted beacons:
 *   pageview     {t,p,r,v}
 *   engagement   {t,v,s,d}
 *   vendor_view  {t,v,slug}
 *   call         {t,v,slug}
 *   directions   {t,v,slug}
 *   demand       {t,v,slug}
 *   search       {t,v,q}
 */
final class AnalyticsRecorder
{
    private const MAX_DWELL_MS = 86_400_000; // 24h cap

    private const SLUG_TYPES = ['vendor_view', 'call', 'directions', 'demand'];

    private const BOT_PATTERN =
        '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|preview|'
        . 'whatsapp|telegrambot|scrapy|curl|wget|python-requests|headless|lighthouse|monitor/i';

    /** @var \Closure(): int */
    private \Closure $clock;

    public function __construct(
        private readonly EntityRepositoryInterface $events,
        private readonly string $secret,
        ?\Closure $clock = null,
        private readonly int $maxPerWindow = 120,
        private readonly int $windowSeconds = 60,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * @param array<string,mixed> $beacon decoded JSON beacon
     *
     * @return bool true if a row was stored, false if the beacon was rejected
     */
    public function record(array $beacon, ?string $ip, ?string $userAgent): bool
    {
        $type = is_string($beacon['t'] ?? null) ? $beacon['t'] : '';
        $allowed = ['pageview', 'engagement', 'search', ...self::SLUG_TYPES];
        if (!in_array($type, $allowed, true)) {
            return false;
        }

        if ($userAgent !== null && $userAgent !== '' && preg_match(self::BOT_PATTERN, $userAgent) === 1) {
            return false;
        }

        $viewId = $this->str($beacon['v'] ?? null, 64);
        if ($viewId === null) {
            return false;
        }

        $now = ($this->clock)();
        $hash = $this->visitorHash($ip, $userAgent, $now);
        if ($this->rateLimited($hash, $now)) {
            return false;
        }

        $fields = [
            'event_type' => $type,
            'view_id' => $viewId,
            'visitor_hash' => $hash,
            'created_at' => $now,
        ];

        if ($type === 'pageview') {
            $path = $this->str($beacon['p'] ?? null, 255);
            if ($path === null) {
                return false;
            }
            $fields['path'] = $path;
            $fields['referrer_host'] = $this->refHost($beacon['r'] ?? null) ?? '';
            $fields['device'] = $this->device($userAgent) ?? '';
        } elseif ($type === 'engagement') {
            $fields['scroll_pct'] = $this->intRange($beacon['s'] ?? null, 0, 100);
            $fields['dwell_ms'] = $this->intRange($beacon['d'] ?? null, 0, self::MAX_DWELL_MS);
        } elseif ($type === 'search') {
            $query = $this->str($beacon['q'] ?? null, 100);
            if ($query === null) {
                return false;
            }
            $fields['query'] = $query;
        } else { // slug types
            $slug = $this->slug($beacon['slug'] ?? null);
            if ($slug === null) {
                return false;
            }
            $fields['slug'] = $slug;
        }

        $this->events->save(new Event($fields));

        return true;
    }

    /** Light per-visitor rate limit: at most maxPerWindow events per windowSeconds. */
    private function rateLimited(string $hash, int $now): bool
    {
        $recent = $this->events->findBy(['visitor_hash' => $hash], ['created_at' => 'desc'], $this->maxPerWindow);
        $cutoff = $now - $this->windowSeconds;
        $count = 0;
        foreach ($recent as $row) {
            if ($row instanceof Event && $row->getCreatedAt() >= $cutoff) {
                $count++;
            }
        }

        return $count >= $this->maxPerWindow;
    }

    private function visitorHash(?string $ip, ?string $userAgent, int $now): string
    {
        $dailySalt = hash_hmac('sha256', gmdate('Y-m-d', $now), $this->secret);

        return hash('sha256', $dailySalt . '|' . ($ip ?? '') . '|' . ($userAgent ?? ''));
    }

    private function device(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)/i', $userAgent) === 1) {
            return 'tablet';
        }
        if (preg_match('/Mobi|iPhone|iPod|Windows Phone|BlackBerry|IEMobile/i', $userAgent) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function refHost(mixed $referrer): ?string
    {
        if (!is_string($referrer) || $referrer === '') {
            return null;
        }
        $host = parse_url($referrer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? substr($host, 0, 255) : null;
    }

    /** A vendor slug: lowercase, alphanumeric + hyphen, capped. */
    private function slug(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function str(mixed $value, int $max): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return substr($value, 0, $max);
    }

    private function intRange(mixed $value, int $min, int $max): ?int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }
        $n = (int) $value;

        return max($min, min($max, $n));
    }
}
