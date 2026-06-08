<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;

/**
 * A customer's order against a single vendor.
 *
 * `status` holds the current state of the Order workflow (placed → accepted →
 * preparing → ready → completed, with cancelled reachable from placed/accepted).
 * Transitions are validated by App\Domain\Order\OrderWorkflowService, which is
 * defined with the workflows package.
 *
 * Money is stored in integer cents. Line items live in OrderItem rows linked by
 * `order_id`. Non-key fields are FieldStorage::Data so findBy() works (e.g. by
 * vendor_id, customer_uid, status).
 */
#[ContentEntityType(id: 'order', label: 'Order', description: 'A customer order against one vendor.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'reference')]
final class Order extends ContentEntityBase
{
    #[Field(label: 'Reference', description: 'Short human-friendly order code, e.g. WSN-000123.')]
    public string $reference = '';

    #[Field(type: 'integer', label: 'Customer', required: true, description: 'user account id (uid); 0 for a guest demo order.', stored: FieldStorage::Data)]
    public ?int $customer_uid = null;

    #[Field(label: 'Customer name', description: 'Name given at checkout (guest orders).', stored: FieldStorage::Data)]
    public string $customer_name = '';

    #[Field(type: 'integer', label: 'Vendor', required: true, description: 'vendor entity id.', stored: FieldStorage::Data)]
    public ?int $vendor_id = null;

    #[Field(label: 'Status', required: true, description: 'Order workflow state.', default: 'placed', stored: FieldStorage::Data)]
    public string $status = 'placed';

    #[Field(label: 'Fulfilment', required: true, description: 'pickup | delivery.', default: 'pickup', stored: FieldStorage::Data)]
    public string $fulfilment = 'pickup';

    #[Field(type: 'text', label: 'Address', description: 'Delivery address (delivery orders only).', stored: FieldStorage::Data)]
    public string $address = '';

    #[Field(type: 'integer', label: 'Community', description: 'taxonomy_term id in the "community" vocabulary.', stored: FieldStorage::Data)]
    public ?int $community_tid = null;

    #[Field(label: 'Contact phone', required: true, stored: FieldStorage::Data)]
    public string $contact_phone = '';

    #[Field(label: 'Payment method', required: true, description: 'cash | etransfer (handled offline).', default: 'cash', stored: FieldStorage::Data)]
    public string $payment_method = 'cash';

    #[Field(type: 'text', label: 'Notes', stored: FieldStorage::Data)]
    public string $notes = '';

    #[Field(type: 'integer', label: 'Subtotal (cents)', default: 0, stored: FieldStorage::Data)]
    public int $subtotal_cents = 0;

    #[Field(type: 'integer', label: 'Total (cents)', default: 0, stored: FieldStorage::Data)]
    public int $total_cents = 0;

    #[Field(type: 'integer', label: 'Placed at', description: 'Unix timestamp.', stored: FieldStorage::Data)]
    public ?int $placed_at = null;

    #[Field(type: 'integer', label: 'Updated at', description: 'Unix timestamp.', stored: FieldStorage::Data)]
    public ?int $updated_at = null;

    public function getReference(): string
    {
        return (string) ($this->get('reference') ?? '');
    }

    public function getCustomerUid(): ?int
    {
        $uid = $this->get('customer_uid');
        return $uid === null ? null : (int) $uid;
    }

    public function getCustomerName(): string
    {
        return (string) ($this->get('customer_name') ?? '');
    }

    public function getVendorId(): ?int
    {
        $id = $this->get('vendor_id');
        return $id === null ? null : (int) $id;
    }

    public function getStatus(): string
    {
        return (string) ($this->get('status') ?? 'placed');
    }

    public function setStatus(string $status): static
    {
        return $this->set('status', $status);
    }

    public function getFulfilment(): string
    {
        return (string) ($this->get('fulfilment') ?? 'pickup');
    }

    public function getPaymentMethod(): string
    {
        return (string) ($this->get('payment_method') ?? 'cash');
    }

    public function getSubtotalCents(): int
    {
        return (int) ($this->get('subtotal_cents') ?? 0);
    }

    public function getTotalCents(): int
    {
        return (int) ($this->get('total_cents') ?? 0);
    }
}
