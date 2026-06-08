<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;

/**
 * One line on an order: a quantity of a menu item at the price captured when the
 * order was placed (`unit_price_cents` is a snapshot, so later menu price changes
 * never alter a historical order). Non-key fields are FieldStorage::Data so
 * findBy(['order_id' => ...]) works.
 */
#[ContentEntityType(id: 'order_item', label: 'Order item', description: 'A line item on an order.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'name_snapshot')]
final class OrderItem extends ContentEntityBase
{
    #[Field(type: 'integer', label: 'Order', required: true, description: 'order entity id.', stored: FieldStorage::Data)]
    public ?int $order_id = null;

    #[Field(type: 'integer', label: 'Menu item', required: true, description: 'menu_item entity id.', stored: FieldStorage::Data)]
    public ?int $menu_item_id = null;

    #[Field(label: 'Name snapshot', description: 'Menu item name captured at order time.')]
    public string $name_snapshot = '';

    #[Field(type: 'integer', label: 'Quantity', required: true, default: 1, stored: FieldStorage::Data)]
    public int $quantity = 1;

    #[Field(type: 'integer', label: 'Unit price (cents)', required: true, description: 'Price snapshot at order time.', stored: FieldStorage::Data)]
    public int $unit_price_cents = 0;

    #[Field(type: 'integer', label: 'Line total (cents)', default: 0, stored: FieldStorage::Data)]
    public int $line_total_cents = 0;

    #[Field(type: 'text', label: 'Line note', description: 'e.g. "no onions".', stored: FieldStorage::Data)]
    public string $line_note = '';

    public function getOrderId(): ?int
    {
        $id = $this->get('order_id');
        return $id === null ? null : (int) $id;
    }

    public function getMenuItemId(): ?int
    {
        $id = $this->get('menu_item_id');
        return $id === null ? null : (int) $id;
    }

    public function getNameSnapshot(): string
    {
        return (string) ($this->get('name_snapshot') ?? '');
    }

    public function getQuantity(): int
    {
        return (int) ($this->get('quantity') ?? 0);
    }

    public function getUnitPriceCents(): int
    {
        return (int) ($this->get('unit_price_cents') ?? 0);
    }

    public function getLineTotalCents(): int
    {
        return (int) ($this->get('line_total_cents') ?? 0);
    }
}
