<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;

/**
 * An owner's request to claim a directory listing and set up ordering. Stored as
 * the durable record (email to Russell is best-effort on top). `status` lets
 * Russell triage ('new' | 'contacted' | 'closed') via the app:claims CLI.
 */
#[ContentEntityType(id: 'claim_request', label: 'Claim request', description: 'Owner request to claim a listing.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'owner_name')]
final class ClaimRequest extends ContentEntityBase
{
    #[Field(label: 'Vendor slug', required: true, stored: FieldStorage::Data)]
    public string $vendor_slug = '';

    #[Field(label: 'Vendor name', stored: FieldStorage::Data)]
    public string $vendor_name = '';

    #[Field(label: 'Owner name', required: true)]
    public string $owner_name = '';

    #[Field(label: 'Phone', stored: FieldStorage::Data)]
    public string $phone = '';

    #[Field(label: 'Email', stored: FieldStorage::Data)]
    public string $email = '';

    #[Field(type: 'text', label: 'Note', stored: FieldStorage::Data)]
    public string $note = '';

    #[Field(label: 'Status', description: 'new | contacted | closed.', default: 'new', stored: FieldStorage::Data)]
    public string $status = 'new';

    #[Field(type: 'integer', label: 'Created at', description: 'Unix timestamp.', stored: FieldStorage::Data)]
    public ?int $created_at = null;

    public function getVendorSlug(): string
    {
        return (string) ($this->get('vendor_slug') ?? '');
    }

    public function getVendorName(): string
    {
        return (string) ($this->get('vendor_name') ?? '');
    }

    public function getOwnerName(): string
    {
        return (string) ($this->get('owner_name') ?? '');
    }

    public function getPhone(): string
    {
        return (string) ($this->get('phone') ?? '');
    }

    public function getEmail(): string
    {
        return (string) ($this->get('email') ?? '');
    }

    public function getNote(): string
    {
        return (string) ($this->get('note') ?? '');
    }

    public function getStatus(): string
    {
        return (string) ($this->get('status') ?? 'new');
    }

    public function getCreatedAt(): int
    {
        return (int) ($this->get('created_at') ?? 0);
    }
}
