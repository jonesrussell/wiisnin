<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Entity\Order;
use Waaseyaa\Workflows\Workflow;
use Waaseyaa\Workflows\WorkflowTransition;

/**
 * Validates and applies Order status transitions against the workflow
 * definition. Authorization (who may transition) is enforced separately by the
 * access policies; this service only enforces the state machine itself.
 */
final class OrderWorkflowService
{
    private readonly Workflow $workflow;

    public function __construct(?Workflow $workflow = null)
    {
        $this->workflow = $workflow ?? OrderWorkflow::definition();
    }

    public function currentState(Order $order): string
    {
        return $order->getStatus();
    }

    /** @return array<string, WorkflowTransition> Transition id => transition. */
    public function availableTransitions(Order $order): array
    {
        return $this->workflow->getValidTransitions($order->getStatus());
    }

    public function canTransitionTo(Order $order, string $toState): bool
    {
        return $this->workflow->isTransitionAllowed($order->getStatus(), $toState);
    }

    /**
     * Move the order to a new state. The caller persists the order afterwards.
     *
     * @throws \DomainException if the transition is not allowed from the current state.
     */
    public function transitionTo(Order $order, string $toState, int $now): void
    {
        if (!$this->canTransitionTo($order, $toState)) {
            throw new \DomainException(sprintf(
                'Order cannot move from "%s" to "%s".',
                $order->getStatus(),
                $toState,
            ));
        }

        $order->setStatus($toState);
        $order->set('updated_at', $now);
    }
}
