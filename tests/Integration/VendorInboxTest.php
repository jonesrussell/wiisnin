<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\VendorInboxController;
use App\Domain\Catalog\Catalog;
use App\Domain\Order\OrderWorkflow;
use App\Domain\Order\OrderWorkflowService;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Mercure\MercurePublisher;

/**
 * The vendor inbox: passphrase-gated, lists the vendor's orders, and advances
 * order status through the workflow.
 */
final class VendorInboxTest extends TestCase
{
    private const string SECRET = 'test-secret';

    private InMemoryEntityRepository $vendors;
    private InMemoryEntityRepository $menuItems;
    private InMemoryEntityRepository $terms;
    private InMemoryEntityRepository $orders;
    private InMemoryEntityRepository $orderItems;
    private int $vendorId;
    private int $orderId;

    protected function setUp(): void
    {
        $this->vendors = new InMemoryEntityRepository();
        $this->menuItems = new InMemoryEntityRepository();
        $this->terms = new InMemoryEntityRepository();
        $this->orders = new InMemoryEntityRepository();
        $this->orderItems = new InMemoryEntityRepository();

        $vendor = new Vendor(['name' => 'Meedjims Foodland', 'slug' => 'meedjims-foodland', 'is_open' => 1]);
        $this->vendors->save($vendor);
        $this->vendorId = (int) $vendor->id();

        $order = new Order([
            'reference' => 'WSN-000001',
            'vendor_id' => $this->vendorId,
            'customer_uid' => 0,
            'customer_name' => 'Nokomis',
            'status' => OrderWorkflow::PLACED,
            'fulfilment' => 'pickup',
            'payment_method' => 'cash',
            'contact_phone' => '705-555-0143',
            'total_cents' => 800,
            'placed_at' => 1_700_000_000,
        ]);
        $this->orders->save($order);
        $this->orderId = (int) $order->id();
        $this->orderItems->save(new OrderItem([
            'order_id' => $this->orderId,
            'menu_item_id' => 1,
            'name_snapshot' => 'Scone',
            'quantity' => 2,
            'unit_price_cents' => 400,
            'line_total_cents' => 800,
        ]));
    }

    private function controller(): VendorInboxController
    {
        return new VendorInboxController(
            new Catalog($this->vendors, $this->menuItems, $this->terms),
            $this->orders,
            $this->orderItems,
            new OrderWorkflowService(),
            new MercurePublisher('', ''), // unconfigured: publish() no-ops
            'open-sesame',
            '',
            self::SECRET,
        );
    }

    private function token(): string
    {
        return hash_hmac('sha256', 'wiisnin-vendor-inbox', self::SECRET);
    }

    #[Test]
    public function inbox_requires_the_passphrase(): void
    {
        $page = $this->controller()->index(Request::create('/vendor', 'GET'))->toPageObject();
        $this->assertSame('VendorLogin', $page['component']);
    }

    #[Test]
    public function a_valid_cookie_shows_the_inbox_with_orders(): void
    {
        $request = Request::create('/vendor', 'GET', [], ['wsn_vendor' => $this->token()]);
        $page = $this->controller()->index($request)->toPageObject();

        $this->assertSame('VendorInbox', $page['component']);
        $this->assertCount(1, $page['props']['orders']);
        $this->assertSame('WSN-000001', $page['props']['orders'][0]['reference']);
        $this->assertSame('vendor/' . $this->vendorId . '/orders', $page['props']['mercure']['topic']);
    }

    #[Test]
    public function transition_advances_order_status(): void
    {
        $request = Request::create(
            "/vendor/orders/{$this->orderId}/transition",
            'POST',
            [],
            ['wsn_vendor' => $this->token()],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['to' => OrderWorkflow::ACCEPTED]),
        );

        $response = $this->controller()->transition($request, (string) $this->orderId);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['ok']);
        $this->assertSame(OrderWorkflow::ACCEPTED, $data['order']['status']);
    }

    #[Test]
    public function transition_is_blocked_without_the_passphrase(): void
    {
        $request = Request::create("/vendor/orders/{$this->orderId}/transition", 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['to' => 'accepted']));
        $response = $this->controller()->transition($request, (string) $this->orderId);
        $this->assertSame(401, $response->getStatusCode());
    }
}
