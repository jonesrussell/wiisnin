<?php

declare(strict_types=1);

namespace App\Support;

/**
 * App identity shared into every Inertia page so the Vue shell can render it.
 */
final class AppMeta
{
    /**
     * @return array{name: string, tagline: string}
     */
    public static function props(): array
    {
        return [
            'name' => 'Wiisnin',
            'tagline' => 'Order food from your North Shore community.',
        ];
    }
}
