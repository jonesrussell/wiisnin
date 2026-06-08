<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;

/**
 * A single item on a vendor's menu.
 *
 * `price_cents` stores money as an integer number of cents to avoid float
 * rounding. Prices are placeholders until confirmed with the vendor (see seed).
 * Non-key fields are FieldStorage::Data so findBy() (e.g. by vendor_id) works.
 */
#[ContentEntityType(id: 'menu_item', label: 'Menu item', description: 'An item on a vendor menu.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'name')]
final class MenuItem extends ContentEntityBase
{
    #[Field(type: 'integer', label: 'Vendor', required: true, description: 'vendor entity id.', stored: FieldStorage::Data)]
    public ?int $vendor_id = null;

    #[Field(type: 'integer', label: 'Category', description: 'taxonomy_term id in the "menu_category" vocabulary.', stored: FieldStorage::Data)]
    public ?int $category_tid = null;

    #[Field(label: 'Name', required: true)]
    public string $name = '';

    #[Field(type: 'text', label: 'Description', stored: FieldStorage::Data)]
    public string $description = '';

    #[Field(type: 'integer', label: 'Price (cents)', required: true, description: 'Money in integer cents. PLACEHOLDER until confirmed.', stored: FieldStorage::Data)]
    public int $price_cents = 0;

    #[Field(type: 'integer', label: 'Photo', description: 'media entity id (mid).', stored: FieldStorage::Data)]
    public ?int $photo_mid = null;

    #[Field(type: 'boolean', label: 'Available', default: 1, stored: FieldStorage::Data)]
    public bool $available = true;

    public function getVendorId(): ?int
    {
        $id = $this->get('vendor_id');
        return $id === null ? null : (int) $id;
    }

    public function getCategoryTermId(): ?int
    {
        $tid = $this->get('category_tid');
        return $tid === null ? null : (int) $tid;
    }

    public function getName(): string
    {
        return (string) ($this->get('name') ?? '');
    }

    public function getDescription(): string
    {
        return (string) ($this->get('description') ?? '');
    }

    public function getPriceCents(): int
    {
        return (int) ($this->get('price_cents') ?? 0);
    }

    public function isAvailable(): bool
    {
        return (bool) ($this->get('available') ?? false);
    }
}
