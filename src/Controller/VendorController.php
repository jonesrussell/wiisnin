<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Catalog\Catalog;
use App\Support\AppMeta;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;

/**
 * A vendor's public page: its menu grouped by category, with draft pricing.
 */
final class VendorController
{
    public function __construct(
        private readonly Catalog $catalog,
    ) {}

    public function show(string $slug, string $locale = 'en'): InertiaResponse
    {
        $vendor = $this->catalog->vendorBySlug($slug);
        // Only the live partner has a menu + ordering + reviews; everyone else is
        // an info/directory listing (call, directions, hours, claim, demand).
        $isPartner = $vendor !== null && $vendor->isPartner();

        return Inertia::render('Vendor', [
            'app' => AppMeta::props(),
            'vendor' => $vendor !== null ? $this->catalog->vendorCard($vendor, null, $locale) : null,
            'menu' => $isPartner ? $this->catalog->menuForVendor((int) $vendor->id(), $locale) : [],
            'reviews' => $isPartner ? $this->catalog->reviewsFor((int) $vendor->id()) : [],
            // Every price shown for this vendor is draft until confirmed.
            'pricingDraft' => true,
        ]);
    }
}
