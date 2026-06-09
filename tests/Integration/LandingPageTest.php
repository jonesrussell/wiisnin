<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\LandingController;
use App\Provider\SiteServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Http\Inertia\InertiaPageResultInterface;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * The Wiisnin landing page renders an Inertia "Landing" component at `/` that
 * lists the four North Shore communities the app serves.
 *
 * This is the end-to-end proof that the Inertia rendering path works: the
 * controller returns an InertiaPageResultInterface (no Symfony Response built
 * by hand), and the ControllerDispatcher turns it into HTML or a JSON page
 * object depending on the X-Inertia header.
 */
final class LandingPageTest extends TestCase
{
    protected function setUp(): void
    {
        Inertia::reset();
    }

    #[Test]
    public function site_provider_registers_the_landing_route(): void
    {
        $router = new WaaseyaaRouter();
        (new SiteServiceProvider())->routes($router);

        $this->assertSame('home', $router->match('/')['_route'] ?? null);
    }

    #[Test]
    public function landing_renders_the_served_north_shore_communities(): void
    {
        $result = (new LandingController())->index();

        $this->assertInstanceOf(InertiaPageResultInterface::class, $result);

        $page = $result->toPageObject();
        $this->assertSame('Landing', $page['component']);

        $names = array_column($page['props']['communities'], 'name');
        $this->assertSame(
            ['Sagamok', 'Massey', 'Walford', 'Spanish', 'Webbwood', 'Espanola', 'McKerrow', 'Nairn Centre'],
            $names,
        );

        // App identity travels with every page so the Vue shell can render it.
        $this->assertSame('Wiisnin', $page['props']['app']['name'] ?? null);
    }
}
