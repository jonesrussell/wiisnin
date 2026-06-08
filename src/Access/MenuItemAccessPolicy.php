<?php

declare(strict_types=1);

namespace App\Access;

use App\Entity\MenuItem;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Menus are public to read; only admins, or vendor_staff of the item's vendor,
 * may create/edit/delete menu items.
 */
#[PolicyAttribute(entityType: 'menu_item')]
final class MenuItemAccessPolicy implements AccessPolicyInterface
{
    public function __construct(
        private readonly VendorStaffDirectory $directory,
    ) {}

    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'menu_item';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            return AccessResult::allowed('Menus are public.');
        }

        if (!$entity instanceof MenuItem) {
            return AccessResult::neutral();
        }

        if ($account->hasPermission(CommerceAccess::ADMINISTER)) {
            return AccessResult::allowed('Administrators manage any menu.');
        }

        $vendorId = $entity->getVendorId();
        if (
            $vendorId !== null
            && in_array($operation, ['update', 'delete'], true)
            && $account->hasPermission(CommerceAccess::MANAGE_VENDOR)
            && $this->directory->isStaffOfVendor($account, $vendorId)
        ) {
            return AccessResult::allowed('Vendor staff may manage their own menu.');
        }

        return AccessResult::neutral('Not staff of this vendor.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        // Vendor staff may add items; the caller must scope the new item to the
        // staffer's own vendor (createAccess has no entity to scope against).
        return $account->hasPermission(CommerceAccess::MANAGE_VENDOR)
            ? AccessResult::allowed('Vendor staff may add menu items.')
            : AccessResult::neutral('Menu authoring requires manage-vendor permission.');
    }
}
