<?php

declare(strict_types=1);

namespace App\Access;

use App\Entity\Vendor;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Vendor profiles are public to read; only admins, or vendor_staff of that
 * vendor's group, may edit them.
 */
#[PolicyAttribute(entityType: 'vendor')]
final class VendorAccessPolicy implements AccessPolicyInterface
{
    public function __construct(
        private readonly VendorStaffDirectory $directory,
    ) {}

    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'vendor';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            return AccessResult::allowed('Vendors are public.');
        }

        if (!$entity instanceof Vendor) {
            return AccessResult::neutral();
        }

        if ($account->hasPermission(CommerceAccess::ADMINISTER)) {
            return AccessResult::allowed('Administrators manage any vendor.');
        }

        if (
            in_array($operation, ['update', 'delete'], true)
            && $account->hasPermission(CommerceAccess::MANAGE_VENDOR)
            && $this->directory->isStaffOfVendor($account, (int) $entity->id())
        ) {
            return AccessResult::allowed('Vendor staff may manage their own vendor.');
        }

        return AccessResult::neutral('Not staff of this vendor.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        // Vendor onboarding is an admin action.
        return $account->hasPermission(CommerceAccess::ADMINISTER)
            ? AccessResult::allowed('Administrators onboard vendors.')
            : AccessResult::neutral('Vendor creation requires administer permission.');
    }
}
