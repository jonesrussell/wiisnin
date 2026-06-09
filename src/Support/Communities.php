<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The North Shore communities Wiisnin serves (verified pass, 2026-06-09).
 *
 * This is the canonical list. The seed mirrors these into a `community` taxonomy
 * vocabulary; keep the names here in sync with the terms so the landing page and
 * the vendor data agree. Centroids are APPROXIMATE town centres used for the
 * distance sort + a per-vendor jitter — refine with real geocoded coordinates
 * when available. Cutler is folded into Spanish.
 */
final class Communities
{
    /** @var list<string> Canonical community names, in display order (home first). */
    public const NAMES = ['Sagamok', 'Massey', 'Walford', 'Spanish', 'Webbwood', 'Espanola', 'McKerrow', 'Nairn Centre'];

    /**
     * Approximate community centroids [lat, lng] for the distance sort (geo).
     *
     * @var array<string, array{0: float, 1: float}>
     */
    public const CENTROIDS = [
        'Sagamok' => [46.21, -82.50],
        'Massey' => [46.21, -82.08],
        'Walford' => [46.18, -82.27],
        'Spanish' => [46.19, -82.34],
        'Webbwood' => [46.27, -81.89],
        'Espanola' => [46.25, -81.77],
        'McKerrow' => [46.27, -81.83],
        'Nairn Centre' => [46.34, -81.58],
    ];

    /** @return array{0: float, 1: float}|null */
    public static function centroid(string $name): ?array
    {
        return self::CENTROIDS[$name] ?? null;
    }

    public static function slug(string $name): string
    {
        return str_replace(' ', '-', strtolower($name));
    }

    /** Resolve a URL slug back to a canonical name, or null if unknown. */
    public static function nameFromSlug(string $slug): ?string
    {
        foreach (self::NAMES as $name) {
            if (self::slug($name) === strtolower($slug)) {
                return $name;
            }
        }
        return null;
    }

    /**
     * Landing-page cards: name plus a one-line blurb.
     *
     * @return list<array{name: string, blurb: string}>
     */
    public static function cards(): array
    {
        $blurbs = [
            'Sagamok' => 'Sagamok Anishnawbek — home of our first vendor, Meedjims Foodland.',
            'Massey' => 'Township on the Spanish River, gateway to the North Shore.',
            'Walford' => 'Small community west of Massey on Highway 17.',
            'Spanish' => 'Where the Spanish River meets the North Channel (incl. Cutler).',
            'Webbwood' => 'Village on Highway 17 east of Massey.',
            'Espanola' => 'The hub town serving the wider North Shore region.',
            'McKerrow' => 'Highway 17 stop between Espanola and Sudbury.',
            'Nairn Centre' => 'Township toward Sudbury on Highway 17.',
        ];

        return array_map(
            static fn (string $name): array => [
                'name' => $name,
                'slug' => self::slug($name),
                'blurb' => $blurbs[$name] ?? '',
            ],
            self::NAMES,
        );
    }
}
