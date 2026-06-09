<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\ReviewController;
use App\Domain\Catalog\Catalog;
use App\Domain\Review\ReviewService;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The review create endpoint is CSRF-protected: a POST without a valid token is
 * rejected (403), while an in-app POST carrying the session token (echoed from
 * the XSRF-TOKEN cookie) succeeds. The partner-only rule is independent of CSRF
 * — a sample listing still 422s even with a valid token.
 */
final class ReviewRouteCsrfTest extends TestCase
{
    private const string TOKEN = 'test-csrf-token-abc123';

    private InMemoryEntityRepository $vendors;
    private InMemoryEntityRepository $menuItems;
    private InMemoryEntityRepository $terms;
    private InMemoryEntityRepository $reviews;

    protected function setUp(): void
    {
        $this->vendors = new InMemoryEntityRepository();
        $this->menuItems = new InMemoryEntityRepository();
        $this->terms = new InMemoryEntityRepository();
        $this->reviews = new InMemoryEntityRepository();

        $this->vendors->save(new Vendor(['name' => 'Meedjims Foodland', 'slug' => 'meedjims-foodland', 'is_partner' => 1]));
        $this->vendors->save(new Vendor(['name' => "Tony V's Pizza", 'slug' => 'tony-vs-pizza', 'is_partner' => 0]));

        // The controller validates against the framework's session CSRF token.
        $_SESSION['_csrf_token'] = self::TOKEN;
    }

    protected function tearDown(): void
    {
        unset($_SESSION['_csrf_token']);
    }

    private function controller(): ReviewController
    {
        $reviewService = new ReviewService($this->reviews, $this->vendors);
        $catalog = new Catalog($this->vendors, $this->menuItems, $this->terms, $reviewService);

        return new ReviewController($catalog, $reviewService, 'unused-cookie-secret');
    }

    private function postReview(string $slug, ?string $token): Request
    {
        $request = Request::create(
            "/vendor/{$slug}/reviews",
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['author_name' => 'June', 'rating' => 5, 'body' => 'Great']),
        );
        if ($token !== null) {
            $request->headers->set('X-XSRF-TOKEN', $token);
        }

        return $request;
    }

    #[Test]
    public function a_post_without_a_token_is_rejected(): void
    {
        $response = $this->controller()->create($this->postReview('meedjims-foodland', null), 'meedjims-foodland');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertCount(0, $this->reviews->findBy([]), 'no review should be written on a CSRF failure');
    }

    #[Test]
    public function a_post_with_a_bad_token_is_rejected(): void
    {
        $response = $this->controller()->create($this->postReview('meedjims-foodland', 'wrong-token'), 'meedjims-foodland');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertCount(0, $this->reviews->findBy([]));
    }

    #[Test]
    public function a_valid_in_app_post_succeeds(): void
    {
        $response = $this->controller()->create($this->postReview('meedjims-foodland', self::TOKEN), 'meedjims-foodland');

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['ok']);
        $this->assertSame(1, $data['summary']['count']);
        $this->assertCount(1, $this->reviews->findBy([]));
    }

    #[Test]
    public function a_url_encoded_token_value_is_accepted(): void
    {
        // The XSRF-TOKEN cookie value is rawurlencoded; the header echoes it
        // verbatim and the server rawurldecodes before comparison.
        $response = $this->controller()->create(
            $this->postReview('meedjims-foodland', rawurlencode(self::TOKEN)),
            'meedjims-foodland',
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function a_sample_listing_still_rejects_even_with_a_valid_token(): void
    {
        $response = $this->controller()->create($this->postReview('tony-vs-pizza', self::TOKEN), 'tony-vs-pizza');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertCount(0, $this->reviews->findBy([]));
    }
}
