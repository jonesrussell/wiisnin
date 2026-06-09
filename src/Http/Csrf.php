<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * App-side CSRF validation for JSON POST endpoints.
 *
 * The framework's CsrfMiddleware EXEMPTS application/json bodies (see
 * WAASEYAA-FRICTION.md F-31), so our JSON mutation endpoints (review, claim,
 * demand) must validate the session token themselves. Accepted sources mirror
 * the middleware: the `_csrf_token` POST field, the `X-CSRF-Token` header, or the
 * URL-decoded `X-XSRF-TOKEN` header (the value of the framework's non-HttpOnly
 * XSRF-TOKEN cookie, which the in-app fetch echoes back).
 */
final class Csrf
{
    public static function valid(Request $request): bool
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
}
