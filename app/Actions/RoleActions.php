<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use App\Support\LicensePlanLimits;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RoleActions extends Action
{
    /**
     * Create a new role with associated permissions.
     *
     * @throws ValidationException
     */
    public static function createRoleAsAdmin(array $input)
    {
        $actor = self::assertHasPermission('admin.roles.create');

        $validatedData = Validator::make($input, [
            'name' => 'required|string|unique:roles,name',
            'description' => 'nullable|string',
            'parent_role_id' => 'nullable|exists:roles,id',
            'super_admin' => 'required|boolean',
            'permissions' => 'required_if:super_admin,false|array',
            'permissions.*' => 'string',
        ])->validate();

        $parentRole = null;
        if (! empty($validatedData['parent_role_id'])) {
            $parentRole = Role::find($validatedData['parent_role_id']);
            if (! $parentRole) {
                throw ValidationException::withMessages([
                    'parent_role_id' => ['Parent role not found.'],
                ]);
            }

            $parentRolePermissions = $parentRole->getAllPermissions();
            if (isset($validatedData['permissions'])) {
                $validatedData['permissions'] = array_diff($validatedData['permissions'], $parentRolePermissions);
            }
        }

        self::assertRolePermissionChangesAreAllowed(
            $actor,
            null,
            (bool) $validatedData['super_admin'],
            $validatedData['permissions'] ?? [],
            $parentRole
        );

        $role = Role::create(self::omitNullValues([
            'name' => $validatedData['name'],
            'description' => $validatedData['description'] ?? null,
            'parent_id' => $validatedData['parent_role_id'] ?? null,
            'super_admin' => $validatedData['super_admin'],
        ]));

        if (! $validatedData['super_admin'] && ! empty($validatedData['permissions'])) {
            $permissionsData = array_map(function ($permission) {
                return ['permission' => $permission];
            }, $validatedData['permissions']);

            $role->permissions()->createMany($permissionsData);
        }

        return $role;
    }

    /**
     * Update an existing role and its permissions.
     *
     * @throws ValidationException
     */
    public static function updateRoleAsAdmin(array $input)
    {
        $actor = self::assertHasPermission('admin.roles.update');

        $validatedData = Validator::make($input, [
            'role_id' => 'required|exists:roles,id',
            'parent_role_id' => 'nullable|exists:roles,id|different:role_id',
            'name' => 'sometimes|required|string|unique:roles,name,'.($input['role_id'] ?? 'NULL').',id',
            'description' => 'nullable|string',
            'super_admin' => 'sometimes|required|boolean',
            'permissions' => 'sometimes|required_if:super_admin,false|array',
            'permissions.*' => 'string',
        ])->validate();

        $role = Role::findOrFail($validatedData['role_id']);

        $parentRole = null;
        if (! empty($validatedData['parent_role_id'])) {
            $parentRole = Role::find($validatedData['parent_role_id']);
            if (! $parentRole) {
                throw ValidationException::withMessages([
                    'parent_role_id' => ['Parent role not found.'],
                ]);
            }

            $parentRolePermissions = $parentRole->getAllPermissions();
            if (isset($validatedData['permissions'])) {
                $validatedData['permissions'] = array_diff($validatedData['permissions'], $parentRolePermissions);
            }
        }

        $superAdmin = array_key_exists('super_admin', $validatedData)
            ? (bool) $validatedData['super_admin']
            : (bool) $role->super_admin;

        $directPermissions = array_key_exists('permissions', $validatedData)
            ? $validatedData['permissions']
            : $role->permissions->pluck('permission')->toArray();

        self::assertRolePermissionChangesAreAllowed(
            $actor,
            $role,
            $superAdmin,
            $directPermissions,
            $parentRole ?? $role->parent
        );

        $role->update(self::omitNullValues([
            'name' => $validatedData['name'] ?? null,
            'description' => $validatedData['description'] ?? null,
            'super_admin' => $validatedData['super_admin'] ?? null,
            'parent_id' => $validatedData['parent_role_id'] ?? null,
        ]));

        if (($validatedData['super_admin'] ?? $role->super_admin) === false && ! empty($validatedData['permissions'])) {
            $role->permissions()->delete();

            $permissionsData = array_map(function ($permission) {
                return ['permission' => $permission];
            }, $validatedData['permissions']);
            $role->permissions()->createMany($permissionsData);
        } elseif (array_key_exists('super_admin', $validatedData) && $validatedData['super_admin']) {
            $role->permissions()->delete();
        }

        return $role;
    }

    public static function assignRoleAsAdmin(array $input)
    {
        $actor = self::assertHasPermission('admin.users.manage_roles');

        $validatedData = Validator::make($input, [
            'role_id' => 'required|exists:roles,id',
            'user_id' => 'required|exists:users,id',
            'assigner_id' => 'required|exists:users,id',
        ])->validate();

        $existing = RoleUser::where('role_id', $validatedData['role_id'])
            ->where('user_id', $validatedData['user_id'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'role_id' => ['User already has this role.'],
            ]);
        }

        $role = Role::findOrFail($validatedData['role_id']);

        self::assertCanAssignRole($actor, $role, (int) $validatedData['user_id']);

        $user = User::query()->findOrFail($validatedData['user_id']);

        if ($user->id !== 1 && ! $user->roles()->exists()) {
            $limit = LicensePlanLimits::staffAccountsLimit();
            if ($limit !== null && self::occupiedStaffAccountSeats() >= $limit) {
                throw ValidationException::withMessages([
                    'role_id' => [
                        sprintf(
                            'Your license allows %d staff account(s). Remove a staff role from another user or upgrade your license.',
                            $limit
                        ),
                    ],
                ]);
            }
        }

        return RoleUser::create([
            'role_id' => $validatedData['role_id'],
            'user_id' => $validatedData['user_id'],
            'assigner_id' => $validatedData['assigner_id'],
        ]);
    }

    public static function removeRoleAsAdmin(array $input)
    {
        self::assertHasPermission('admin.users.manage_roles');

        $validatedData = Validator::make($input, [
            'role_id' => 'required|exists:roles,id',
            'user_id' => 'required|exists:users,id',
        ])->validate();

        return RoleUser::where('role_id', $validatedData['role_id'])
            ->where('user_id', $validatedData['user_id'])
            ->delete();
    }

    private static function assertHasPermission(string $permission): User
    {
        $user = auth()->user();

        if (! $user || ! $user->hasPermission($permission)) {
            abort(403);
        }

        return $user;
    }

    private static function assertRolePermissionChangesAreAllowed(
        User $actor,
        ?Role $role,
        bool $superAdmin,
        array $directPermissions,
        ?Role $parentRole
    ): void {
        if ($actor->isPrimaryAdmin()) {
            return;
        }

        if ($role !== null && self::userHasRole($actor, $role)) {
            abort(403, 'You cannot modify a role assigned to you.');
        }

        if ($superAdmin) {
            abort(403, 'Only the primary administrator can grant full access.');
        }

        if ($parentRole !== null) {
            if ($parentRole->super_admin) {
                abort(403, 'Only the primary administrator can use a full access parent role.');
            }

            $invalidParentPermissions = array_diff($parentRole->getAllPermissions(), $actor->getAllPermissions());
            if ($invalidParentPermissions !== []) {
                throw ValidationException::withMessages([
                    'parent_role_id' => ['The selected parent role includes permissions you do not have.'],
                ]);
            }
        }

        $effectivePermissions = self::resolveEffectivePermissions($directPermissions, $parentRole);
        $invalidPermissions = array_diff($effectivePermissions, $actor->getAllPermissions());

        if ($invalidPermissions !== []) {
            throw ValidationException::withMessages([
                'permissions' => ['You cannot grant permissions you do not have.'],
            ]);
        }
    }

    private static function assertCanAssignRole(User $actor, Role $role, int $userId): void
    {
        if ($actor->isPrimaryAdmin()) {
            return;
        }

        if ($userId === $actor->id) {
            abort(403, 'You cannot assign roles to yourself.');
        }

        if ($role->super_admin) {
            abort(403, 'Only the primary administrator can assign full access roles.');
        }

        $invalidPermissions = array_diff($role->getAllPermissions(), $actor->getAllPermissions());
        if ($invalidPermissions !== []) {
            abort(403, 'You cannot assign a role that includes permissions you do not have.');
        }
    }

    private static function userHasRole(User $user, Role $role): bool
    {
        return RoleUser::query()
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private static function resolveEffectivePermissions(array $directPermissions, ?Role $parentRole): array
    {
        if ($parentRole === null) {
            return array_values(array_unique($directPermissions));
        }

        return array_values(array_unique(array_merge($directPermissions, $parentRole->getAllPermissions())));
    }

    /**
     * Seats counted toward {@see LicensePlanLimits::staffAccountsLimit()}: the initial admin (user id 1) counts as one
     * seat when that account exists, plus every other user who has at least one staff role.
     */
    private static function occupiedStaffAccountSeats(): int
    {
        $initialAdminSeat = User::query()->whereKey(1)->exists() ? 1 : 0;

        $nonPrimaryWithRoles = User::query()
            ->where('id', '!=', 1)
            ->whereHas('roles')
            ->count();

        return $initialAdminSeat + $nonPrimaryWithRoles;
    }
}
