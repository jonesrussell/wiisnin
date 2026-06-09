<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Catalog\Catalog;
use App\Domain\Demand\DemandService;
use App\Http\Csrf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * "I'd order here" demand votes for non-partner listings (CSRF-protected, one
 * vote per device). Partners are rejected — ordering is already live there.
 */
final class DemandController
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly DemandService $demand,
    ) {}

    public function vote(Request $request, string $slug): JsonResponse
    {
        if (!Csrf::valid($request)) {
            return new JsonResponse(['error' => 'CSRF token validation failed.'], 403);
        }

        $vendor = $this->catalog->vendorBySlug($slug);
        if ($vendor === null) {
            return new JsonResponse(['error' => 'not found'], 404);
        }
        if ($vendor->isPartner()) {
            return new JsonResponse(['error' => 'Ordering is already live here.'], 422);
        }

        $data = $this->payload($request);
        $deviceId = (string) ($data['device_id'] ?? '');
        $count = $this->demand->vote($slug, $deviceId);

        return new JsonResponse(['ok' => true, 'count' => $count]);
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
