<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Access\CommerceAccess;
use App\Access\VendorStaffDirectory;
use App\Entity\GroupMembership;
use App\Entity\MenuItem;
use App\Entity\Order;
use App\Entity\Vendor;
use App\Tests\Support\FakeAccount;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Access model: vendor_staff are scoped to their own vendor's group, customers
 * see only their own orders, admins may do anything, and menus/vendors are
 * publicly viewable.
 */
final class CommerceAccessTest extends TestCase
{
    private InMemoryEntityRepository $vendors;
    private InMemoryEntityRepository $memberships;
    private \Waaseyaa\Access\EntityAccessHandler $handler;

    private int $vendorAId;
    private int $vendorBId;

    protected function setUp(): void
    {
        $this->vendors = new InMemoryEntityRepository();
        $this->memberships = new InMemoryEntityRepository();

        $vendorA = new Vendor(['name' => 'Vendor A', 'owner_group_id' => 10, 'is_open' => 1]);
        $vendorB = new Vendor(['name' => 'Vendor B', 'owner_group_id' => 20, 'is_open' => 1]);
        $this->vendors->save($vendorA);
        $this->vendors->save($vendorB);
        $this->vendorAId = (int) $vendorA->id();
        $this->vendorBId = (int) $vendorB->id();

        // User 100 is staff of Vendor A's group (10); nobody is staff of B.
        $this->memberships->save(new GroupMembership(['group_id' => 10, 'user_id' => 100, 'role' => 'owner']));

        $directory = new VendorStaffDirectory($this->vendors, $this->memberships);
        $this->handler = CommerceAccess::handler($directory);
    }

    private function staffOfA(): FakeAccount
    {
        return new FakeAccount(100, [CommerceAccess::ROLE_VENDOR_STAFF], [
            CommerceAccess::MANAGE_VENDOR,
            CommerceAccess::MANAGE_ORDERS,
        ]);
    }

    #[Test]
    public function vendor_staff_can_update_their_own_orders_but_not_another_vendors(): void
    {
        $orderA = new Order(['vendor_id' => $this->vendorAId, 'customer_uid' => 7, 'status' => 'placed']);
        $orderB = new Order(['vendor_id' => $this->vendorBId, 'customer_uid' => 8, 'status' => 'placed']);

        $staff = $this->staffOfA();

        $this->assertTrue($this->handler->check($orderA, 'update', $staff)->isAllowed());
        $this->assertFalse($this->handler->check($orderB, 'update', $staff)->isAllowed());
    }

    #[Test]
    public function vendor_staff_are_scoped_for_menu_items_too(): void
    {
        $itemA = new MenuItem(['vendor_id' => $this->vendorAId, 'name' => 'Scone', 'price_cents' => 300]);
        $itemB = new MenuItem(['vendor_id' => $this->vendorBId, 'name' => 'Poutine', 'price_cents' => 900]);

        $staff = $this->staffOfA();

        $this->assertTrue($this->handler->check($itemA, 'update', $staff)->isAllowed());
        $this->assertFalse($this->handler->check($itemB, 'update', $staff)->isAllowed());
        // Menus are public to read.
        $this->assertTrue($this->handler->check($itemB, 'view', $staff)->isAllowed());
    }

    #[Test]
    public function customers_see_only_their_own_orders_and_may_create_orders(): void
    {
        $customer = new FakeAccount(7, [CommerceAccess::ROLE_CUSTOMER], [CommerceAccess::PLACE_ORDERS]);

        $own = new Order(['vendor_id' => $this->vendorAId, 'customer_uid' => 7, 'status' => 'placed']);
        $other = new Order(['vendor_id' => $this->vendorAId, 'customer_uid' => 999, 'status' => 'placed']);

        $this->assertTrue($this->handler->check($own, 'view', $customer)->isAllowed());
        $this->assertFalse($this->handler->check($other, 'view', $customer)->isAllowed());
        $this->assertFalse($this->handler->check($own, 'update', $customer)->isAllowed());
        $this->assertTrue($this->handler->checkCreateAccess('order', 'order', $customer)->isAllowed());
    }

    #[Test]
    public function admins_may_manage_any_vendors_orders(): void
    {
        $admin = new FakeAccount(1, [CommerceAccess::ROLE_ADMIN]);
        $orderB = new Order(['vendor_id' => $this->vendorBId, 'customer_uid' => 8, 'status' => 'placed']);

        $this->assertTrue($this->handler->check($orderB, 'update', $admin)->isAllowed());
    }
}
