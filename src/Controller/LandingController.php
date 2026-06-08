<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\Communities;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;

/**
 * The public landing page.
 *
 * Returns an Inertia page result rather than a Symfony Response: the framework's
 * ControllerDispatcher renders the root HTML template (initial load) or a JSON
 * page object (X-Inertia XHR) from this single return value.
 */
final class LandingController
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Landing', [
            'app' => [
                'name' => 'Wiisnin',
                'tagline' => 'Order food from your North Shore community.',
            ],
            'communities' => Communities::cards(),
        ]);
    }
}
