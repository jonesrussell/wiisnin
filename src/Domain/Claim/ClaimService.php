<?php

declare(strict_types=1);

namespace App\Domain\Claim;

use App\Entity\ClaimRequest;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Stores owner "claim this listing / set up ordering" requests. The stored
 * ClaimRequest is the durable record; emailing Russell is best-effort on top
 * (handled in the controller).
 */
final class ClaimService
{
    /** @var \Closure(): int */
    private \Closure $clock;

    public function __construct(
        private readonly EntityRepositoryInterface $claims,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * @throws \DomainException if the owner name or all contact methods are missing
     */
    public function create(string $vendorSlug, string $vendorName, string $ownerName, string $phone, string $email, string $note): ClaimRequest
    {
        $ownerName = trim($ownerName);
        $phone = trim($phone);
        $email = trim($email);
        if ($ownerName === '') {
            throw new \DomainException('Please tell us your name.');
        }
        if ($phone === '' && $email === '') {
            throw new \DomainException('Please leave a phone number or email so we can reach you.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('That email address looks off — please check it.');
        }

        $claim = new ClaimRequest([
            'vendor_slug' => $vendorSlug,
            'vendor_name' => $vendorName,
            'owner_name' => $ownerName,
            'phone' => $phone,
            'email' => $email,
            'note' => trim($note),
            'status' => 'new',
            'created_at' => ($this->clock)(),
        ]);
        $this->claims->save($claim);

        return $claim;
    }

    /**
     * All claims newest-first, as plain arrays (for the app:claims CLI).
     *
     * @return list<array<string, mixed>>
     */
    public function listAll(): array
    {
        $rows = array_values(array_filter(
            $this->claims->findBy([]),
            static fn (object $c): bool => $c instanceof ClaimRequest,
        ));
        usort($rows, static fn (ClaimRequest $a, ClaimRequest $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        return array_map(static fn (ClaimRequest $c): array => [
            'vendor_name' => $c->getVendorName(),
            'vendor_slug' => $c->getVendorSlug(),
            'owner_name' => $c->getOwnerName(),
            'phone' => $c->getPhone(),
            'email' => $c->getEmail(),
            'note' => $c->getNote(),
            'status' => $c->getStatus(),
            'created_at' => $c->getCreatedAt(),
        ], $rows);
    }
}
