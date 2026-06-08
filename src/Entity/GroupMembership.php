<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;

/**
 * Records that a user belongs to a group (and in what role).
 *
 * The groups package provides Group/GroupType entities but deliberately leaves
 * membership to the application (see WAASEYAA-FRICTION.md). This is that
 * membership table: vendor staff are users with a row linking them to their
 * vendor's `owner_group_id`. Access policies query it to scope vendor_staff to
 * their own vendor's orders and menu.
 */
#[ContentEntityType(id: 'group_membership', label: 'Group membership', description: 'Links a user to a group.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'role')]
final class GroupMembership extends ContentEntityBase
{
    #[Field(type: 'integer', label: 'Group', required: true, description: 'groups package group id.', stored: FieldStorage::Data)]
    public ?int $group_id = null;

    #[Field(type: 'integer', label: 'User', required: true, description: 'user account id (uid).', stored: FieldStorage::Data)]
    public ?int $user_id = null;

    #[Field(label: 'Role', required: true, description: 'owner | staff.', default: 'staff')]
    public string $role = 'staff';

    public function getGroupId(): ?int
    {
        $id = $this->get('group_id');
        return $id === null ? null : (int) $id;
    }

    public function getUserId(): ?int
    {
        $id = $this->get('user_id');
        return $id === null ? null : (int) $id;
    }

    public function getRole(): string
    {
        return (string) ($this->get('role') ?? 'staff');
    }
}
