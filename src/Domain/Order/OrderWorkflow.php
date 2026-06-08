<?php

declare(strict_types=1);

namespace App\Domain\Order;

use Waaseyaa\Workflows\Workflow;

/**
 * The Order status workflow, defined with the workflows package.
 *
 *   placed → accepted → preparing → ready → completed
 *   cancelled is reachable from placed or accepted.
 *
 * Wiisnin is order-taking only: there is no payment-capture or refund state.
 */
final class OrderWorkflow
{
    public const string PLACED = 'placed';
    public const string ACCEPTED = 'accepted';
    public const string PREPARING = 'preparing';
    public const string READY = 'ready';
    public const string COMPLETED = 'completed';
    public const string CANCELLED = 'cancelled';

    public const string ID = 'order';

    public static function definition(): Workflow
    {
        return new Workflow([
            'id' => self::ID,
            'label' => 'Order',
            'states' => [
                self::PLACED => ['label' => 'Placed', 'weight' => 0],
                self::ACCEPTED => ['label' => 'Accepted', 'weight' => 1],
                self::PREPARING => ['label' => 'Preparing', 'weight' => 2],
                self::READY => ['label' => 'Ready', 'weight' => 3],
                self::COMPLETED => ['label' => 'Completed', 'weight' => 4],
                self::CANCELLED => ['label' => 'Cancelled', 'weight' => 5],
            ],
            'transitions' => [
                'accept' => ['label' => 'Accept', 'from' => [self::PLACED], 'to' => self::ACCEPTED],
                'start' => ['label' => 'Start preparing', 'from' => [self::ACCEPTED], 'to' => self::PREPARING],
                'ready' => ['label' => 'Mark ready', 'from' => [self::PREPARING], 'to' => self::READY],
                'complete' => ['label' => 'Complete', 'from' => [self::READY], 'to' => self::COMPLETED],
                'cancel' => ['label' => 'Cancel', 'from' => [self::PLACED, self::ACCEPTED], 'to' => self::CANCELLED],
            ],
        ]);
    }
}
