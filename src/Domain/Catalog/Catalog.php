<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Entity\MenuItem;
use App\Entity\Vendor;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Read model for the customer-facing storefront: vendors by community, a vendor
 * by slug, and a vendor's menu grouped by category. Returns plain arrays ready
 * to hand to Inertia. Constructed from repositories so it's unit-testable with
 * in-memory doubles.
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
     * @return list<array<string, mixed>>
     */
    public function vendorsInCommunity(string $communityName): array
    {
        $tid = $this->termTid('community', $communityName);
        if ($tid === null) {
            return [];
        }

        $out = [];
        foreach ($this->vendors->findBy(['community_tid' => $tid]) as $vendor) {
            if ($vendor instanceof Vendor) {
                $out[] = $this->vendorCard($vendor);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function vendorCard(Vendor $vendor): array
    {
        return [
            'id' => (int) $vendor->id(),
            'name' => $vendor->getName(),
            'slug' => $vendor->getSlug(),
            'community' => $this->termName($vendor->getCommunityTermId()),
            'description' => (string) ($vendor->get('description') ?? ''),
            'hours' => (string) ($vendor->get('hours') ?? ''),
            'is_open' => $vendor->isOpen(),
        ];
    }

    /**
     * Menu grouped by category, in the canonical category order.
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
            $ia = $ia === false ? PHP_INT_MAX : $ia;
            $ib = $ib === false ? PHP_INT_MAX : $ib;
            return $ia <=> $ib;
        });

        $out = [];
        foreach ($groups as $category => $items) {
            $out[] = ['category' => $category, 'items' => $items];
        }

        return $out;
    }

    private function termTid(string $vid, string $name): ?int
    {
        $rows = $this->terms->findBy(['vid' => $vid, 'name' => $name], null, 1);
        $term = $rows[0] ?? null;

        return $term !== null ? (int) $term->id() : null;
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
