<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;

/**
 * A customer's star rating + short review of a vendor.
 *
 * The `engagement` package ships Comment/Reaction/Follow but no star-rating
 * review type (Comment/Reaction carry no rating) — see WAASEYAA-FRICTION.md — so
 * this is a small app entity. `status` ('visible'|'hidden') backs basic
 * moderation. Only live partners accept reviews (enforced in ReviewService).
 */
#[ContentEntityType(id: 'review', label: 'Review', description: 'A vendor rating + review.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'author_name')]
final class Review extends ContentEntityBase
{
    #[Field(type: 'integer', label: 'Vendor', required: true, description: 'vendor entity id.', stored: FieldStorage::Data)]
    public ?int $vendor_id = null;

    #[Field(type: 'integer', label: 'Author', description: 'user id (0 for a guest demo review).', stored: FieldStorage::Data)]
    public int $author_uid = 0;

    #[Field(label: 'Author name')]
    public string $author_name = 'Guest';

    #[Field(type: 'integer', label: 'Rating', required: true, description: '1–5 stars.', stored: FieldStorage::Data)]
    public int $rating = 5;

    #[Field(type: 'text', label: 'Body', stored: FieldStorage::Data)]
    public string $body = '';

    #[Field(label: 'Status', description: 'visible | hidden (moderation).', default: 'visible', stored: FieldStorage::Data)]
    public string $status = 'visible';

    #[Field(type: 'integer', label: 'Created at', description: 'Unix timestamp.', stored: FieldStorage::Data)]
    public ?int $created_at = null;

    public function getVendorId(): ?int
    {
        $id = $this->get('vendor_id');
        return $id === null ? null : (int) $id;
    }

    public function getAuthorName(): string
    {
        return (string) ($this->get('author_name') ?? 'Guest');
    }

    public function getRating(): int
    {
        return (int) ($this->get('rating') ?? 0);
    }

    public function getBody(): string
    {
        return (string) ($this->get('body') ?? '');
    }

    public function getStatus(): string
    {
        return (string) ($this->get('status') ?? 'visible');
    }

    public function isVisible(): bool
    {
        return $this->getStatus() === 'visible';
    }

    public function getCreatedAt(): int
    {
        return (int) ($this->get('created_at') ?? 0);
    }
}
