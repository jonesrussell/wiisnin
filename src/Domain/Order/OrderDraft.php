<?php

declare(strict_types=1);

namespace App\Domain\Order;

/**
 * A customer's requested order, as submitted, before validation and pricing.
 *
 * @phpstan-type Lines list<OrderLineDraft>
 */
final readonly class OrderDraft
{
    /**
     * @param list<OrderLineDraft> $lines
     */
    public function __construct(
        public int $customerUid,
        public int $vendorId,
        public string $fulfilment,
        public string $contactPhone,
        public string $paymentMethod,
        public array $lines,
        public string $address = '',
        public ?int $communityTid = null,
        public string $notes = '',
    ) {}
}
