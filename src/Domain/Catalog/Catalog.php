<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Demand\DemandService;
use App\Domain\Review\ReviewService;
use App\Entity\MenuItem;
use App\Entity\Vendor;
use App\Support\OpenHours;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Geo\GeoDistance;

/**
 * Read model for the storefront: vendor cards (with optional distance), the
 * "near you" distance-sorted list (geo package), and a vendor's grouped menu.
 * Constructed from repositories so it's unit-testable with in-memory doubles.
 */
final class Catalog
{
    /**
     * Real photos, keyed by vendor slug. Only live partners have them; everyone
     * else keeps the warm colour-tint placeholders (honesty rule). Item keys are
     * the lower-cased English menu-item name. Files are served from public/img/.
     *
     * @var array<string, array{hero: string, items: array<string, string>}>
     */
    private const VENDOR_PHOTOS = [
        'meedjims-foodland' => [
            'hero' => '/img/meedjims/building.jpg',
            'items' => [
                'corn soup' => '/img/meedjims/corn-soup.jpg',
                'poutine' => '/img/meedjims/poutine.jpg',
            ],
        ],
    ];

    /** @var array<int, ?string> tid => term name cache */
    private array $termNames = [];

    /** @var \Closure(): int */
    private \Closure $clock;

    public function __construct(
        private readonly EntityRepositoryInterface $vendors,
        private readonly EntityRepositoryInterface $menuItems,
        private readonly EntityRepositoryInterface $terms,
        private readonly ?ReviewService $reviews = null,
        private readonly ?DemandService $demand = null,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /** Demand-vote count for a listing (0 when demand isn't wired). */
    public function demandFor(string $slug): int
    {
        return $this->demand?->countFor($slug) ?? 0;
    }

    /**
     * Visible reviews for a vendor (newest first); [] when reviews aren't wired.
     *
     * @return list<array<string, mixed>>
     */
    public function reviewsFor(int $vendorId): array
    {
        return $this->reviews?->listFor($vendorId) ?? [];
    }

    public function vendorBySlug(string $slug): ?Vendor
    {
        $rows = $this->vendors->findBy(['slug' => $slug], null, 1);
        $vendor = $rows[0] ?? null;

        return $vendor instanceof Vendor ? $vendor : null;
    }

    /**
     * Vendors as cards, optionally restricted to a set of ids (search results),
     * filtered by community, and — when user coords are given — sorted nearest
     * first with a distance badge. Without coords: partners first, then name.
     *
     * @param list<int>|null $restrictIds
     * @return list<array<string, mixed>>
     */
    public function vendorsNear(
        ?float $userLat = null,
        ?float $userLng = null,
        ?string $community = null,
        ?array $restrictIds = null,
        string $locale = 'en',
    ): array {
        $cards = [];
        foreach ($this->vendors->findBy([]) as $vendor) {
            if (!$vendor instanceof Vendor) {
                continue;
            }
            if ($restrictIds !== null && !in_array((int) $vendor->id(), $restrictIds, true)) {
                continue;
            }
            if ($community !== null && $community !== '' && $community !== 'All'
                && $this->termName($vendor->getCommunityTermId()) !== $community) {
                continue;
            }

            $distance = null;
            if ($userLat !== null && $userLng !== null
                && $vendor->getLatitude() !== null && $vendor->getLongitude() !== null) {
                $distance = GeoDistance::haversine($userLat, $userLng, $vendor->getLatitude(), $vendor->getLongitude());
            }
            $cards[] = $this->vendorCard($vendor, $distance, $locale);
        }

        usort($cards, static function (array $a, array $b): int {
            // Distance-sorted when both have it; else partners first, then name.
            if ($a['distance_km'] !== null && $b['distance_km'] !== null) {
                return $a['distance_km'] <=> $b['distance_km'];
            }
            if ($a['is_partner'] !== $b['is_partner']) {
                return $a['is_partner'] ? -1 : 1;
            }
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return $cards;
    }

    /**
     * @return array<string, mixed>
     */
    public function vendorCard(Vendor $vendor, ?float $distanceKm = null, string $locale = 'en'): array
    {
        $slug = (string) $vendor->getSlug();

        return [
            'id' => (int) $vendor->id(),
            'name' => $this->localized($vendor, 'name', $locale),
            'slug' => $slug,
            'community' => $this->termName($vendor->getCommunityTermId()),
            'cuisine' => $vendor->getCuisine(),
            'description' => $this->localized($vendor, 'description', $locale),
            // 'open' is the only open/closed signal the UI may show: tri-state
            // true/false/null (null = unknown -> never fake "Open now").
            'open' => $this->openStatus($vendor),
            'is_partner' => $vendor->isPartner(),
            'opening_soon' => $vendor->isOpeningSoon(),
            'contact_phone' => $vendor->getContactPhone(),
            'address' => $vendor->getAddress(),
            'maps_url' => $this->mapsUrl($vendor),
            'hours' => $vendor->getHours(),
            'distance_km' => $distanceKm === null ? null : round($distanceKm, $distanceKm < 10 ? 1 : 0),
            'rating' => $this->reviews?->summary((int) $vendor->id()) ?? ['average' => null, 'count' => 0],
            'demand' => $this->demandFor($slug),
            'image' => self::VENDOR_PHOTOS[$slug]['hero'] ?? null,
        ];
    }

    /**
     * Open/closed: computed from structured hours when known, else the partner's
     * operational flag, else null (unknown — UI shows neither; never fake it).
     */
    private function openStatus(Vendor $vendor): ?bool
    {
        $hoursJson = $vendor->getHoursJson();
        if ($hoursJson !== '') {
            return OpenHours::isOpen($hoursJson, ($this->clock)());
        }
        if ($vendor->isPartner()) {
            return $vendor->isOpen();
        }

        return null;
    }

    /** Google Maps directions URL from the street address, else coords, else null. */
    private function mapsUrl(Vendor $vendor): ?string
    {
        $address = trim($vendor->getAddress());
        if ($address !== '') {
            $dest = $vendor->getName() . ', ' . $address;
        } elseif ($vendor->getLatitude() !== null && $vendor->getLongitude() !== null) {
            $dest = $vendor->getLatitude() . ',' . $vendor->getLongitude();
        } else {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($dest);
    }

    /** Localized field value: the *_oj field for Nishnaabemwin when non-empty, else English. */
    private function localized(Vendor|MenuItem $entity, string $field, string $locale): string
    {
        if ($locale === 'oj') {
            $oj = (string) ($entity->get($field . '_oj') ?? '');
            if ($oj !== '') {
                return $oj;
            }
        }
        return (string) ($entity->get($field) ?? '');
    }

    /**
     * Menu grouped by category, canonical order.
     *
     * @return list<array{category: string, items: list<array<string, mixed>>}>
     */
    public function menuForVendor(int $vendorId, string $locale = 'en'): array
    {
        $vendor = $this->vendors->find((string) $vendorId);
        $slug = $vendor instanceof Vendor ? (string) $vendor->getSlug() : '';
        $itemPhotos = self::VENDOR_PHOTOS[$slug]['items'] ?? [];

        $groups = [];
        foreach ($this->menuItems->findBy(['vendor_id' => $vendorId]) as $item) {
            if (!$item instanceof MenuItem) {
                continue;
            }
            $category = $this->termName($item->getCategoryTermId()) ?? 'Menu';
            $groups[$category] ??= [];
            $groups[$category][] = [
                'id' => (int) $item->id(),
                'name' => $this->localized($item, 'name', $locale),
                'description' => $this->localized($item, 'description', $locale),
                'price_cents' => $item->getPriceCents(),
                'available' => $item->isAvailable(),
                'image' => $itemPhotos[strtolower(trim($item->getName()))] ?? null,
            ];
        }

        $order = ['Native cuisine', 'Grill', 'Daily specials'];
        uksort($groups, static function (string $a, string $b) use ($order): int {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);
            return ($ia === false ? PHP_INT_MAX : $ia) <=> ($ib === false ? PHP_INT_MAX : $ib);
        });

        $out = [];
        foreach ($groups as $category => $items) {
            $out[] = ['category' => $category, 'items' => $items];
        }
        return $out;
    }

    /** Menu item names for a vendor (used to build the search document). */
    public function menuItemNames(int $vendorId): string
    {
        $names = [];
        foreach ($this->menuItems->findBy(['vendor_id' => $vendorId]) as $item) {
            if ($item instanceof MenuItem) {
                $names[] = $item->getName();
            }
        }
        return implode(' ', $names);
    }

    private function termName(?int $tid): ?string
    {
        if ($tid === null) {
            return null;
        }
        if (array_key_exists($tid, $this->termNames)) {
            return $this->termNames[$tid];
        }
        $term = $this->terms->find((string) $tid);
        return $this->termNames[$tid] = $term !== null ? (string) $term->get('name') : null;
    }
}
