<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Order\OrderDraft;
use App\Domain\Order\OrderLineDraft;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderView;
use App\Entity\Order;
use App\Support\AppMeta;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;

/**
 * Places a guest order (the customer checkout) and shows the confirmation.
 */
final class OrderController
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly EntityRepositoryInterface $orderRepo,
        private readonly EntityRepositoryInterface $orderItemRepo,
    ) {}

    public function place(Request $request): InertiaResponse
    {
        $data = $this->payload($request);

        $lines = [];
        foreach ((array) ($data['lines'] ?? []) as $line) {
            $lines[] = new OrderLineDraft(
                menuItemId: (int) ($line['menu_item_id'] ?? 0),
                quantity: max(1, (int) ($line['quantity'] ?? 1)),
                note: (string) ($line['note'] ?? ''),
            );
        }

        $draft = new OrderDraft(
            customerUid: 0, // guest demo order
            vendorId: (int) ($data['vendor_id'] ?? 0),
            fulfilment: (string) ($data['fulfilment'] ?? 'pickup'),
            contactPhone: (string) ($data['contact_phone'] ?? ''),
            paymentMethod: (string) ($data['payment_method'] ?? 'cash'),
            lines: $lines,
            customerName: (string) ($data['customer_name'] ?? ''),
            address: (string) ($data['address'] ?? ''),
            notes: (string) ($data['notes'] ?? ''),
        );

        try {
            $order = $this->orders->place($draft);
        } catch (\DomainException $e) {
            return Inertia::render('OrderConfirmation', [
                'app' => AppMeta::props(),
                'order' => null,
                'error' => $e->getMessage(),
            ]);
        }

        return Inertia::render('OrderConfirmation', [
            'app' => AppMeta::props(),
            'order' => OrderView::of($order, $this->orderItemRepo),
            'error' => null,
        ]);
    }

    public function show(string $reference): InertiaResponse
    {
        $rows = $this->orderRepo->findBy(['reference' => $reference], null, 1);
        $order = $rows[0] ?? null;

        return Inertia::render('OrderConfirmation', [
            'app' => AppMeta::props(),
            'order' => $order instanceof Order ? OrderView::of($order, $this->orderItemRepo) : null,
            'error' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $raw = $request->getContent();
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->request->all();
    }
}
