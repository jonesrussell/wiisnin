<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;

/**
 * One "I'd order here" demand signal for a non-partner listing. Deduped per
 * device: at most one row per (vendor_slug, device_id). Counts are Russell's
 * outreach priority list (see the app:demand CLI).
 */
#[ContentEntityType(id: 'demand_vote', label: 'Demand vote', description: 'An "I would order here" signal for a listing.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'vendor_slug')]
final class DemandVote extends ContentEntityBase
{
    #[Field(label: 'Vendor slug', required: true, stored: FieldStorage::Data)]
    public string $vendor_slug = '';

    #[Field(label: 'Device id', description: 'Opaque per-device id for one-vote-per-device dedupe.', stored: FieldStorage::Data)]
    public string $device_id = '';

    #[Field(type: 'integer', label: 'Created at', description: 'Unix timestamp.', stored: FieldStorage::Data)]
    public ?int $created_at = null;

    public function getVendorSlug(): string
    {
        return (string) ($this->get('vendor_slug') ?? '');
    }

    public function getDeviceId(): string
    {
        return (string) ($this->get('device_id') ?? '');
    }

    public function getCreatedAt(): int
    {
        return (int) ($this->get('created_at') ?? 0);
    }
}
