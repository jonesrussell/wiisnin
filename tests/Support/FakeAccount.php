<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Waaseyaa\Access\AccountInterface;

/**
 * A lightweight AccountInterface for access-policy tests.
 */
final class FakeAccount implements AccountInterface
{
    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    public function __construct(
        private readonly int $id,
        private readonly array $roles = [],
        private readonly array $permissions = [],
    ) {}

    public function id(): int|string
    {
        return $this->id;
    }

    public function hasPermission(string $permission): bool
    {
        // Mirror User::hasPermission: the administrator role grants everything.
        if (in_array('administrator', $this->roles, true)) {
            return true;
        }
        return in_array($permission, $this->permissions, true);
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isAuthenticated(): bool
    {
        return $this->id !== 0;
    }
}
