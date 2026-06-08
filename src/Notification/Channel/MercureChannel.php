<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use Waaseyaa\Mercure\MercurePublisher;
use Waaseyaa\Notification\ChannelInterface;
use Waaseyaa\Notification\NotifiableInterface;
use Waaseyaa\Notification\NotificationInterface;

/**
 * Bridges the notification package to the mercure package: publishes a
 * notification's toMercure() payload to the notifiable's Mercure topic.
 *
 * The mercure package ships only a MercurePublisher (no notification channel),
 * so this adapter lives in the app. See WAASEYAA-FRICTION.md.
 */
final class MercureChannel implements ChannelInterface
{
    public function __construct(
        private readonly MercurePublisher $publisher,
    ) {}

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        if (!method_exists($notification, 'toMercure')) {
            return;
        }

        $topic = (string) ($notifiable->routeNotificationFor('mercure') ?: '');
        if ($topic === '') {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = $notification->toMercure($notifiable);
        $this->publisher->publish($topic, $payload);
    }
}
