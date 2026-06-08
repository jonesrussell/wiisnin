<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Entity\MenuItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Vendor;
use App\Notification\OrderPlacedNotification;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Notification\NotificationDispatcher;

/**
 * The order-placement flow: validate the draft against the vendor and its menu,
 * snapshot prices into line items, compute totals, persist the order, and notify
 * the vendor through the notification package (Mercure + mail channels).
 *
 * Wiisnin is order-taking only — no payment is captured here; payment_method is
 * recorded for the vendor to collect offline (cash / e-transfer).
 */
final class OrderService
{
    /** @var \Closure(): int */
    private \Closure $clock;

    public function __construct(
        private readonly EntityRepositoryInterface $orders,
        private readonly EntityRepositoryInterface $orderItems,
        private readonly EntityRepositoryInterface $menuItems,
        private readonly EntityRepositoryInterface $vendors,
        private readonly NotificationDispatcher $notifier,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function place(OrderDraft $draft): Order
    {
        $vendor = $this->vendors->find((string) $draft->vendorId);
        if (!$vendor instanceof Vendor) {
            throw new \DomainException('Unknown vendor.');
        }
        if (!$vendor->isOpen()) {
            throw new \DomainException('This vendor is not accepting orders right now.');
        }
        if ($draft->lines === []) {
            throw new \DomainException('An order must contain at least one item.');
        }

        $now = ($this->clock)();

        $order = new Order([
            'customer_uid' => $draft->customerUid,
            'vendor_id' => $draft->vendorId,
            'status' => OrderWorkflow::PLACED,
            'fulfilment' => $draft->fulfilment,
            'address' => $draft->address,
            'community_tid' => $draft->communityTid,
            'contact_phone' => $draft->contactPhone,
            'payment_method' => $draft->paymentMethod,
            'notes' => $draft->notes,
            'subtotal_cents' => 0,
            'total_cents' => 0,
            'placed_at' => $now,
            'updated_at' => $now,
        ]);
        $this->orders->save($order);

        $orderId = (int) $order->id();
        $order->set('reference', sprintf('WSN-%06d', $orderId));

        $subtotal = 0;
        foreach ($draft->lines as $line) {
            $menuItem = $this->menuItems->find((string) $line->menuItemId);
            if (!$menuItem instanceof MenuItem) {
                throw new \DomainException(sprintf('Unknown menu item %d.', $line->menuItemId));
            }
            if ($menuItem->getVendorId() !== $draft->vendorId) {
                throw new \DomainException('A menu item does not belong to this vendor.');
            }
            if (!$menuItem->isAvailable()) {
                throw new \DomainException(sprintf('"%s" is currently unavailable.', $menuItem->getName()));
            }

            $quantity = max(1, $line->quantity);
            $unitPrice = $menuItem->getPriceCents();
            $lineTotal = $unitPrice * $quantity;
            $subtotal += $lineTotal;

            $this->orderItems->save(new OrderItem([
                'order_id' => $orderId,
                'menu_item_id' => $line->menuItemId,
                'name_snapshot' => $menuItem->getName(),
                'quantity' => $quantity,
                'unit_price_cents' => $unitPrice,
                'line_total_cents' => $lineTotal,
                'line_note' => $line->note,
            ]));
        }

        // MVP: total == subtotal. No tax, delivery fee, or payment processing.
        $order->set('subtotal_cents', $subtotal);
        $order->set('total_cents', $subtotal);
        $this->orders->save($order);

        // Notify the vendor in real time (Mercure) and by email.
        $this->notifier->send($vendor, new OrderPlacedNotification($order, $vendor));

        return $order;
    }
}
