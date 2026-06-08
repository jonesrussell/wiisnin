<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Catalog\Catalog;
use App\Domain\Order\OrderView;
use App\Domain\Order\OrderWorkflowService;
use App\Entity\Order;
use App\Support\AppMeta;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;
use Waaseyaa\Mercure\MercurePublisher;

/**
 * The vendor inbox — the demo "wow moment".
 *
 * Gated by a shared demo passphrase (a signed cookie, not real accounts). Shows
 * incoming orders newest-first; the Vue page subscribes to the vendor's Mercure
 * topic and refetches on each event so new orders appear with no refresh.
 * Workflow buttons advance order status via the Order workflow.
 */
final class VendorInboxController
{
    private const string COOKIE = 'wsn_vendor';
    private const string VENDOR_SLUG = 'meedjims-foodland';

    public function __construct(
        private readonly Catalog $catalog,
        private readonly EntityRepositoryInterface $orderRepo,
        private readonly EntityRepositoryInterface $orderItemRepo,
        private readonly OrderWorkflowService $workflow,
        private readonly MercurePublisher $mercure,
        private readonly string $passphrase,
        private readonly string $mercurePublicUrl,
        private readonly string $cookieSecret,
    ) {}

    public function index(Request $request): Response|InertiaResponse
    {
        if (!$this->authed($request)) {
            return Inertia::render('VendorLogin', ['app' => AppMeta::props(), 'error' => null]);
        }

        $vendor = $this->catalog->vendorBySlug(self::VENDOR_SLUG);
        $vendorId = $vendor !== null ? (int) $vendor->id() : 0;

        return Inertia::render('VendorInbox', [
            'app' => AppMeta::props(),
            'vendor' => $vendor !== null ? $this->catalog->vendorCard($vendor) : null,
            'orders' => $this->ordersFor($vendorId),
            'mercure' => [
                'url' => $this->mercurePublicUrl,
                'topic' => 'vendor/' . $vendorId . '/orders',
            ],
        ]);
    }

    public function login(Request $request): Response|InertiaResponse
    {
        $data = $this->payload($request);
        $given = (string) ($data['passphrase'] ?? '');

        if ($this->passphrase !== '' && hash_equals($this->passphrase, $given)) {
            $response = new RedirectResponse('/vendor', 303);
            $response->headers->setCookie(Cookie::create(
                name: self::COOKIE,
                value: $this->token(),
                expire: time() + 12 * 3600,
                path: '/',
                secure: false, // demo runs behind Cloudflare TLS but Caddy sees http
                httpOnly: true,
                sameSite: Cookie::SAMESITE_LAX,
            ));
            return $response;
        }

        return Inertia::render('VendorLogin', [
            'app' => AppMeta::props(),
            'error' => 'Incorrect passphrase.',
        ]);
    }

    public function ordersJson(Request $request, string $vid): Response
    {
        if (!$this->authed($request)) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        return new JsonResponse(['orders' => $this->ordersFor((int) $vid)]);
    }

    public function transition(Request $request, string $id): Response
    {
        if (!$this->authed($request)) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $order = $this->orderRepo->find($id);
        if (!$order instanceof Order) {
            return new JsonResponse(['error' => 'not found'], 404);
        }

        $to = (string) ($this->payload($request)['to'] ?? '');
        try {
            $this->workflow->transitionTo($order, $to, time());
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }
        $this->orderRepo->save($order);

        // Broadcast so every open inbox refetches.
        if ($this->mercure->isConfigured()) {
            $this->mercure->publish(
                'vendor/' . (int) $order->getVendorId() . '/orders',
                ['event' => 'order.updated', 'reference' => $order->getReference(), 'status' => $order->getStatus()],
            );
        }

        return new JsonResponse(['ok' => true, 'order' => $this->orderView($order)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ordersFor(int $vendorId): array
    {
        if ($vendorId === 0) {
            return [];
        }

        $orders = $this->orderRepo->findBy(['vendor_id' => $vendorId], ['placed_at' => 'DESC']);
        $out = [];
        foreach ($orders as $order) {
            if ($order instanceof Order) {
                $out[] = $this->orderView($order);
            }
        }

        // Newest first even if the storage sort is not honored for blob fields.
        usort($out, static fn (array $a, array $b): int => ($b['placed_at'] ?? 0) <=> ($a['placed_at'] ?? 0));

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderView(Order $order): array
    {
        $view = OrderView::of($order, $this->orderItemRepo);
        $view['transitions'] = array_map(
            static fn ($t): array => ['id' => $t->id, 'label' => $t->label, 'to' => $t->to],
            array_values($this->workflow->availableTransitions($order)),
        );

        return $view;
    }

    private function authed(Request $request): bool
    {
        $cookie = (string) $request->cookies->get(self::COOKIE, '');

        return $cookie !== '' && hash_equals($this->token(), $cookie);
    }

    private function token(): string
    {
        return hash_hmac('sha256', 'wiisnin-vendor-inbox', $this->cookieSecret);
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
