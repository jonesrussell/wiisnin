<?php

declare(strict_types=1);

use App\Entity\ClaimRequest;
use App\Entity\DemandVote;
use App\Entity\GroupMembership;
use App\Entity\MenuItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Review;
use App\Entity\Vendor;
use Waaseyaa\Entity\EntityType;

/**
 * Wiisnin entity types.
 *
 * Field columns are derived from each class's #[Field] attributes at
 * schema:sync; the EntityType only needs id/label/class/keys here. Taxonomy
 * (community, menu_category) uses the taxonomy package's taxonomy_term /
 * taxonomy_vocabulary types, and vendor groups use the groups package's group
 * type — those are registered by their own packages, not here.
 */
return [
    new EntityType(
        id: 'vendor',
        label: 'Vendor',
        class: Vendor::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
    ),
    new EntityType(
        id: 'menu_item',
        label: 'Menu item',
        class: MenuItem::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
    ),
    new EntityType(
        id: 'order',
        label: 'Order',
        class: Order::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'reference'],
    ),
    new EntityType(
        id: 'order_item',
        label: 'Order item',
        class: OrderItem::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name_snapshot'],
    ),
    new EntityType(
        id: 'group_membership',
        label: 'Group membership',
        class: GroupMembership::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'role'],
    ),
    new EntityType(
        id: 'review',
        label: 'Review',
        class: Review::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'author_name'],
    ),
    new EntityType(
        id: 'claim_request',
        label: 'Claim request',
        class: ClaimRequest::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'owner_name'],
    ),
    new EntityType(
        id: 'demand_vote',
        label: 'Demand vote',
        class: DemandVote::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'vendor_slug'],
    ),
];
