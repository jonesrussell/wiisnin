<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The VERIFIED vendor dataset (AREA-VENDORS-VERIFIED.md, 2026-06-09), cross-checked
 * against the Township of Sables-Spanish Rivers dining directory + business listings.
 *
 * Ordering is live for **Meedjims only** (the one partner). Everyone else is a
 * directory/info listing (name, town, cuisine, phone, address, hours) with an
 * "ordering coming soon / claim this listing" CTA — no menu, no ordering.
 *
 * EXCLUDED on purpose (flagged unconfirmed/closed in the verified file): Back Home
 * Bistro, Tony V's Pizza, Deluxe Drive-In. Jones General Store is excluded too —
 * it's a convenience store, not an eatery.
 *
 * Hours: only the two listings the file actually gives hours for carry hours_json
 * (so open/closed can be computed). Everyone else has empty hours — we never invent
 * hours or fake "Open now". Coordinates are town centroids (Communities::CENTROIDS)
 * plus a per-vendor jitter so pins don't overlap; refine with real geocoding later.
 *
 * Kept as plain data (separate from the seeder) so seed counts/exclusions are unit
 * testable without booting the kernel.
 */
final class VendorData
{
    /**
     * @return list<array{
     *   slug:string, name:string, community:string, cuisine:string, description:string,
     *   partner:bool, phone:string, address:string, hours:string, hours_json:string,
     *   jitter:array{0:float,1:float}, menu:list<array{0:string,1:string,2:int}>
     * }>
     */
    public static function vendors(): array
    {
        return [
            // --- Sagamok (home) — the one live partner ---------------------------
            [
                'slug' => 'meedjims-foodland', 'name' => 'Meedjims Foodland', 'community' => 'Sagamok',
                'cuisine' => 'Native cuisine & grill',
                'description' => 'Family-owned in Sagamok First Nation, recently reopened. The one live Wiisnin ordering partner.',
                'partner' => true, 'phone' => '705-865-1537', 'address' => '', 'hours' => '', 'hours_json' => '',
                'jitter' => [0.0, 0.0],
                'menu' => [
                    ['Native cuisine', 'Scone', 400], ['Native cuisine', 'Indian taco', 1400],
                    ['Native cuisine', 'Scone dog', 900], ['Native cuisine', 'Scone & bologna', 800],
                    ['Grill', 'Hamburger', 800], ['Grill', 'French fries', 500],
                    ['Grill', 'Poutine', 1000], ['Grill', 'Cheese fries', 900],
                    ['Daily specials', 'Corn soup', 800],
                ],
            ],

            // --- Massey ----------------------------------------------------------
            [
                'slug' => 'little-brew-cafe', 'name' => 'Little Brew Cafe', 'community' => 'Massey',
                'cuisine' => 'Cafe', 'description' => 'Massey cafe. Township-verified listing.',
                'partner' => false, 'phone' => '705-582-2070', 'address' => '365 Imperial St S, Massey',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.004, 0.004], 'menu' => [],
            ],
            [
                'slug' => 'poiriers-confectionery', 'name' => "Poirier's Confectionery & Pizza", 'community' => 'Massey',
                'cuisine' => 'Pizza & confectionery', 'description' => 'Pizza and confectionery in Massey. Township-verified.',
                'partner' => false, 'phone' => '705-865-2740', 'address' => '355 Imperial St S, Massey',
                'hours' => '', 'hours_json' => '', 'jitter' => [-0.004, 0.004], 'menu' => [],
            ],
            [
                'slug' => 'wing-house', 'name' => 'Wing House', 'community' => 'Massey',
                'cuisine' => 'Wings & comfort food', 'description' => 'Wings and comfort food (winghouse.ca). Township-verified.',
                'partner' => false, 'phone' => '705-582-2004', 'address' => '340 Sable St E, Massey',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.004, -0.004], 'menu' => [],
            ],
            [
                'slug' => 'chutes-confectionery', 'name' => 'Chutes Confectionery', 'community' => 'Massey',
                'cuisine' => 'Confectionery (seasonal)', 'description' => 'Seasonal confectionery in Massey. Township-listed; confirm in-season.',
                'partner' => false, 'phone' => '705-865-2586', 'address' => '595 Imperial St N, Massey',
                'hours' => '', 'hours_json' => '', 'jitter' => [-0.004, -0.004], 'menu' => [],
            ],

            // --- Walford ---------------------------------------------------------
            [
                'slug' => 'luckys-homestyle', 'name' => "Lucky's Homestyle Restaurant & Motel", 'community' => 'Walford',
                'cuisine' => 'Homestyle diner', 'description' => 'Homestyle diner + motel on Highway 17 in Walford. Township-verified.',
                'partner' => false, 'phone' => '705-844-1842', 'address' => '403 Highway 17, Walford',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.0, 0.0], 'menu' => [],
            ],

            // --- Spanish / Cutler ------------------------------------------------
            [
                'slug' => 'north-channel-pizza', 'name' => 'North Channel Pizza', 'community' => 'Spanish',
                'cuisine' => 'Pizza, fried chicken & munchie boxes',
                'description' => 'Takeout pizza, Broaster fried chicken, munchie boxes. Already delivers to Spanish, Cutler, Walford & Serpent River.',
                'partner' => false, 'phone' => '705-844-2222', 'address' => '101 Front St, Spanish',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.003, 0.003], 'menu' => [],
            ],
            [
                'slug' => 'dixie-lee-chicken', 'name' => 'Dixie Lee Chicken', 'community' => 'Spanish',
                'cuisine' => 'Fried chicken, fish & breakfast', 'description' => 'Fried chicken, fish, all-day breakfast and homemade pizza in Spanish.',
                'partner' => false, 'phone' => '', 'address' => 'Spanish',
                'hours' => '', 'hours_json' => '', 'jitter' => [-0.003, -0.003], 'menu' => [],
            ],

            // --- Webbwood --------------------------------------------------------
            [
                'slug' => 'j-and-b-fish-and-chips', 'name' => "J & B's Fish & Chips", 'community' => 'Webbwood',
                'cuisine' => 'Fish & chips, poutine', 'description' => 'Beloved local chip stand off Hwy 17: fries, poutine, hot dinners. Confirm seasonal hours.',
                'partner' => false, 'phone' => '', 'address' => 'Webbwood',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.0, 0.0], 'menu' => [],
            ],

            // --- Espanola (biggest cluster) -------------------------------------
            [
                'slug' => 'tim-hortons-espanola', 'name' => 'Tim Hortons', 'community' => 'Espanola',
                'cuisine' => 'Coffee & quick bites', 'description' => 'Espanola Tim Hortons on Centre St.',
                'partner' => false, 'phone' => '', 'address' => '701 Centre St, Espanola',
                'hours' => 'Daily 5:30am–11pm',
                'hours_json' => '{"mon":[["05:30","23:00"]],"tue":[["05:30","23:00"]],"wed":[["05:30","23:00"]],"thu":[["05:30","23:00"]],"fri":[["05:30","23:00"]],"sat":[["05:30","23:00"]],"sun":[["05:30","23:00"]]}',
                'jitter' => [0.0, 0.0], 'menu' => [],
            ],
            [
                'slug' => 'pizza-hut-espanola', 'name' => 'Pizza Hut', 'community' => 'Espanola',
                'cuisine' => 'Pizza', 'description' => 'Dine-in, delivery and online ordering in Espanola.',
                'partner' => false, 'phone' => '', 'address' => 'Espanola',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.005, 0.005], 'menu' => [],
            ],
            [
                'slug' => 'sukhdev-restaurant', 'name' => 'Sukhdev Restaurant & Inn', 'community' => 'Espanola',
                'cuisine' => 'Indian', 'description' => 'Indian restaurant & inn on Centre St.',
                'partner' => false, 'phone' => '', 'address' => '585 Centre St, Espanola',
                'hours' => '', 'hours_json' => '', 'jitter' => [-0.005, 0.005], 'menu' => [],
            ],
            [
                'slug' => 'cortina-restaurant', 'name' => 'Cortina Restaurant', 'community' => 'Espanola',
                'cuisine' => 'Italian, pizza & Canadian', 'description' => 'Italian, pizza and Canadian fare on Centre St.',
                'partner' => false, 'phone' => '', 'address' => '3-383 Centre St, Espanola',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.005, -0.005], 'menu' => [],
            ],
            [
                'slug' => 'toppers-pizza', 'name' => "Topper's Pizza", 'community' => 'Espanola',
                'cuisine' => 'Pizza', 'description' => 'Pizza on Centre St, Espanola.',
                'partner' => false, 'phone' => '(866) 454-6644', 'address' => '88 Centre St, Espanola',
                'hours' => '', 'hours_json' => '', 'jitter' => [-0.005, -0.005], 'menu' => [],
            ],
            [
                'slug' => 'roger-rabbits', 'name' => "Roger Rabbit's Restaurant", 'community' => 'Espanola',
                'cuisine' => 'Breakfast & diner', 'description' => 'Breakfast and diner fare on Park St.',
                'partner' => false, 'phone' => '705-869-3418', 'address' => '1-92 Park St, Espanola',
                'hours' => 'Mon–Sat 6am–2pm · Sun 7am–2pm',
                'hours_json' => '{"mon":[["06:00","14:00"]],"tue":[["06:00","14:00"]],"wed":[["06:00","14:00"]],"thu":[["06:00","14:00"]],"fri":[["06:00","14:00"]],"sat":[["06:00","14:00"]],"sun":[["07:00","14:00"]]}',
                'jitter' => [0.008, 0.0], 'menu' => [],
            ],
            [
                'slug' => 'golden-dragon', 'name' => 'Golden Dragon Chinese Restaurant & Take-Out', 'community' => 'Espanola',
                'cuisine' => 'Chinese takeout', 'description' => 'Chinese restaurant and takeout on Centre St.',
                'partner' => false, 'phone' => '705-869-4477', 'address' => '322 Centre St, Espanola',
                'hours' => '', 'hours_json' => '', 'jitter' => [-0.008, 0.0], 'menu' => [],
            ],
            [
                'slug' => 'hong-kong-restaurant', 'name' => 'Hong Kong Restaurant', 'community' => 'Espanola',
                'cuisine' => 'Canadian-Chinese', 'description' => 'Canadian-Chinese, takeout and dine-in in Espanola.',
                'partner' => false, 'phone' => '', 'address' => 'Espanola',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.0, 0.008], 'menu' => [],
            ],
            [
                'slug' => 'dfr-sports-bar', 'name' => 'DFR Sports Bar & Eatery', 'community' => 'Espanola',
                'cuisine' => 'Pub & eatery', 'description' => 'Sports bar and eatery in Espanola.',
                'partner' => false, 'phone' => '705-583-3663', 'address' => 'Espanola',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.0, -0.008], 'menu' => [],
            ],

            // --- McKerrow --------------------------------------------------------
            [
                'slug' => 'wendys-mckerrow', 'name' => "Wendy's", 'community' => 'McKerrow',
                'cuisine' => 'Fast food', 'description' => 'Fast food with drive-thru on Highway 17, open late.',
                'partner' => false, 'phone' => '', 'address' => '331 Highway 17, McKerrow',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.003, 0.0], 'menu' => [],
            ],
            [
                'slug' => 'fried-and-toasted', 'name' => 'Fried And Toasted', 'community' => 'McKerrow',
                'cuisine' => 'Casual eats', 'description' => 'Casual eats in McKerrow.',
                'partner' => false, 'phone' => '', 'address' => 'McKerrow',
                'hours' => '', 'hours_json' => '', 'jitter' => [-0.003, 0.0], 'menu' => [],
            ],

            // --- Nairn Centre ----------------------------------------------------
            [
                'slug' => 'jeremys-country', 'name' => "Jeremy's Country Restaurant", 'community' => 'Nairn Centre',
                'cuisine' => 'Country restaurant', 'description' => 'Country restaurant in Nairn Centre.',
                'partner' => false, 'phone' => '', 'address' => 'Nairn Centre',
                'hours' => '', 'hours_json' => '', 'jitter' => [0.0, 0.0], 'menu' => [],
            ],
        ];
    }
}
