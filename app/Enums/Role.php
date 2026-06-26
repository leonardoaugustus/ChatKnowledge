<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Colaborador = 'colaborador';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Colaborador => 'Colaborador',
        };
    }

    /**
     * Get the Flux color token for the role.
     */
    public function color(): string
    {
        return match ($this) {
            self::Admin => 'indigo',
            self::Colaborador => 'zinc',
        };
    }

    /**
     * Get all the permissions for this role.
     *
     * @return array<OrganizationPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => OrganizationPermission::cases(),
            self::Colaborador => [],
        };
    }

    /**
     * Determine if the role has the given permission.
     */
    public function hasPermission(OrganizationPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Get the hierarchy level for this role.
     * Higher numbers indicate higher privileges.
     */
    public function level(): int
    {
        return match ($this) {
            self::Admin => 2,
            self::Colaborador => 1,
        };
    }

    /**
     * Check if this role is at least as privileged as another role.
     */
    public function isAtLeast(Role $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Get the roles that can be assigned to organization members.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
