<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Waaseyaa\Queue\QueueInterface;

/**
 * A no-op queue that records dispatched messages (used to construct a
 * NotificationDispatcher in tests without a real queue backend).
 */
final class NullQueue implements QueueInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $message): void
    {
        $this->dispatched[] = $message;
    }
}
