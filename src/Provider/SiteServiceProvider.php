<?php

declare(strict_types=1);

namespace App\Provider;

use App\Controller\LandingController;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Registers Wiisnin's public site routes.
 *
 * Routes are wired here (not in a config file) per the framework's convention:
 * a service provider's routes() method receives the WaaseyaaRouter and registers
 * named routes built with RouteBuilder.
 */
final class SiteServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function routes(
        WaaseyaaRouter $router,
        ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null,
    ): void {
        $landing = new LandingController();

        $router->addRoute(
            'home',
            RouteBuilder::create('/')
                ->controller(fn () => $landing->index())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }
}
