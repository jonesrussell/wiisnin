<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\ClaimController;
use App\Domain\Catalog\Catalog;
use App\Domain\Claim\ClaimService;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Mail\Envelope;
use Waaseyaa\Mail\MailerInterface;

/**
 * The owner claim endpoint is CSRF-protected, stores a durable ClaimRequest,
 * validates contact details, and refuses partners (already ordering).
 */
final class ClaimRouteCsrfTest extends TestCase
{
    private const string TOKEN = 'claim-csrf-token-xyz';

    private InMemoryEntityRepository $vendors;
    private InMemoryEntityRepository $claims;

    protected function setUp(): void
    {
        $this->vendors = new InMemoryEntityRepository();
        $this->claims = new InMemoryEntityRepository();
        $this->vendors->save(new Vendor(['name' => 'Wing House', 'slug' => 'wing-house', 'is_partner' => 0]));
        $this->vendors->save(new Vendor(['name' => 'Meedjims Foodland', 'slug' => 'meedjims-foodland', 'is_partner' => 1]));
        $_SESSION['_csrf_token'] = self::TOKEN;
    }

    protected function tearDown(): void
    {
        unset($_SESSION['_csrf_token']);
    }

    private function controller(): ClaimController
    {
        $catalog = new Catalog($this->vendors, new InMemoryEntityRepository(), new InMemoryEntityRepository());

        return new ClaimController($catalog, new ClaimService($this->claims, static fn (): int => 1_700_000_000), null);
    }

    /** @param array<string,mixed> $body */
    private function post(string $slug, ?string $token, array $body): Request
    {
        $request = Request::create(
            "/vendor/{$slug}/claim",
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($body),
        );
        if ($token !== null) {
            $request->headers->set('X-XSRF-TOKEN', $token);
        }

        return $request;
    }

    #[Test]
    public function a_claim_without_a_token_is_rejected(): void
    {
        $response = $this->controller()->create(
            $this->post('wing-house', null, ['owner_name' => 'Jo', 'phone' => '705-555-0100']),
            'wing-house',
        );
        $this->assertSame(403, $response->getStatusCode());
        $this->assertCount(0, $this->claims->findBy([]));
    }

    #[Test]
    public function a_valid_claim_is_stored(): void
    {
        $response = $this->controller()->create(
            $this->post('wing-house', self::TOKEN, ['owner_name' => 'Jo Owner', 'phone' => '705-555-0100', 'note' => 'keen']),
            'wing-house',
        );
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode((string) $response->getContent(), true)['ok']);

        $stored = $this->claims->findBy([]);
        $this->assertCount(1, $stored);
        $this->assertSame('Jo Owner', $stored[0]->get('owner_name'));
        $this->assertSame('wing-house', $stored[0]->get('vendor_slug'));
    }

    #[Test]
    public function a_claim_needs_a_name_and_a_contact_method(): void
    {
        $noName = $this->controller()->create(
            $this->post('wing-house', self::TOKEN, ['owner_name' => '', 'phone' => '705-555-0100']),
            'wing-house',
        );
        $this->assertSame(422, $noName->getStatusCode());

        $noContact = $this->controller()->create(
            $this->post('wing-house', self::TOKEN, ['owner_name' => 'Jo']),
            'wing-house',
        );
        $this->assertSame(422, $noContact->getStatusCode());

        $this->assertCount(0, $this->claims->findBy([]));
    }

    #[Test]
    public function a_claim_is_stored_even_when_mail_throws(): void
    {
        $throwing = new class implements MailerInterface {
            public function send(Envelope $envelope): void
            {
                throw new \RuntimeException('no transport configured');
            }
        };
        $catalog = new Catalog($this->vendors, new InMemoryEntityRepository(), new InMemoryEntityRepository());
        $controller = new ClaimController($catalog, new ClaimService($this->claims, static fn (): int => 1), $throwing);

        $response = $controller->create(
            $this->post('wing-house', self::TOKEN, ['owner_name' => 'Jo Owner', 'phone' => '705-555-0100']),
            'wing-house',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode((string) $response->getContent(), true)['ok']);
        $this->assertCount(1, $this->claims->findBy([]), 'the durable claim is stored even when mail fails');
    }

    #[Test]
    public function a_partner_cannot_be_claimed(): void
    {
        $response = $this->controller()->create(
            $this->post('meedjims-foodland', self::TOKEN, ['owner_name' => 'Jo', 'phone' => '705-555-0100']),
            'meedjims-foodland',
        );
        $this->assertSame(422, $response->getStatusCode());
        $this->assertCount(0, $this->claims->findBy([]));
    }
}
