<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Analytics\AnalyticsRecorder;
use App\Controller\CollectController;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public /api/collect endpoint always answers 204, stores valid beacons,
 * and silently ignores malformed/oversized ones.
 */
final class CollectRouteTest extends TestCase
{
    private InMemoryEntityRepository $events;

    protected function setUp(): void
    {
        $this->events = new InMemoryEntityRepository();
    }

    private function controller(): CollectController
    {
        return new CollectController(new AnalyticsRecorder($this->events, 'secret', static fn (): int => 1_700_000_000));
    }

    private function post(string $body, array $server = []): Request
    {
        return Request::create('/api/collect', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'] + $server, $body);
    }

    #[Test]
    public function a_valid_beacon_returns_204_and_is_stored(): void
    {
        $response = $this->controller()->collect($this->post('{"t":"pageview","p":"/","v":"view-1"}'));
        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertCount(1, $this->events->findBy([]));
    }

    #[Test]
    public function a_wiisnin_call_event_is_stored(): void
    {
        $response = $this->controller()->collect($this->post('{"t":"call","v":"view-1","slug":"wing-house"}'));
        $this->assertSame(204, $response->getStatusCode());
        $rows = $this->events->findBy([]);
        $this->assertCount(1, $rows);
        $this->assertSame('call', $rows[0]->get('event_type'));
        $this->assertSame('wing-house', $rows[0]->get('slug'));
    }

    #[Test]
    public function malformed_or_empty_bodies_return_204_and_store_nothing(): void
    {
        $this->assertSame(204, $this->controller()->collect($this->post(''))->getStatusCode());
        $this->assertSame(204, $this->controller()->collect($this->post('not json'))->getStatusCode());
        $this->assertSame(204, $this->controller()->collect($this->post('{"t":"evil","v":"x"}'))->getStatusCode());
        $this->assertCount(0, $this->events->findBy([]));
    }

    #[Test]
    public function an_oversized_body_is_ignored(): void
    {
        $huge = '{"t":"pageview","p":"/","v":"' . str_repeat('a', 3000) . '"}';
        $response = $this->controller()->collect($this->post($huge));
        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(0, $this->events->findBy([]), 'over the 2KB cap, dropped before parsing');
    }

    #[Test]
    public function a_cross_origin_beacon_is_dropped(): void
    {
        // Origin host differs from the request host -> rejected (but still 204).
        $request = $this->post('{"t":"pageview","p":"/","v":"v"}', ['HTTP_ORIGIN' => 'https://evil.example']);
        $response = $this->controller()->collect($request);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(0, $this->events->findBy([]));
    }
}
