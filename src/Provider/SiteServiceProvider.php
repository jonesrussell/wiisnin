<?php

declare(strict_types=1);

namespace App\Provider;

use App\Controller\CommunityController;
use App\Controller\LandingController;
use App\Controller\OrderController;
use App\Controller\VendorController;
use App\Controller\VendorInboxController;
use App\Domain\Catalog\Catalog;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderWorkflowService;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mercure\MercurePublisher;
use Waaseyaa\Notification\NotificationDispatcher;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Wiisnin's public site + demo routes.
 *
 * Controllers are built lazily (per request) via the factory methods below so
 * route *registration* never dereferences the kernel — route-match tests can
 * call routes() with a null EntityTypeManager.
 */
final class SiteServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function routes(
        WaaseyaaRouter $router,
        ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null,
    ): void {
        // Customer storefront.
        $router->addRoute('home', RouteBuilder::create('/')
            ->controller(fn () => new LandingController()->index())
            ->allowAll()->methods('GET')->build());

        $router->addRoute('community.show', RouteBuilder::create('/c/{slug}')
            ->controller(fn (Request $r, string $slug) => $this->communityController()->show($slug))
            ->allowAll()->methods('GET')->build());

        $router->addRoute('order.place', RouteBuilder::create('/order')
            ->controller(fn (Request $r) => $this->orderController()->place($r))
            ->allowAll()->methods('POST')->build());

        $router->addRoute('order.show', RouteBuilder::create('/order/{reference}')
            ->controller(fn (Request $r, string $reference) => $this->orderController()->show($reference))
            ->allowAll()->methods('GET')->build());

        // Vendor inbox (passphrase-gated). Register specific paths before the
        // catch-all /vendor/{slug} so they win.
        $router->addRoute('vendor.inbox', RouteBuilder::create('/vendor')
            ->controller(fn (Request $r) => $this->vendorInboxController()->index($r))
            ->allowAll()->methods('GET')->priority(10)->build());

        $router->addRoute('vendor.login', RouteBuilder::create('/vendor/login')
            ->controller(fn (Request $r) => $this->vendorInboxController()->login($r))
            ->allowAll()->methods('POST')->priority(10)->build());

        $router->addRoute('vendor.transition', RouteBuilder::create('/vendor/orders/{id}/transition')
            ->controller(fn (Request $r, string $id) => $this->vendorInboxController()->transition($r, $id))
            ->allowAll()->methods('POST')->priority(10)->build());

        $router->addRoute('vendor.orders', RouteBuilder::create('/api/vendor/{vid}/orders')
            ->controller(fn (Request $r, string $vid) => $this->vendorInboxController()->ordersJson($r, $vid))
            ->allowAll()->methods('GET')->build());

        $router->addRoute('vendor.show', RouteBuilder::create('/vendor/{slug}')
            ->controller(fn (Request $r, string $slug) => $this->vendorController()->show($slug))
            ->allowAll()->methods('GET')->build());
    }

    // --- lazy controller factories (request time) ----------------------------

    private function catalog(): Catalog
    {
        $etm = $this->entityTypeManager();

        return new Catalog(
            $etm->getRepository('vendor'),
            $etm->getRepository('menu_item'),
            $etm->getRepository('taxonomy_term'),
        );
    }

    private function communityController(): CommunityController
    {
        return new CommunityController($this->catalog());
    }

    private function vendorController(): VendorController
    {
        return new VendorController($this->catalog());
    }

    private function orderController(): OrderController
    {
        $etm = $this->entityTypeManager();
        $dispatcher = $this->resolve(NotificationDispatcher::class);
        \assert($dispatcher instanceof NotificationDispatcher);

        $orderService = new OrderService(
            $etm->getRepository('order'),
            $etm->getRepository('order_item'),
            $etm->getRepository('menu_item'),
            $etm->getRepository('vendor'),
            $dispatcher,
        );

        return new OrderController(
            $orderService,
            $etm->getRepository('order'),
            $etm->getRepository('order_item'),
        );
    }

    private function vendorInboxController(): VendorInboxController
    {
        $etm = $this->entityTypeManager();
        $mercure = $this->resolve(MercurePublisher::class);
        \assert($mercure instanceof MercurePublisher);

        $passphrase = (string) ($this->config['wiisnin']['vendor_passphrase'] ?? 'meedjims');
        $publicUrl = (string) ($this->config['mercure']['public_url'] ?? '');
        $secret = (string) ($this->config['mercure']['jwt_secret'] ?? (getenv('WAASEYAA_JWT_SECRET') ?: 'wiisnin'));

        return new VendorInboxController(
            $this->catalog(),
            $etm->getRepository('order'),
            $etm->getRepository('order_item'),
            new OrderWorkflowService(),
            $mercure,
            $passphrase,
            $publicUrl,
            $secret,
        );
    }

    private function entityTypeManager(): EntityTypeManager
    {
        $etm = $this->resolve(EntityTypeManager::class);
        \assert($etm instanceof EntityTypeManager);

        return $etm;
    }
}
