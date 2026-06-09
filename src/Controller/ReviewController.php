<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Catalog\Catalog;
use App\Domain\Review\ReviewService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Customer review creation (public) and staff moderation (passphrase-gated, same
 * cookie as the vendor inbox).
 */
final class ReviewController
{
    private const COOKIE = 'wsn_vendor';

    public function __construct(
        private readonly Catalog $catalog,
        private readonly ReviewService $reviews,
        private readonly string $cookieSecret,
    ) {}

    public function create(Request $request, string $slug): JsonResponse
    {
        if (!$this->hasValidCsrfToken($request)) {
            return $this->csrfFailure();
        }

        $vendor = $this->catalog->vendorBySlug($slug);
        if ($vendor === null) {
            return new JsonResponse(['error' => 'not found'], 404);
        }

        $data = $this->payload($request);
        try {
            $this->reviews->create(
                (int) $vendor->id(),
                0, // guest demo review
                (string) ($data['author_name'] ?? $data['name'] ?? ''),
                (int) ($data['rating'] ?? 0),
                (string) ($data['body'] ?? ''),
            );
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'ok' => true,
            'summary' => $this->reviews->summary((int) $vendor->id()),
            'reviews' => $this->reviews->listFor((int) $vendor->id()),
        ]);
    }

    public function hide(Request $request, string $id): JsonResponse
    {
        if (!$this->hasValidCsrfToken($request)) {
            return $this->csrfFailure();
        }
        if (!$this->authed($request)) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        return new JsonResponse(['ok' => $this->reviews->hide((int) $id)]);
    }

    /**
     * Validate the CSRF token the same way the framework's CsrfMiddleware does.
     *
     * The middleware exempts application/json bodies (a cross-site HTML form
     * can't send that content type), so this JSON endpoint isn't covered by it —
     * and it accepts a form-encoded fallback, which a forged form COULD reach.
     * We therefore enforce the session-bound token here. Accepted sources mirror
     * the middleware: the `_csrf_token` POST field, the `X-CSRF-Token` header, or
     * the URL-decoded `X-XSRF-TOKEN` header (the value of the framework's
     * non-HttpOnly XSRF-TOKEN cookie, which the in-app fetch echoes back — the
     * same token Inertia's axios sends on the order path).
     */
    private function hasValidCsrfToken(Request $request): bool
    {
        $session = $_SESSION['_csrf_token'] ?? '';
        if (!is_string($session) || $session === '') {
            return false;
        }

        $field = $request->request->get('_csrf_token');
        if (is_string($field) && hash_equals($session, $field)) {
            return true;
        }

        $header = $request->headers->get('X-CSRF-Token');
        if (is_string($header) && hash_equals($session, $header)) {
            return true;
        }

        $xsrf = $request->headers->get('X-XSRF-TOKEN');
        if (is_string($xsrf) && hash_equals($session, rawurldecode($xsrf))) {
            return true;
        }

        return false;
    }

    private function csrfFailure(): JsonResponse
    {
        return new JsonResponse(['error' => 'CSRF token validation failed.'], 403);
    }

    private function authed(Request $request): bool
    {
        $cookie = (string) $request->cookies->get(self::COOKIE, '');
        $token = hash_hmac('sha256', 'wiisnin-vendor-inbox', $this->cookieSecret);

        return $cookie !== '' && hash_equals($token, $cookie);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $raw = $request->getContent();
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $request->request->all();
    }
}
