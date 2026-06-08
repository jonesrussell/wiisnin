<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Provider\SiteServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * The storefront + vendor-inbox GET routes are registered (path → route name).
 */
final class StorefrontRoutesTest extends TestCase
{
    private function router(): WaaseyaaRouter
    {
        $router = new WaaseyaaRouter();
        new SiteServiceProvider()->routes($router);
        return $router;
    }

    #[Test]
    public function customer_routes_are_registered(): void
    {
        $router = $this->router();
        $this->assertSame('home', $router->match('/')['_route'] ?? null);
        $this->assertSame('community.show', $router->match('/c/sagamok')['_route'] ?? null);
        $this->assertSame('vendor.show', $router->match('/vendor/meedjims-foodland')['_route'] ?? null);
        $this->assertSame('order.show', $router->match('/order/WSN-000001')['_route'] ?? null);
    }

    #[Test]
    public function vendor_inbox_routes_are_registered(): void
    {
        $router = $this->router();
        $this->assertSame('vendor.inbox', $router->match('/vendor')['_route'] ?? null);
        $this->assertSame('vendor.orders', $router->match('/api/vendor/1/orders')['_route'] ?? null);
    }
}
