<?php

declare(strict_types=1);

namespace App\Domain\Order;

/**
 * One requested line in an order draft, before pricing/snapshotting.
 */
final readonly class OrderLineDraft
{
    public function __construct(
        public int $menuItemId,
        public int $quantity = 1,
        public string $note = '',
    ) {}
}
