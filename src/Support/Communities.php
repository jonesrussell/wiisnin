<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The four North Shore communities Wiisnin serves.
 *
 * This is the canonical list. Phase 1 mirrors these into a `community` taxonomy
 * vocabulary (see the taxonomy seed); keep the names here in sync with the terms
 * so the landing page and the vendor/order data agree.
 */
final class Communities
{
    /** @var list<string> Canonical community names, in display order. */
    public const NAMES = ['Massey', 'Sagamok', 'Espanola', 'Spanish'];

    public static function slug(string $name): string
    {
        return strtolower($name);
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
            'Massey' => 'Township on the Spanish River, gateway to the North Shore.',
            'Sagamok' => 'Sagamok Anishnawbek — home of our first vendor, Meedjims Foodland.',
            'Espanola' => 'The hub town serving the wider North Shore region.',
            'Spanish' => 'Historic community where the Spanish River meets the North Channel.',
        ];

        return array_map(
            static fn (string $name): array => [
                'name' => $name,
                'slug' => self::slug($name),
                'blurb' => $blurbs[$name],
            ],
            self::NAMES,
        );
    }
}
