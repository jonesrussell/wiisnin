<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Notification\Sms\SmsSenderInterface;
use Waaseyaa\Notification\ChannelInterface;
use Waaseyaa\Notification\NotifiableInterface;
use Waaseyaa\Notification\NotificationInterface;

/**
 * Future SMS channel (Twilio). STUB — intentionally inert this session.
 *
 * The channel interface and notification toSms() payload are in place so that
 * enabling SMS is a small, isolated change: provide an SmsSenderInterface
 * implementation, register this channel under the 'sms' name, and add 'sms' to
 * OrderPlacedNotification::via(). With no sender configured, send() is a no-op,
 * so listing 'sms' in via() is already safe. See WAASEYAA-FRICTION.md.
 */
final class SmsChannel implements ChannelInterface
{
    public function __construct(
        private readonly ?SmsSenderInterface $sender = null,
    ) {}

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        // TODO(SMS): not implemented this session. No sender => no-op.
        if ($this->sender === null) {
            return;
        }
        if (!method_exists($notification, 'toSms')) {
            return;
        }

        $phone = (string) ($notifiable->routeNotificationFor('sms') ?: '');
        if ($phone === '') {
            return;
        }

        $this->sender->send($phone, $notification->toSms($notifiable));
    }
}
