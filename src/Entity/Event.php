<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;

/**
 * Append-only first-party analytics event. Written ONLY by AnalyticsRecorder
 * (insert; never updated/deleted in normal operation). No PII: the visitor is a
 * daily-rotating salted hash (not reversible, not cross-day), no raw IP/UA.
 *
 * Event types: pageview {path,referrer_host,device}, engagement {scroll_pct,
 * dwell_ms}, and Wiisnin actions vendor_view/call/directions/demand {slug},
 * search {query}. All carry a view_id so a session's events group together.
 */
#[ContentEntityType(id: 'event', label: 'Event', description: 'Append-only first-party analytics event.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'event_type')]
final class Event extends ContentEntityBase
{
    #[Field(label: 'Event type', required: true)]
    public string $event_type = '';

    #[Field(label: 'Path', description: 'pageview path.', stored: FieldStorage::Data)]
    public string $path = '';

    #[Field(label: 'Referrer host', stored: FieldStorage::Data)]
    public string $referrer_host = '';

    #[Field(label: 'View id', description: 'Opaque per-view id (groups a session\'s events).', stored: FieldStorage::Data)]
    public string $view_id = '';

    #[Field(label: 'Visitor hash', description: 'Daily-salted hash of IP+UA (non-PII, rate-limit + unique-ish).', stored: FieldStorage::Data)]
    public string $visitor_hash = '';

    #[Field(label: 'Device', description: 'mobile | tablet | desktop.', stored: FieldStorage::Data)]
    public string $device = '';

    #[Field(type: 'integer', label: 'Scroll %', stored: FieldStorage::Data)]
    public ?int $scroll_pct = null;

    #[Field(type: 'integer', label: 'Dwell ms', stored: FieldStorage::Data)]
    public ?int $dwell_ms = null;

    #[Field(label: 'Vendor slug', description: 'For vendor_view/call/directions/demand.', stored: FieldStorage::Data)]
    public string $slug = '';

    #[Field(label: 'Query', description: 'For search events.', stored: FieldStorage::Data)]
    public string $query = '';

    #[Field(type: 'integer', label: 'Created at', description: 'Unix timestamp.', stored: FieldStorage::Data)]
    public ?int $created_at = null;

    public function getEventType(): string
    {
        return (string) ($this->get('event_type') ?? '');
    }

    public function getPath(): string
    {
        return (string) ($this->get('path') ?? '');
    }

    public function getReferrerHost(): string
    {
        return (string) ($this->get('referrer_host') ?? '');
    }

    public function getViewId(): string
    {
        return (string) ($this->get('view_id') ?? '');
    }

    public function getVisitorHash(): string
    {
        return (string) ($this->get('visitor_hash') ?? '');
    }

    public function getDevice(): string
    {
        return (string) ($this->get('device') ?? '');
    }

    public function getScrollPct(): ?int
    {
        $v = $this->get('scroll_pct');
        return $v === null ? null : (int) $v;
    }

    public function getDwellMs(): ?int
    {
        $v = $this->get('dwell_ms');
        return $v === null ? null : (int) $v;
    }

    public function getSlug(): string
    {
        return (string) ($this->get('slug') ?? '');
    }

    public function getQuery(): string
    {
        return (string) ($this->get('query') ?? '');
    }

    public function getCreatedAt(): int
    {
        return (int) ($this->get('created_at') ?? 0);
    }
}
