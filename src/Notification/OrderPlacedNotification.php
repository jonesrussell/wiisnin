<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Order;
use App\Entity\Vendor;
use App\Support\Money;
use Waaseyaa\Mail\Envelope;
use Waaseyaa\Notification\NotifiableInterface;
use Waaseyaa\Notification\NotificationInterface;

/**
 * Sent to a vendor when a customer places a new order.
 *
 * Delivered over the Mercure channel (real-time SSE to the vendor's order
 * screen) and the mail channel. A toSms() payload is already defined so the
 * future SMS channel works the moment it's enabled (just add 'sms' to via()).
 */
final class OrderPlacedNotification implements NotificationInterface
{
    public function __construct(
        private readonly Order $order,
        private readonly Vendor $vendor,
    ) {}

    /**
     * @return list<string>
     */
    public function via(NotifiableInterface $notifiable): array
    {
        // TODO(SMS): add 'sms' here once App\Notification\Channel\SmsChannel is
        // wired to a Twilio-backed SmsSenderInterface.
        return ['mercure', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(NotifiableInterface $notifiable): array
    {
        return [
            'event' => 'order.placed',
            'order_id' => (string) $this->order->id(),
            'reference' => $this->order->getReference(),
            'vendor_id' => (string) ($this->order->getVendorId() ?? ''),
            'fulfilment' => $this->order->getFulfilment(),
            'total_cents' => $this->order->getTotalCents(),
            'placed_at' => (int) ($this->order->get('placed_at') ?? 0),
        ];
    }

    /**
     * Real-time payload pushed to the vendor's Mercure topic.
     *
     * @return array<string, mixed>
     */
    public function toMercure(NotifiableInterface $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    public function toMail(NotifiableInterface $notifiable): Envelope
    {
        $to = (string) ($notifiable->routeNotificationFor('mail') ?: '');
        $reference = $this->order->getReference();
        $total = Money::format($this->order->getTotalCents());

        $text = implode("\n", [
            sprintf('New order %s for %s.', $reference, $this->vendor->getName()),
            '',
            sprintf('Fulfilment: %s', $this->order->getFulfilment()),
            sprintf('Total: %s', $total),
            sprintf('Payment: %s (collected offline)', $this->order->getPaymentMethod()),
            sprintf('Customer phone: %s', (string) ($this->order->get('contact_phone') ?? '')),
        ]);

        return new Envelope(
            to: $to === '' ? [] : [$to],
            from: 'orders@wiisnin.app',
            subject: sprintf('New order %s', $reference),
            textBody: $text,
        );
    }

    public function toSms(NotifiableInterface $notifiable): string
    {
        return sprintf(
            'Wiisnin: new order %s (%s). Total %s.',
            $this->order->getReference(),
            $this->order->getFulfilment(),
            Money::format($this->order->getTotalCents()),
        );
    }
}
