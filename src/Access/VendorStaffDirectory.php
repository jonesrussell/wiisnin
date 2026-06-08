<?php

declare(strict_types=1);

namespace App\Access;

use App\Entity\Vendor;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Resolves whether an account is staff of a given vendor's group.
 *
 * The groups package supplies Group entities; membership is the app's
 * group_membership table. A vendor's staff are the members of its
 * owner_group_id. This is the seam the access policies use to scope vendor_staff
 * to their own vendor.
 */
final class VendorStaffDirectory
{
    public function __construct(
        private readonly EntityRepositoryInterface $vendors,
        private readonly EntityRepositoryInterface $memberships,
    ) {}

    public function isStaffOfVendor(AccountInterface $account, int $vendorId): bool
    {
        $vendor = $this->vendors->find((string) $vendorId);
        if (!$vendor instanceof Vendor) {
            return false;
        }

        $groupId = $vendor->getOwnerGroupId();
        if ($groupId === null) {
            return false;
        }

        return $this->isMemberOfGroup($account, $groupId);
    }

    public function isMemberOfGroup(AccountInterface $account, int $groupId): bool
    {
        $rows = $this->memberships->findBy([
            'group_id' => $groupId,
            'user_id' => (int) $account->id(),
        ]);

        return $rows !== [];
    }
}
