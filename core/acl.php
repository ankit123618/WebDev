<?php

declare(strict_types=1);

namespace core;

/**
 * Centralizes role normalization and permission lookups for the application.
 *
 * Controllers use it to decide which authenticated users may access each feature.
 */
class acl
{
    private const ROLE_PERMISSIONS = [
        'user' => [
            'dashboard.view',
            'profile.security.manage',
            'uploads.manage',
        ],
        'manager' => [
            'dashboard.view',
            'profile.security.manage',
            'uploads.manage',
            'admin.panel.view',
        ],
        'admin' => [
            'dashboard.view',
            'profile.security.manage',
            'uploads.manage',
            'admin.panel.view',
            'admin.users.manage',
            'admin.system.manage',
        ],
    ];

    /**
     * Normalizes a role string and falls back to the base user role when unknown.
     */
    public function normalizeRole(?string $role): string
    {
        $normalized = strtolower(trim((string) $role));

        if (!array_key_exists($normalized, self::ROLE_PERMISSIONS)) {
            return 'user';
        }

        return $normalized;
    }

    /**
     * Returns the list of supported role names.
     */
    public function roles(): array
    {
        return array_keys(self::ROLE_PERMISSIONS);
    }

    /**
     * Returns every permission assigned to the given role.
     */
    public function permissionsForRole(?string $role): array
    {
        $role = $this->normalizeRole($role);

        return self::ROLE_PERMISSIONS[$role];
    }

    /**
     * Checks whether a role includes a specific permission.
     */
    public function can(?string $role, string $permission): bool
    {
        return in_array($permission, $this->permissionsForRole($role), true);
    }
}
