<?php

declare(strict_types=1);

namespace App\Access;

use App\Entity\Order;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Orders are private. A customer may view their own orders; vendor_staff may
 * view and advance orders for their own vendor; admins may do anything.
 * Customers create orders (the place-order flow); they do not change status.
 */
#[PolicyAttribute(entityType: 'order')]
final class OrderAccessPolicy implements AccessPolicyInterface
{
    public function __construct(
        private readonly VendorStaffDirectory $directory,
    ) {}

    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'order';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if (!$entity instanceof Order) {
            return AccessResult::neutral();
        }

        if ($account->hasPermission(CommerceAccess::ADMINISTER)) {
            return AccessResult::allowed('Administrators manage any order.');
        }

        $vendorId = $entity->getVendorId();
        $isVendorStaff = $vendorId !== null
            && $account->hasPermission(CommerceAccess::MANAGE_ORDERS)
            && $this->directory->isStaffOfVendor($account, $vendorId);

        if ($operation === 'view') {
            if ((int) $account->id() === $entity->getCustomerUid()) {
                return AccessResult::allowed('Customers may view their own orders.');
            }
            if ($isVendorStaff) {
                return AccessResult::allowed('Vendor staff may view their vendor’s orders.');
            }
            return AccessResult::neutral('Not the customer or vendor staff for this order.');
        }

        if (in_array($operation, ['update', 'delete'], true)) {
            return $isVendorStaff
                ? AccessResult::allowed('Vendor staff may advance their vendor’s orders.')
                : AccessResult::neutral('Only vendor staff may change an order.');
        }

        return AccessResult::neutral();
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        return $account->hasPermission(CommerceAccess::PLACE_ORDERS)
            ? AccessResult::allowed('Customers may place orders.')
            : AccessResult::neutral('Placing an order requires the place-orders permission.');
    }
}
