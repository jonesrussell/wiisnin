<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\DemandController;
use App\Domain\Catalog\Catalog;
use App\Domain\Demand\DemandService;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The demand-vote endpoint is CSRF-protected, increments a per-vendor count,
 * dedupes one-vote-per-device, and refuses partners (ordering already live).
 */
final class DemandRouteCsrfTest extends TestCase
{
    private const string TOKEN = 'demand-csrf-token-xyz';

    private InMemoryEntityRepository $vendors;
    private InMemoryEntityRepository $votes;

    protected function setUp(): void
    {
        $this->vendors = new InMemoryEntityRepository();
        $this->votes = new InMemoryEntityRepository();
        $this->vendors->save(new Vendor(['name' => 'Wing House', 'slug' => 'wing-house', 'is_partner' => 0]));
        $this->vendors->save(new Vendor(['name' => 'Meedjims Foodland', 'slug' => 'meedjims-foodland', 'is_partner' => 1]));
        $_SESSION['_csrf_token'] = self::TOKEN;
    }

    protected function tearDown(): void
    {
        unset($_SESSION['_csrf_token']);
    }

    private function controller(): DemandController
    {
        $demand = new DemandService($this->votes, static fn (): int => 1_700_000_000);
        $catalog = new Catalog($this->vendors, new InMemoryEntityRepository(), new InMemoryEntityRepository(), null, $demand);

        return new DemandController($catalog, $demand);
    }

    private function post(string $slug, ?string $token, string $device): Request
    {
        $request = Request::create(
            "/vendor/{$slug}/demand",
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['device_id' => $device]),
        );
        if ($token !== null) {
            $request->headers->set('X-XSRF-TOKEN', $token);
        }

        return $request;
    }

    #[Test]
    public function a_vote_without_a_token_is_rejected(): void
    {
        $response = $this->controller()->vote($this->post('wing-house', null, 'dev-1'), 'wing-house');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertCount(0, $this->votes->findBy([]));
    }

    #[Test]
    public function a_vote_with_a_bad_token_is_rejected(): void
    {
        $response = $this->controller()->vote($this->post('wing-house', 'nope', 'dev-1'), 'wing-house');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertCount(0, $this->votes->findBy([]));
    }

    #[Test]
    public function a_valid_vote_increments_and_dedupes_per_device(): void
    {
        $c = $this->controller();

        $r1 = $c->vote($this->post('wing-house', self::TOKEN, 'dev-1'), 'wing-house');
        $this->assertSame(200, $r1->getStatusCode());
        $this->assertSame(1, json_decode((string) $r1->getContent(), true)['count']);

        // Same device again — count stays 1.
        $r2 = $c->vote($this->post('wing-house', self::TOKEN, 'dev-1'), 'wing-house');
        $this->assertSame(1, json_decode((string) $r2->getContent(), true)['count']);

        // Different device — count goes to 2.
        $r3 = $c->vote($this->post('wing-house', self::TOKEN, 'dev-2'), 'wing-house');
        $this->assertSame(2, json_decode((string) $r3->getContent(), true)['count']);

        $this->assertCount(2, $this->votes->findBy([]));
    }

    #[Test]
    public function a_partner_cannot_be_voted_on(): void
    {
        $response = $this->controller()->vote($this->post('meedjims-foodland', self::TOKEN, 'dev-1'), 'meedjims-foodland');
        $this->assertSame(422, $response->getStatusCode());
        $this->assertCount(0, $this->votes->findBy([]));
    }
}
