<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Money is stored and computed in integer cents; this formats it for display.
 */
final class Money
{
    public static function format(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
