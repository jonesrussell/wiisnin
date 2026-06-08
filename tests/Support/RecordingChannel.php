<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Waaseyaa\Notification\ChannelInterface;
use Waaseyaa\Notification\NotifiableInterface;
use Waaseyaa\Notification\NotificationInterface;

/**
 * A notification channel that records what it was asked to send, for assertions.
 */
final class RecordingChannel implements ChannelInterface
{
    /** @var list<array{notifiable: NotifiableInterface, notification: NotificationInterface}> */
    public array $sent = [];

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $this->sent[] = ['notifiable' => $notifiable, 'notification' => $notification];
    }
}
