<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Catalog\Catalog;
use App\Support\AppMeta;
use App\Support\Communities;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;

/**
 * Lists the vendors in a single community (e.g. /c/sagamok → Meedjims Foodland).
 */
final class CommunityController
{
    public function __construct(
        private readonly Catalog $catalog,
    ) {}

    public function show(string $slug): InertiaResponse
    {
        $name = Communities::nameFromSlug($slug);

        return Inertia::render('Community', [
            'app' => AppMeta::props(),
            'community' => [
                'name' => $name ?? ucfirst($slug),
                'slug' => $name !== null ? Communities::slug($name) : $slug,
            ],
            'vendors' => $name !== null ? $this->catalog->vendorsInCommunity($name) : [],
        ]);
    }
}
