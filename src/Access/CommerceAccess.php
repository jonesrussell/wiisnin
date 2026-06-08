<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\User\User;

/**
 * Wiisnin's role + permission model, mapped onto Waaseyaa's role/permission
 * substrate (no parallel system), following the fnpi WorkspaceAccess pattern.
 *
 * Three roles:
 *   - admin        → the framework's built-in `administrator` role, which
 *                    short-circuits every permission check (all permissions).
 *   - vendor_staff → manage their own vendor's menu and orders. Scoping to the
 *                    vendor's group is enforced by the access policies via
 *                    VendorStaffDirectory (groups package + group_membership).
 *   - customer     → place orders and view their own orders.
 *
 * Because User::hasPermission() only special-cases `administrator` (it does not
 * union a role's permissions), apply() writes a role's concrete permission
 * strings onto the user, so the role and its permissions travel together.
 */
final class CommerceAccess
{
    // Roles.
    public const string ROLE_ADMIN = 'administrator';
    public const string ROLE_VENDOR_STAFF = 'vendor_staff';
    public const string ROLE_CUSTOMER = 'customer';

    // Permissions.
    public const string ADMINISTER = 'administer commerce';
    public const string MANAGE_VENDOR = 'manage vendor';   // edit vendor profile + menu
    public const string MANAGE_ORDERS = 'manage orders';   // accept / advance / cancel orders
    public const string PLACE_ORDERS = 'place orders';     // create orders, view own

    /**
     * Role definitions: id => {label, permissions}.
     *
     * @return array<string, array{label: string, permissions: list<string>}>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN => [
                'label' => 'Administrator',
                'permissions' => [self::ADMINISTER, self::MANAGE_VENDOR, self::MANAGE_ORDERS, self::PLACE_ORDERS],
            ],
            self::ROLE_VENDOR_STAFF => [
                'label' => 'Vendor staff',
                'permissions' => [self::MANAGE_VENDOR, self::MANAGE_ORDERS],
            ],
            self::ROLE_CUSTOMER => [
                'label' => 'Customer',
                'permissions' => [self::PLACE_ORDERS],
            ],
        ];
    }

    public static function isRole(string $roleId): bool
    {
        return array_key_exists($roleId, self::roles());
    }

    /**
     * Apply a role to a user: set the role id and write its permission strings,
     * preserving any non-commerce roles. The caller persists the user.
     */
    public static function apply(User $user, string $roleId): void
    {
        $defs = self::roles();
        $permissions = $defs[$roleId]['permissions'] ?? [];

        $kept = array_values(array_filter(
            $user->getRoles(),
            static fn (string $role): bool => !array_key_exists($role, $defs),
        ));
        $user->setRoles(array_values(array_unique([...$kept, $roleId])));
        $user->setPermissions($permissions);
    }

    /**
     * Single construction point for the commerce access handler: the three
     * entity policies, sharing one VendorStaffDirectory. Reused by controllers
     * (later) and tests so there is one source of truth.
     */
    public static function handler(VendorStaffDirectory $directory): EntityAccessHandler
    {
        return new EntityAccessHandler([
            new VendorAccessPolicy($directory),
            new MenuItemAccessPolicy($directory),
            new OrderAccessPolicy($directory),
        ]);
    }
}
