<?php

declare(strict_types=1);

namespace App\Seed;

use App\Access\CommerceAccess;
use App\Entity\GroupMembership;
use App\Entity\MenuItem;
use App\Entity\Vendor;
use App\Search\VendorSearchDocument;
use App\Support\Communities;
use App\Support\VendorData;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Path\PathAlias;
use Waaseyaa\Search\SearchIndexerInterface;
use Waaseyaa\Taxonomy\Term;

/**
 * Idempotent seed of VERIFIED data (AREA-VENDORS-VERIFIED.md):
 *   - community taxonomy terms for the 8 served towns + menu_category terms,
 *   - Meedjims Foodland (Sagamok) — the ONE live, orderable partner, real 9-item menu,
 *   - ~20 verified directory/info listings across the North Shore (no menu/ordering;
 *     "ordering coming soon / claim this listing"),
 *   - a clean path alias per vendor (/meedjims etc.),
 *   - the FTS5 search index (vendor name + cuisine + community + menu item names).
 *
 * Meedjims prices are DRAFT and badged "to be confirmed". Info listings are
 * browsable but not orderable (OrderService rejects non-partners).
 */
final class WiisninSeeder
{
    public function __construct(
        private readonly EntityRepositoryInterface $terms,
        private readonly EntityRepositoryInterface $vendors,
        private readonly EntityRepositoryInterface $menuItems,
        private readonly EntityRepositoryInterface $groups,
        private readonly EntityRepositoryInterface $memberships,
        private readonly EntityRepositoryInterface $pathAliases,
        private readonly ?SearchIndexerInterface $searchIndexer = null,
    ) {}

    public function run(CliIO $io): int
    {
        // 1. Taxonomy.
        $communityTids = [];
        foreach (Communities::NAMES as $name) {
            $communityTids[$name] = $this->ensureTerm('community', $name);
        }
        foreach (['Native cuisine', 'Grill', 'Daily specials', 'Menu'] as $cat) {
            $this->ensureTerm('menu_category', $cat);
        }
        $io->writeln('Communities + menu categories ensured.');

        // 2. Verified vendor data. Meedjims = the live partner; the rest are
        // directory/info listings ("ordering coming soon"). See VendorData.
        $specs = VendorData::vendors();
        $created = 0;
        foreach ($specs as $spec) {
            if ($this->vendors->findBy(['slug' => $spec['slug']], null, 1) !== []) {
                continue; // idempotent
            }

            [$lat, $lng] = Communities::centroid($spec['community']) ?? [null, null];
            $lat = $lat !== null ? $lat + $spec['jitter'][0] : null;
            $lng = $lng !== null ? $lng + $spec['jitter'][1] : null;

            $groupId = null;
            if ($spec['partner']) {
                $groupId = $this->ensureVendorGroup($io, $spec['name']);
                if ($groupId !== null) {
                    $this->memberships->save(new GroupMembership(['group_id' => $groupId, 'user_id' => 1, 'role' => 'owner']));
                }
            }

            $vendor = new Vendor([
                'name' => $spec['name'],
                'slug' => $spec['slug'],
                'community_tid' => $communityTids[$spec['community']] ?? null,
                'cuisine' => $spec['cuisine'],
                'description' => $spec['description'],
                'hours' => $spec['hours'],
                'hours_json' => $spec['hours_json'],
                'address' => $spec['address'],
                'is_open' => 1,
                'is_partner' => $spec['partner'] ? 1 : 0,
                'owner_group_id' => $groupId,
                'contact_phone' => $spec['phone'],
                'contact_email' => '',
                'latitude' => $lat,
                'longitude' => $lng,
            ]);
            $this->vendors->save($vendor);
            $vendorId = (int) $vendor->id();

            foreach ($spec['menu'] as [$category, $name, $priceCents]) {
                $this->menuItems->save(new MenuItem([
                    'vendor_id' => $vendorId,
                    'category_tid' => $this->ensureTerm('menu_category', $category),
                    'name' => $name,
                    'description' => '',
                    'price_cents' => $priceCents,
                    'available' => 1,
                ]));
            }

            $alias = $spec['slug'] === 'meedjims-foodland' ? 'meedjims' : $spec['slug'];
            $this->ensureAlias('/' . $alias, '/vendor/' . $spec['slug']);
            $this->indexVendor($vendor, $spec);
            $created++;
        }

        $io->writeln($created === 0
            ? 'All vendors already seeded; nothing to do.'
            : "Seeded {$created} vendor(s): 1 live partner (Meedjims) + sample listings. DRAFT prices.");
        $io->writeln('Roles: ' . implode(', ', array_keys(CommerceAccess::roles())));

        return 0;
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function indexVendor(Vendor $vendor, array $spec): void
    {
        if ($this->searchIndexer === null) {
            return;
        }
        try {
            $this->searchIndexer->ensureSchema();
            $menuNames = implode(' ', array_map(static fn (array $m): string => $m[1], $spec['menu']));
            $this->searchIndexer->index(new VendorSearchDocument(
                vendorId: (int) $vendor->id(),
                name: $spec['name'],
                cuisine: $spec['cuisine'],
                community: $spec['community'],
                slug: $spec['slug'],
                menuItemNames: $menuNames,
                isPartner: (bool) $spec['partner'],
            ));
        } catch (\Throwable) {
            // Search indexing is best-effort; never fail the seed over it.
        }
    }

    private function ensureAlias(string $alias, string $systemPath): void
    {
        if ($this->pathAliases->findBy(['alias' => $alias], null, 1) !== []) {
            return;
        }
        $this->pathAliases->save(new PathAlias([
            'path' => $systemPath,
            'alias' => $alias,
            'langcode' => 'en',
            'status' => true,
        ]));
    }

    private function ensureTerm(string $vid, string $name): int
    {
        $existing = $this->terms->findBy(['vid' => $vid, 'name' => $name], null, 1);
        if ($existing !== []) {
            return (int) $existing[0]->id();
        }
        $term = new Term(['vid' => $vid, 'name' => $name, 'status' => true]);
        $this->terms->save($term);
        return (int) $term->id();
    }

    private function ensureVendorGroup(CliIO $io, string $name): ?int
    {
        try {
            $group = new \Waaseyaa\Groups\Group(['type' => 'vendor', 'name' => $name]);
            $this->groups->save($group);
            return (int) $group->id();
        } catch (\Throwable $e) {
            $io->writeln('NOTE: could not create vendor group (' . $e->getMessage() . '); continuing.');
            return null;
        }
    }
}
