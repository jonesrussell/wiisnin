<?php

declare(strict_types=1);

namespace App\Seed;

use App\Access\CommerceAccess;
use App\Entity\GroupMembership;
use App\Entity\MenuItem;
use App\Entity\Vendor;
use App\Support\Communities;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Idempotent seed of realistic demo data:
 *   - the four community taxonomy terms,
 *   - the menu_category taxonomy terms (Meedjims' real categories),
 *   - the pilot vendor Meedjims Foodland (Sagamok), its group + an owner
 *     membership, and its known menu.
 *
 * PRICES ARE PLACEHOLDERS. The family's real prices are unknown — every menu
 * item is flagged and carries price_cents = 0. TODO: confirm prices with
 * Meedjims Foodland (705-865-1537). Do not treat these as a real price list.
 */
final class WiisninSeeder
{
    private const string VENDOR_SLUG = 'meedjims-foodland';
    private const string PRICE_TODO = 'PLACEHOLDER — confirm price with Meedjims Foodland (705-865-1537).';

    public function __construct(
        private readonly EntityRepositoryInterface $terms,
        private readonly EntityRepositoryInterface $vendors,
        private readonly EntityRepositoryInterface $menuItems,
        private readonly EntityRepositoryInterface $groups,
        private readonly EntityRepositoryInterface $memberships,
    ) {}

    public function run(CliIO $io): int
    {
        // 1. Community terms.
        $communityTids = [];
        foreach (Communities::NAMES as $name) {
            $communityTids[$name] = $this->ensureTerm('community', $name);
        }
        $io->writeln('Communities: ' . implode(', ', array_keys($communityTids)));

        // 2. Menu category terms (Meedjims' actual categories).
        $categories = ['Native cuisine', 'Grill', 'Daily specials'];
        $categoryTids = [];
        foreach ($categories as $name) {
            $categoryTids[$name] = $this->ensureTerm('menu_category', $name);
        }
        $io->writeln('Menu categories: ' . implode(', ', array_keys($categoryTids)));

        // 3. The pilot vendor (idempotent by slug).
        $existing = $this->vendors->findBy(['slug' => self::VENDOR_SLUG], null, 1);
        if ($existing !== []) {
            $io->writeln('Meedjims Foodland already seeded; nothing to do.');
            return 0;
        }

        // 3a. A group for the vendor's staff, and an owner membership.
        $groupId = $this->ensureVendorGroup($io);
        if ($groupId !== null) {
            $this->memberships->save(new GroupMembership([
                'group_id' => $groupId,
                'user_id' => 1, // placeholder owner (admin). TODO: real staff accounts.
                'role' => 'owner',
            ]));
        }

        $vendor = new Vendor([
            'name' => 'Meedjims Foodland',
            'slug' => self::VENDOR_SLUG,
            'community_tid' => $communityTids['Sagamok'] ?? null,
            'description' => 'Family-owned restaurant in Sagamok First Nation, recently reopened.',
            'hours' => 'Hours TBD — confirm with the family.',
            'is_open' => 1,
            'owner_group_id' => $groupId,
            'contact_phone' => '705-865-1537',
            'contact_email' => '',
        ]);
        $this->vendors->save($vendor);
        $vendorId = (int) $vendor->id();
        $io->writeln("Seeded vendor 'Meedjims Foodland' (id={$vendorId}, group=" . var_export($groupId, true) . ').');

        // 4. The known menu. Prices are placeholders (0); see PRICE_TODO.
        $menu = [
            'Native cuisine' => ['Scone', 'Indian taco', 'Scone dog', 'Scone & bologna'],
            'Grill' => ['Hamburger', 'French fries', 'Poutine'],
            'Daily specials' => ['Soup', 'Sandwich'],
        ];
        $count = 0;
        foreach ($menu as $category => $items) {
            foreach ($items as $name) {
                $this->menuItems->save(new MenuItem([
                    'vendor_id' => $vendorId,
                    'category_tid' => $categoryTids[$category] ?? null,
                    'name' => $name,
                    'description' => self::PRICE_TODO,
                    'price_cents' => 0, // PLACEHOLDER — see PRICE_TODO.
                    'available' => 1,
                ]));
                $count++;
            }
        }
        $io->writeln("Seeded {$count} menu items (all prices are placeholders — confirm with the family).");
        $io->writeln('Roles available: ' . implode(', ', array_keys(CommerceAccess::roles())));

        return 0;
    }

    /**
     * Find-or-create a taxonomy term in a vocabulary; returns its tid.
     */
    private function ensureTerm(string $vid, string $name): int
    {
        $existing = $this->terms->findBy(['vid' => $vid, 'name' => $name], null, 1);
        if ($existing !== []) {
            return (int) $existing[0]->id();
        }

        $term = new \Waaseyaa\Taxonomy\Term(['vid' => $vid, 'name' => $name, 'status' => true]);
        $this->terms->save($term);

        return (int) $term->id();
    }

    /**
     * Create the vendor's group via the groups package. Returns the group id, or
     * null if the groups backend rejects the bundle (the seed still proceeds —
     * see WAASEYAA-FRICTION.md).
     */
    private function ensureVendorGroup(CliIO $io): ?int
    {
        try {
            $group = new \Waaseyaa\Groups\Group(['type' => 'vendor', 'name' => 'Meedjims Foodland']);
            $this->groups->save($group);

            return (int) $group->id();
        } catch (\Throwable $e) {
            $io->writeln('NOTE: could not create vendor group (' . $e->getMessage() . '); continuing without group scoping.');

            return null;
        }
    }
}
