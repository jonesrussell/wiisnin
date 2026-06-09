<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Catalog\Catalog;
use App\Domain\Claim\ClaimService;
use App\Http\Csrf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Mail\Envelope;
use Waaseyaa\Mail\MailerInterface;

/**
 * Owner "claim this listing / set up ordering" requests (CSRF-protected). Stores
 * a durable ClaimRequest and best-effort emails Russell. Partners can't be
 * claimed (Meedjims already orders).
 */
final class ClaimController
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly ClaimService $claims,
        private readonly ?MailerInterface $mailer = null,
        private readonly string $notifyEmail = 'jonesrussell42@gmail.com',
    ) {}

    public function create(Request $request, string $slug): JsonResponse
    {
        if (!Csrf::valid($request)) {
            return new JsonResponse(['error' => 'CSRF token validation failed.'], 403);
        }

        $vendor = $this->catalog->vendorBySlug($slug);
        if ($vendor === null) {
            return new JsonResponse(['error' => 'not found'], 404);
        }
        if ($vendor->isPartner()) {
            return new JsonResponse(['error' => 'This listing already takes orders on Wiisnin.'], 422);
        }

        $data = $this->payload($request);
        try {
            $claim = $this->claims->create(
                $slug,
                $vendor->getName(),
                (string) ($data['owner_name'] ?? ''),
                (string) ($data['phone'] ?? ''),
                (string) ($data['email'] ?? ''),
                (string) ($data['note'] ?? ''),
            );
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        $this->notify($vendor->getName(), $claim->getOwnerName(), $claim->getPhone(), $claim->getEmail(), $claim->getNote());

        return new JsonResponse(['ok' => true]);
    }

    private function notify(string $vendorName, string $owner, string $phone, string $email, string $note): void
    {
        if ($this->mailer === null) {
            return;
        }
        $body = "New Wiisnin listing claim.\n\n"
            . "Vendor: {$vendorName}\n"
            . "Owner:  {$owner}\n"
            . "Phone:  " . ($phone !== '' ? $phone : '—') . "\n"
            . "Email:  " . ($email !== '' ? $email : '—') . "\n"
            . "Note:   " . ($note !== '' ? $note : '—') . "\n";
        try {
            $this->mailer->send(new Envelope(
                to: [$this->notifyEmail],
                from: 'claims@wiisnin.ca',
                subject: "Wiisnin claim: {$vendorName}",
                textBody: $body,
            ));
        } catch (\Throwable) {
            // Mail is best-effort; the stored ClaimRequest is the durable record.
        }
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
