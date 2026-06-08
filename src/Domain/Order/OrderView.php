<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Entity\Order;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Serializes an Order (+ its line items) to a plain array for Inertia / JSON.
 * Money stays in integer cents; the Vue layer formats and badges it as draft.
 */
final class OrderView
{
    /**
     * @return array<string, mixed>
     */
    public static function of(Order $order, EntityRepositoryInterface $orderItems): array
    {
        $items = [];
        foreach ($orderItems->findBy(['order_id' => (int) $order->id()]) as $line) {
            $items[] = [
                'name' => (string) $line->get('name_snapshot'),
                'quantity' => (int) $line->get('quantity'),
                'unit_price_cents' => (int) $line->get('unit_price_cents'),
                'line_total_cents' => (int) $line->get('line_total_cents'),
                'note' => (string) ($line->get('line_note') ?? ''),
            ];
        }

        return [
            'id' => (int) $order->id(),
            'reference' => $order->getReference(),
            'customer_name' => $order->getCustomerName(),
            'contact_phone' => (string) ($order->get('contact_phone') ?? ''),
            'fulfilment' => $order->getFulfilment(),
            'payment_method' => $order->getPaymentMethod(),
            'notes' => (string) ($order->get('notes') ?? ''),
            'status' => $order->getStatus(),
            'subtotal_cents' => $order->getSubtotalCents(),
            'total_cents' => $order->getTotalCents(),
            'placed_at' => (int) ($order->get('placed_at') ?? 0),
            'items' => $items,
        ];
    }
}
