<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;
use Waaseyaa\Notification\NotifiableInterface;

/**
 * A food vendor (restaurant) customers can order from.
 *
 * Reference fields (`*_tid`, `*_group_id`, `*_mid`) hold the integer id of the
 * referenced entity. We use plain integer foreign keys rather than the
 * relationship package's graph records or the entity_reference field type — see
 * WAASEYAA-FRICTION.md for why that fits an order-taking domain better.
 *
 * Non-key fields are stored in the entity `_data` JSON blob (FieldStorage::Data)
 * so findBy() can filter them via json_extract; the default sql-blob backend
 * creates no per-field columns. See WAASEYAA-FRICTION.md.
 *
 * Implements NotifiableInterface so the notification package can route a
 * new-order alert to the vendor by mail, Mercure topic, or (future) SMS.
 */
#[ContentEntityType(id: 'vendor', label: 'Vendor', description: 'A food vendor customers can order from.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'name')]
final class Vendor extends ContentEntityBase implements NotifiableInterface
{
    #[Field(label: 'Name', required: true)]
    public string $name = '';

    #[Field(label: 'Slug', required: true, description: 'URL-friendly id, used at /vendor/{slug}.', stored: FieldStorage::Data)]
    public string $slug = '';

    #[Field(type: 'integer', label: 'Community', description: 'taxonomy_term id in the "community" vocabulary.', stored: FieldStorage::Data)]
    public ?int $community_tid = null;

    #[Field(type: 'text', label: 'Description', stored: FieldStorage::Data)]
    public string $description = '';

    #[Field(type: 'text', label: 'Hours', description: 'Human-readable opening hours for display.', stored: FieldStorage::Data)]
    public string $hours = '';

    #[Field(type: 'text', label: 'Hours (structured)', description: 'JSON {mon:[[\"HH:MM\",\"HH:MM\"]],...} for computing open/closed. Empty = unknown (never fake "Open now").', stored: FieldStorage::Data)]
    public string $hours_json = '';

    #[Field(label: 'Address', description: 'Street address for directions/maps.', stored: FieldStorage::Data)]
    public string $address = '';

    #[Field(type: 'boolean', label: 'Open', description: 'Whether the vendor is currently accepting orders.', default: 1, stored: FieldStorage::Data)]
    public bool $is_open = true;

    #[Field(type: 'integer', label: 'Owner group', description: 'groups package group id; vendor_staff are members.', stored: FieldStorage::Data)]
    public ?int $owner_group_id = null;

    #[Field(label: 'Contact phone', stored: FieldStorage::Data)]
    public string $contact_phone = '';

    #[Field(label: 'Contact email', description: 'Where new-order mail notifications are sent.', stored: FieldStorage::Data)]
    public string $contact_email = '';

    #[Field(type: 'integer', label: 'Logo', description: 'media entity id (mid) for the vendor logo.', stored: FieldStorage::Data)]
    public ?int $logo_mid = null;

    #[Field(type: 'boolean', label: 'Partner', description: 'True = live orderable partner (Meedjims). False = sample listing, not yet a partner.', default: 0, stored: FieldStorage::Data)]
    public bool $is_partner = false;

    #[Field(label: 'Cuisine', description: 'Short cuisine line, e.g. "Native cuisine & grill".', stored: FieldStorage::Data)]
    public string $cuisine = '';

    #[Field(label: 'Name (Nishnaabemwin)', description: 'Anishinaabemowin name — blank until community-confirmed (i18n seam).', stored: FieldStorage::Data)]
    public string $name_oj = '';

    #[Field(type: 'text', label: 'Description (Nishnaabemwin)', description: 'Anishinaabemowin description — blank until confirmed.', stored: FieldStorage::Data)]
    public string $description_oj = '';

    #[Field(type: 'float', label: 'Latitude', description: 'Vendor latitude for distance sort (geo).', stored: FieldStorage::Data)]
    public ?float $latitude = null;

    #[Field(type: 'float', label: 'Longitude', description: 'Vendor longitude for distance sort (geo).', stored: FieldStorage::Data)]
    public ?float $longitude = null;

    public function getName(): string
    {
        return (string) ($this->get('name') ?? '');
    }

    public function getSlug(): string
    {
        return (string) ($this->get('slug') ?? '');
    }

    public function getCommunityTermId(): ?int
    {
        $tid = $this->get('community_tid');
        return $tid === null ? null : (int) $tid;
    }

    public function getOwnerGroupId(): ?int
    {
        $gid = $this->get('owner_group_id');
        return $gid === null ? null : (int) $gid;
    }

    public function isOpen(): bool
    {
        return (bool) ($this->get('is_open') ?? false);
    }

    public function getContactPhone(): string
    {
        return (string) ($this->get('contact_phone') ?? '');
    }

    public function getAddress(): string
    {
        return (string) ($this->get('address') ?? '');
    }

    public function getHours(): string
    {
        return (string) ($this->get('hours') ?? '');
    }

    public function getHoursJson(): string
    {
        return (string) ($this->get('hours_json') ?? '');
    }

    public function getContactEmail(): string
    {
        return (string) ($this->get('contact_email') ?? '');
    }

    public function isPartner(): bool
    {
        return (bool) ($this->get('is_partner') ?? false);
    }

    public function getCuisine(): string
    {
        return (string) ($this->get('cuisine') ?? '');
    }

    public function getLatitude(): ?float
    {
        $v = $this->get('latitude');
        return $v === null ? null : (float) $v;
    }

    public function getLongitude(): ?float
    {
        $v = $this->get('longitude');
        return $v === null ? null : (float) $v;
    }

    // --- NotifiableInterface -------------------------------------------------

    public function routeNotificationFor(string $channel): mixed
    {
        return match ($channel) {
            'mail' => $this->getContactEmail(),
            'sms' => $this->getContactPhone(),
            // Clients subscribe to this Mercure topic for live order updates.
            'mercure' => 'vendor/' . $this->id() . '/orders',
            default => null,
        };
    }

    public function getNotifiableId(): string
    {
        return (string) $this->id();
    }

    public function getNotifiableType(): string
    {
        return $this->getEntityTypeId();
    }
}
