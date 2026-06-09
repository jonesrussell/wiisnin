<?php

declare(strict_types=1);

namespace App\Seed;

use App\Access\CommerceAccess;
use App\Entity\GroupMembership;
use App\Entity\MenuItem;
use App\Entity\Vendor;
use App\Search\VendorSearchDocument;
use App\Support\Communities;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Path\PathAlias;
use Waaseyaa\Search\SearchIndexerInterface;
use Waaseyaa\Taxonomy\Term;

/**
 * Idempotent seed of demo data:
 *   - community + menu_category taxonomy terms,
 *   - Meedjims Foodland (Sagamok) — the ONE live, orderable partner, real 9-item menu,
 *   - area "Sample listing — not yet a partner" vendors (per AREA-VENDORS.md),
 *   - a clean path alias per vendor (/meedjims etc.),
 *   - the FTS5 search index (vendor name + cuisine + community + menu item names).
 *
 * ALL prices are DRAFT and badged "to be confirmed" in the UI. Sample vendors are
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

        // 2. Vendor specs. Meedjims = live partner; the rest = sample listings.
        $specs = $this->vendorSpecs();
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
                'hours' => $spec['partner'] ? 'Hours TBD — confirm with the family.' : '',
                'is_open' => $spec['open'] ? 1 : 0,
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

            $this->ensureAlias('/' . $spec['alias'], '/vendor/' . $spec['slug']);
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
     * @return list<array{slug:string,name:string,community:string,cuisine:string,description:string,partner:bool,open:bool,phone:string,alias:string,jitter:array{0:float,1:float},menu:list<array{0:string,1:string,2:int}>}>
     */
    private function vendorSpecs(): array
    {
        return [
            [
                'slug' => 'meedjims-foodland', 'name' => 'Meedjims Foodland', 'community' => 'Sagamok',
                'cuisine' => 'Native cuisine & grill', 'description' => 'Family-owned in Sagamok First Nation since 1989, recently reopened.',
                'partner' => true, 'open' => true, 'phone' => '705-865-1537', 'alias' => 'meedjims', 'jitter' => [0.0, 0.0],
                'menu' => [
                    ['Native cuisine', 'Scone', 400], ['Native cuisine', 'Indian taco', 1400],
                    ['Native cuisine', 'Scone dog', 900], ['Native cuisine', 'Scone & bologna', 800],
                    ['Grill', 'Hamburger', 800], ['Grill', 'French fries', 500],
                    ['Grill', 'Poutine', 1000], ['Grill', 'Cheese fries', 900],
                    ['Daily specials', 'Corn soup', 800],
                ],
            ],
            [
                'slug' => 'back-home-bistro', 'name' => 'Back Home Bistro', 'community' => 'Massey',
                'cuisine' => 'Homemade comfort food', 'description' => 'Hearty homemade comfort food, curbside pickup.',
                'partner' => false, 'open' => true, 'phone' => '', 'alias' => 'back-home-bistro', 'jitter' => [0.004, 0.004],
                'menu' => [['Menu', 'Hot turkey sandwich', 1200], ['Menu', 'Homemade soup', 600], ['Menu', "Shepherd's pie", 1300], ['Menu', 'Butter tart', 350]],
            ],
            [
                'slug' => 'tony-vs-pizza', 'name' => "Tony V's Pizza", 'community' => 'Massey',
                'cuisine' => 'Pizza & burgers', 'description' => 'Pizza and burgers in Massey.',
                'partner' => false, 'open' => false, 'phone' => '', 'alias' => 'tony-vs-pizza', 'jitter' => [-0.005, 0.003],
                'menu' => [['Menu', 'Pepperoni pizza', 1600], ['Menu', 'Cheeseburger', 900], ['Menu', 'Garlic bread', 500]],
            ],
            [
                'slug' => 'cortina-restaurant', 'name' => 'Cortina Restaurant', 'community' => 'Espanola',
                'cuisine' => 'Italian & pizza', 'description' => 'Long-running family Italian + pizza.',
                'partner' => false, 'open' => true, 'phone' => '', 'alias' => 'cortina-restaurant', 'jitter' => [0.003, -0.004],
                'menu' => [['Menu', 'Spaghetti & meatballs', 1500], ['Menu', 'Margherita pizza', 1500], ['Menu', 'Caesar salad', 800]],
            ],
            [
                'slug' => 'toppers-pizza', 'name' => "Topper's Pizza", 'community' => 'Espanola',
                'cuisine' => 'Pizza', 'description' => 'The uptown pizza go-to.',
                'partner' => false, 'open' => true, 'phone' => '', 'alias' => 'toppers-pizza', 'jitter' => [-0.003, -0.002],
                'menu' => [['Menu', 'Large pepperoni', 1900], ['Menu', 'Chicken wings', 1300], ['Menu', 'Cheese sticks', 800]],
            ],
            [
                'slug' => 'deluxe-drive-in', 'name' => 'Deluxe Drive-In', 'community' => 'Espanola',
                'cuisine' => 'Burgers & fries', 'description' => 'Classic fast food, burgers and fries.',
                'partner' => false, 'open' => false, 'phone' => '', 'alias' => 'deluxe-drive-in', 'jitter' => [0.005, 0.002],
                'menu' => [['Menu', 'Burger & fries', 900], ['Menu', 'Onion rings', 500], ['Menu', 'Milkshake', 450]],
            ],
            [
                'slug' => 'north-channel-pizza', 'name' => 'North Channel Pizza', 'community' => 'Spanish',
                'cuisine' => 'Homemade pizza, takeout', 'description' => 'Fresh-daily dough, takeout pizza, wings, lasagna.',
                'partner' => false, 'open' => true, 'phone' => '', 'alias' => 'north-channel-pizza', 'jitter' => [0.003, 0.004],
                'menu' => [['Menu', 'Specialty pizza', 1800], ['Menu', 'Lasagna', 1400], ['Menu', 'Wings', 1300]],
            ],
            [
                'slug' => 'dixie-lee-chicken', 'name' => 'Dixie Lee Chicken', 'community' => 'Spanish',
                'cuisine' => 'Fried chicken', 'description' => 'Fried chicken and Canadian diner fare.',
                'partner' => false, 'open' => true, 'phone' => '', 'alias' => 'dixie-lee-chicken', 'jitter' => [-0.004, 0.003],
                'menu' => [['Menu', '2pc chicken dinner', 1200], ['Menu', 'Popcorn chicken', 900], ['Menu', 'Coleslaw', 300]],
            ],
        ];
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
