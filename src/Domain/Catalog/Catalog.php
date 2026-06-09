<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Entity\MenuItem;
use App\Entity\Vendor;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Geo\GeoDistance;

/**
 * Read model for the storefront: vendor cards (with optional distance), the
 * "near you" distance-sorted list (geo package), and a vendor's grouped menu.
 * Constructed from repositories so it's unit-testable with in-memory doubles.
 */
final class Catalog
{
    /** @var array<int, ?string> tid => term name cache */
    private array $termNames = [];

    public function __construct(
        private readonly EntityRepositoryInterface $vendors,
        private readonly EntityRepositoryInterface $menuItems,
        private readonly EntityRepositoryInterface $terms,
    ) {}

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
            $cards[] = $this->vendorCard($vendor, $distance);
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
    public function vendorCard(Vendor $vendor, ?float $distanceKm = null): array
    {
        return [
            'id' => (int) $vendor->id(),
            'name' => $vendor->getName(),
            'slug' => $vendor->getSlug(),
            'community' => $this->termName($vendor->getCommunityTermId()),
            'cuisine' => $vendor->getCuisine(),
            'description' => (string) ($vendor->get('description') ?? ''),
            'is_open' => $vendor->isOpen(),
            'is_partner' => $vendor->isPartner(),
            'distance_km' => $distanceKm === null ? null : round($distanceKm, $distanceKm < 10 ? 1 : 0),
        ];
    }

    /**
     * Menu grouped by category, canonical order.
     *
     * @return list<array{category: string, items: list<array<string, mixed>>}>
     */
    public function menuForVendor(int $vendorId): array
    {
        $groups = [];
        foreach ($this->menuItems->findBy(['vendor_id' => $vendorId]) as $item) {
            if (!$item instanceof MenuItem) {
                continue;
            }
            $category = $this->termName($item->getCategoryTermId()) ?? 'Menu';
            $groups[$category] ??= [];
            $groups[$category][] = [
                'id' => (int) $item->id(),
                'name' => $item->getName(),
                'description' => $item->getDescription(),
                'price_cents' => $item->getPriceCents(),
                'available' => $item->isAvailable(),
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
