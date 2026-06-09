<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Order\OrderDraft;
use App\Domain\Order\OrderLineDraft;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderWorkflow;
use App\Domain\Order\OrderWorkflowService;
use App\Entity\MenuItem;
use App\Entity\Order;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use App\Tests\Support\NullQueue;
use App\Tests\Support\RecordingChannel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Notification\NotificationDispatcher;

/**
 * The order-placement flow: a customer places an order against a vendor, prices
 * are snapshotted into line items, totals are computed, the order starts in the
 * 'placed' workflow state, and the vendor is notified on the Mercure + mail
 * channels. Written before OrderService existed (test-first per house style).
 */
final class OrderPlacementTest extends TestCase
{
    private InMemoryEntityRepository $vendors;
    private InMemoryEntityRepository $menuItems;
    private InMemoryEntityRepository $orders;
    private InMemoryEntityRepository $orderItems;
    private RecordingChannel $mail;
    private RecordingChannel $mercure;

    private int $vendorId;
    private int $sconeId;
    private int $tacoId;

    protected function setUp(): void
    {
        $this->vendors = new InMemoryEntityRepository();
        $this->menuItems = new InMemoryEntityRepository();
        $this->orders = new InMemoryEntityRepository();
        $this->orderItems = new InMemoryEntityRepository();
        $this->mail = new RecordingChannel();
        $this->mercure = new RecordingChannel();

        $vendor = new Vendor([
            'name' => 'Partner Kitchen',
            'slug' => 'partner-kitchen',
            'is_open' => 1,
            'is_partner' => 1,
            'contact_email' => 'meedjims@example.test',
            'contact_phone' => '705-865-1537',
        ]);
        $this->vendors->save($vendor);
        $this->vendorId = (int) $vendor->id();

        $scone = new MenuItem(['vendor_id' => $this->vendorId, 'name' => 'Scone', 'price_cents' => 300, 'available' => 1]);
        $taco = new MenuItem(['vendor_id' => $this->vendorId, 'name' => 'Indian taco', 'price_cents' => 1200, 'available' => 1]);
        $this->menuItems->save($scone);
        $this->menuItems->save($taco);
        $this->sconeId = (int) $scone->id();
        $this->tacoId = (int) $taco->id();
    }

    private function service(): OrderService
    {
        $dispatcher = new NotificationDispatcher(
            new NullQueue(),
            ['mail' => $this->mail, 'mercure' => $this->mercure],
        );

        return new OrderService(
            $this->orders,
            $this->orderItems,
            $this->menuItems,
            $this->vendors,
            $dispatcher,
            static fn (): int => 1_700_000_000,
        );
    }

    private function draft(): OrderDraft
    {
        return new OrderDraft(
            customerUid: 42,
            vendorId: $this->vendorId,
            fulfilment: 'pickup',
            contactPhone: '705-555-0142',
            paymentMethod: 'etransfer',
            lines: [
                new OrderLineDraft(menuItemId: $this->sconeId, quantity: 2),
                new OrderLineDraft(menuItemId: $this->tacoId, quantity: 1, note: 'extra salsa'),
            ],
        );
    }

    #[Test]
    public function placing_an_order_snapshots_prices_and_computes_totals(): void
    {
        $order = $this->service()->place($this->draft());

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(OrderWorkflow::PLACED, $order->getStatus());
        $this->assertSame('WSN-000001', $order->getReference());
        // 2 × $3.00 + 1 × $12.00 = $18.00
        $this->assertSame(1800, $order->getSubtotalCents());
        $this->assertSame(1800, $order->getTotalCents());
        $this->assertSame(1_700_000_000, $order->get('placed_at'));

        $lines = $this->orderItems->findBy(['order_id' => (int) $order->id()]);
        $this->assertCount(2, $lines);

        // Line prices are snapshots taken from the menu at order time.
        $byName = [];
        foreach ($lines as $line) {
            $byName[$line->get('name_snapshot')] = $line;
        }
        $this->assertSame(600, $byName['Scone']->get('line_total_cents'));
        $this->assertSame(300, $byName['Scone']->get('unit_price_cents'));
        $this->assertSame('extra salsa', $byName['Indian taco']->get('line_note'));
    }

    #[Test]
    public function placing_an_order_notifies_the_vendor_on_mercure_and_mail(): void
    {
        $this->service()->place($this->draft());

        $this->assertCount(1, $this->mail->sent, 'vendor should get one mail notification');
        $this->assertCount(1, $this->mercure->sent, 'vendor should get one mercure notification');
        $this->assertSame(
            (string) $this->vendorId,
            $this->mail->sent[0]['notifiable']->getNotifiableId(),
        );
    }

    #[Test]
    public function a_sample_non_partner_vendor_rejects_orders(): void
    {
        $vendor = $this->vendors->find((string) $this->vendorId);
        $vendor->set('is_partner', 0); // sample listing
        $this->vendors->save($vendor);

        $this->expectException(\DomainException::class);
        $this->service()->place($this->draft());
    }

    #[Test]
    public function a_closed_vendor_rejects_orders(): void
    {
        $vendor = $this->vendors->find((string) $this->vendorId);
        $vendor->set('is_open', 0);
        $this->vendors->save($vendor);

        $this->expectException(\DomainException::class);
        $this->service()->place($this->draft());
    }

    #[Test]
    public function the_order_workflow_allows_placed_to_accepted_and_cancelled_but_not_completed(): void
    {
        $workflow = new OrderWorkflowService();
        $order = new Order(['status' => OrderWorkflow::PLACED]);

        $this->assertTrue($workflow->canTransitionTo($order, OrderWorkflow::ACCEPTED));
        $this->assertTrue($workflow->canTransitionTo($order, OrderWorkflow::CANCELLED));
        $this->assertFalse($workflow->canTransitionTo($order, OrderWorkflow::COMPLETED));

        $workflow->transitionTo($order, OrderWorkflow::ACCEPTED, 1_700_000_000);
        $this->assertSame(OrderWorkflow::ACCEPTED, $order->getStatus());

        // From accepted, cancel is still allowed; from completed nothing is.
        $this->assertTrue($workflow->canTransitionTo($order, OrderWorkflow::CANCELLED));
    }
}
