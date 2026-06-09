<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Catalog\Catalog;
use App\Search\VendorSearchDocument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\SearchRequest;

/**
 * JSON API powering the location-first home: returns vendors across all four
 * communities sorted nearest-first (geo distance from the user's coords), with
 * optional community filter and FTS5 dish/kitchen search.
 *   GET /api/vendors?lat=&lng=&community=&q=
 */
final class VendorQueryController
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly ?SearchProviderInterface $search = null,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $latRaw = $request->query->get('lat');
        $lngRaw = $request->query->get('lng');
        $lat = is_numeric($latRaw) ? (float) $latRaw : null;
        $lng = is_numeric($lngRaw) ? (float) $lngRaw : null;

        $communityRaw = $request->query->get('community');
        $community = is_string($communityRaw) && $communityRaw !== '' ? $communityRaw : null;

        $query = trim((string) $request->query->get('q', ''));

        $langRaw = (string) ($request->query->get('lang') ?: $request->cookies->get('wsn_lang', ''));
        $locale = $langRaw === 'oj' ? 'oj' : 'en';

        $restrictIds = null;
        if ($query !== '' && $this->search !== null) {
            $restrictIds = [];
            try {
                $result = $this->search->search(new SearchRequest(query: $query, pageSize: 50));
                foreach ($result->hits as $hit) {
                    $vendorId = VendorSearchDocument::vendorIdFromHit($hit->id);
                    if ($vendorId !== null) {
                        $restrictIds[] = $vendorId;
                    }
                }
            } catch (\Throwable) {
                $restrictIds = [];
            }
        }

        return new JsonResponse([
            'vendors' => $this->catalog->vendorsNear($lat, $lng, $community, $restrictIds, $locale),
            'located' => $lat !== null && $lng !== null,
            'query' => $query,
        ]);
    }
}
