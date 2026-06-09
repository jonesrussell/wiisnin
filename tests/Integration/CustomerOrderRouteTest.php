<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\OrderController;
use App\Domain\Order\OrderService;
use App\Entity\MenuItem;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use App\Tests\Support\NullQueue;
use App\Tests\Support\RecordingChannel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Notification\NotificationDispatcher;

/**
 * The customer order route: a JSON checkout POST places an order through
 * OrderService and returns an Inertia confirmation carrying the WSN reference.
 */
final class CustomerOrderRouteTest extends TestCase
{
    private InMemoryEntityRepository $vendors;
    private InMemoryEntityRepository $menuItems;
    private InMemoryEntityRepository $orders;
    private InMemoryEntityRepository $orderItems;
    private int $vendorId;
    private int $sconeId;

    protected function setUp(): void
    {
        $this->vendors = new InMemoryEntityRepository();
        $this->menuItems = new InMemoryEntityRepository();
        $this->orders = new InMemoryEntityRepository();
        $this->orderItems = new InMemoryEntityRepository();

        $vendor = new Vendor(['name' => 'Partner Kitchen', 'slug' => 'partner-kitchen', 'is_open' => 1, 'is_partner' => 1]);
        $this->vendors->save($vendor);
        $this->vendorId = (int) $vendor->id();

        $scone = new MenuItem(['vendor_id' => $this->vendorId, 'name' => 'Scone', 'price_cents' => 400, 'available' => 1]);
        $this->menuItems->save($scone);
        $this->sconeId = (int) $scone->id();
    }

    private function controller(): OrderController
    {
        $dispatcher = new NotificationDispatcher(new NullQueue(), ['mail' => new RecordingChannel()]);
        $service = new OrderService($this->orders, $this->orderItems, $this->menuItems, $this->vendors, $dispatcher);

        return new OrderController($service, $this->orders, $this->orderItems);
    }

    #[Test]
    public function checkout_post_places_an_order_and_confirms_with_a_reference(): void
    {
        $payload = [
            'vendor_id' => $this->vendorId,
            'customer_name' => 'Nokomis',
            'contact_phone' => '705-555-0143',
            'fulfilment' => 'pickup',
            'payment_method' => 'cash',
            'lines' => [['menu_item_id' => $this->sconeId, 'quantity' => 2]],
        ];
        $request = Request::create('/order', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        $page = $this->controller()->place($request)->toPageObject();

        $this->assertSame('OrderConfirmation', $page['component']);
        $order = $page['props']['order'];
        $this->assertNotNull($order);
        $this->assertMatchesRegularExpression('/^WSN-\d{6}$/', $order['reference']);
        $this->assertSame('Nokomis', $order['customer_name']);
        $this->assertSame(800, $order['total_cents']); // 2 × $4.00
        $this->assertCount(1, $order['items']);
    }
}
