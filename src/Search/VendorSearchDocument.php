<?php

declare(strict_types=1);

namespace App\Search;

use Waaseyaa\Search\SearchIndexableInterface;

/**
 * Indexes a vendor (name + cuisine + community + its menu item names) into the
 * FTS5 search index, so a search for a dish ("taco", "corn soup") or a kitchen
 * ("pizza") surfaces the right vendor. One document per vendor; the menu item
 * names are folded into the body.
 */
final class VendorSearchDocument implements SearchIndexableInterface
{
    public function __construct(
        private readonly int $vendorId,
        private readonly string $name,
        private readonly string $cuisine,
        private readonly string $community,
        private readonly string $slug,
        private readonly string $menuItemNames,
        private readonly bool $isPartner,
    ) {}

    public static function documentId(int $vendorId): string
    {
        return 'vendor:' . $vendorId;
    }

    /** Parse the vendor id back out of a search hit id. */
    public static function vendorIdFromHit(string $hitId): ?int
    {
        return str_starts_with($hitId, 'vendor:') ? (int) substr($hitId, 7) : null;
    }

    public function getSearchDocumentId(): string
    {
        return self::documentId($this->vendorId);
    }

    /** @return array<string, string> */
    public function toSearchDocument(): array
    {
        return [
            'title' => $this->name,
            'body' => trim($this->name . ' ' . $this->cuisine . ' ' . $this->community . ' ' . $this->menuItemNames),
        ];
    }

    /** @return array<string, mixed> */
    public function toSearchMetadata(): array
    {
        return [
            'entity_type' => 'vendor',
            'content_type' => 'vendor',
            'source_name' => 'wiisnin',
            'quality_score' => $this->isPartner ? 100 : 50,
            'topics' => [$this->community],
            'url' => '/vendor/' . $this->slug,
            'og_image' => '',
            'created_at' => '2026-01-01T00:00:00+00:00',
        ];
    }
}
