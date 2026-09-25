<?php

namespace App\Services;

use App\Exceptions\AssociationRuleException;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * AdminUserManagementService
 * app/Services/AdminUserManagementService.php
 *
 * Owns all business logic for the User and Access Control Module
 * (System Administrator only). Controller stays thin - filtering,
 * pagination, RBAC guard rules (last-admin protection), and audit
 * logging all live here per project standards.
 */
class AdminUserManagementService
{
    /** Avoids the role name being a magic string in every guard method. */
    private const ROLE_SYSTEM_ADMIN = 'System Administrator';

    public const ROLES = ['System Administrator', 'Field Officer', 'Association Member'];

    private ?int $adminRoleId = null;

    /**
     * Filtered, sorted, paginated account list for the index table.
     * Each row also carries two computed, non-persisted attributes:
     *   - last_login    derived from audit_logs (no schema change)
     *   - is_last_admin drives the last-admin protection hint/guard
     */
    public function listForIndex(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->select('users.*', 'roles.role_name', 'associations.name as association_name')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->leftJoin('associations', 'associations.id', '=', 'users.association_id');

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'ilike', $term)
                    ->orWhere('users.email', 'ilike', $term);
            });
        }

        if (! empty($filters['role_id'])) {
            $query->where('users.role_id', $filters['role_id']);
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('users.is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('users.is_active', false);
        }

        $sort = in_array($filters['sort'] ?? '', ['name', 'email', 'role_name', 'created_at'], true)
            ? $filters['sort']
            : 'name';
        $query->orderBy($sort === 'role_name' ? 'roles.role_name' : "users.$sort");

        $users = $query->paginate(10)->withQueryString();

        $logins = $this->lastLogins();
        $activeAdminCount = $this->activeAdminCount();

        $users->getCollection()->each(function (User $user) use ($logins, $activeAdminCount) {
            $user->setAttribute('last_login', $logins[$user->id] ?? null);
            $user->setAttribute(
                'is_last_admin',
                $user->role_name === self::ROLE_SYSTEM_ADMIN
                    && $user->is_active
                    && $activeAdminCount <= 1
            );
        });

        return $users;
    }

    /** Summary counts for the dashboard cards above the table. */
    public function summaryCounts(): array
    {
        return [
            'total' => User::count(),
            'admins' => $this->countByRole(self::ROLE_SYSTEM_ADMIN),
            'field_officers' => $this->countByRole('Field Officer'),
            'members' => $this->countByRole('Association Member'),
            'inactive' => User::where('is_active', false)->count(),
        ];
    }

    /** Lookup list for the Add/Edit User role dropdown. */
    public function allRoles()
    {
        return DB::table('roles')->whereIn('role_name', self::ROLES)->orderBy('role_name')->get();
    }

    public function associationOptions()
    {
        // Keep archived associations visible for existing links; the form disables new selection.
        return DB::table('associations')->select('id', 'name', 'is_archived')->orderBy('name')->get();
    }

    public function create(array $data, ?int $actorId): User
    {
        // Let failures leave this transaction so account and audit writes roll back together.
        // The controller catches expected errors only after the transaction finishes.
        return app(AssociationDatabase::class)->run(function () use ($data, $actorId) {
            $this->lockAdministratorChanges();
            $this->requireAdministrator($actorId);
            $role = $this->supportedRole((int) $data['role_id']);
            // Lock the selected association before checking it, preventing archive/save races.
            $association = $this->lockAssociation($role, $data);
            $associationId = $this->associationLink($role, $association);
            $this->requireUniqueEmail($data['email']);
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'],
                'password' => Hash::make($data['password']), 'role_id' => $data['role_id'],
                'association_id' => $associationId, 'is_active' => true,
            ]);
            $this->logAction($actorId, 'CREATE', (string) $user->id,
                "Created user {$user->email}; role {$role}; association ".($associationId ?? 'none').'.');

            return $user;
        });
    }

    public function update(int $userId, array $data, ?int $actorId): User
    {
        // Apply the account update and audit event as one operation, including password resets.
        return app(AssociationDatabase::class)->run(function () use ($userId, $data, $actorId) {
            $this->lockAdministratorChanges();
            $newRole = $this->supportedRole((int) $data['role_id']);
            // Association writes elsewhere lock association before user; use that same order.
            $association = $this->lockAssociation($newRole, $data);
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $this->requireRemainingAdministrator($user, (int) $data['role_id'], $user->is_active);
            if ($newRole !== 'Field Officer') {
                $this->requireReassignment($userId);
            }
            $this->requireAdministrator($actorId);
            $associationId = $this->associationLink($newRole, $association);
            $this->requireUniqueEmail($data['email'], $userId);
            // Preserve the old link and role for the audit trail before changing the account.
            $previousRole = $user->role_id;
            $previousAssociation = $user->association_id;
            $payload = ['name' => $data['name'], 'email' => $data['email'], 'role_id' => $data['role_id'], 'association_id' => $associationId];
            // A blank password keeps the current hash and existing session credentials intact.
            if (! empty($data['password'])) {
                $payload['password'] = Hash::make($data['password']);
            }
            $user->update($payload);
            $this->logAction($actorId, 'UPDATE', (string) $user->id,
                "Updated user {$user->email}; role {$previousRole} to {$user->role_id}; association ".($previousAssociation ?? 'none').' to '.($associationId ?? 'none').'.');

            return $user;
        });
    }

    /** Set the requested status instead of reversing whatever status happens to be stored. */
    public function setActive(int $userId, bool $active, ?int $actorId): bool
    {
        return app(AssociationDatabase::class)->run(function () use ($userId, $active, $actorId) {
            $this->lockAdministratorChanges();
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            if (! $active && $user->is_active && $user->id === $actorId) {
                throw new AssociationRuleException('You cannot deactivate your own account while logged in.');
            }
            $this->requireRemainingAdministrator($user, (int) $user->role_id, $active);
            if (! $active && $user->is_active) {
                $this->requireReassignment($userId);
            }
            $this->requireAdministrator($actorId);
            // Check the actor even for repeated requests. A completed action needs no
            // second write or audit event, and a stale form cannot reverse its outcome.
            if ($user->is_active === $active) {
                return $active;
            }
            $user->is_active = $active;
            $user->save();
            $action = $user->is_active ? 'ACTIVATE' : 'DEACTIVATE';
            $this->logAction($actorId, $action, (string) $user->id, "{$action} user {$user->email}");

            return $user->is_active;
        });
    }

    private function requireReassignment(int $userId): void
    {
        // No association lock is taken here: user-first then association would invert
        // the assignment lock order. The user lock serializes eligibility changes.
        $count = DB::table('associations')->where('field_officer_id', $userId)->where('is_archived', false)->count();
        if ($count > 0) {
            throw new AssociationRuleException("Reassign this officer's {$count} current association(s) in Association Management before changing their role or deactivating the account.");
        }
    }

    private function requireAdministrator(?int $actorId): void
    {
        // The shared role lock serializes this check with all account changes in this service.
        if (! $actorId || ! User::whereKey($actorId)->where('is_active', true)->where('role_id', $this->adminRoleId())->exists()) {
            throw new AssociationRuleException('An active System Administrator must perform this account change.');
        }
    }

    private function supportedRole(int $roleId): string
    {
        // Recheck in the service because callers outside the form also use these methods.
        $role = DB::table('roles')->where('id', $roleId)->value('role_name');
        if (! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages(['role_id' => 'Choose a supported account role.']);
        }

        return $role;
    }

    private function lockAssociation(string $role, array $data): ?object
    {
        return $role === 'Association Member' && ! empty($data['association_id'])
            ? DB::table('associations')->where('id', $data['association_id'])->lockForUpdate()->first()
            : null;
    }

    private function associationLink(string $role, ?object $association): ?int
    {
        if ($role !== 'Association Member') {
            return null; // Removing the shared-account role always removes its association scope.
        }
        // Shared accounts need a current association to determine which records they may access.
        if (! $association || $association->is_archived) {
            throw ValidationException::withMessages(['association_id' => 'Choose a current, non-archived association for this shared account.']);
        }

        return (int) $association->id;
    }

    private function requireUniqueEmail(string $email, ?int $exceptId = null): void
    {
        // Recheck after the shared lock: request validation may predate another completed save.
        if (User::where('email', $email)->when($exceptId, fn ($query) => $query->where('id', '<>', $exceptId))->exists()) {
            throw ValidationException::withMessages(['email' => 'This email address is already used by another account.']);
        }
    }

    private function lockAdministratorChanges(): void
    {
        // Lock one stable row before any target user. Locking only the target allows
        // two administrators to change different accounts using the same old count.
        DB::table('roles')->where('id', $this->adminRoleId())->lockForUpdate()->first();
    }

    private function requireRemainingAdministrator(User $user, int $newRoleId, bool $newActive): void
    {
        if ($user->is_active && (int) $user->role_id === $this->adminRoleId()
            && (! $newActive || $newRoleId !== $this->adminRoleId())
            && $this->activeAdminCount() <= 1) {
            throw new AssociationRuleException('At least one active System Administrator must remain.');
        }
    }

    private function countByRole(string $roleName): int
    {
        return User::whereHas('role', fn ($q) => $q->where('role_name', $roleName))->count();
    }

    private function activeAdminCount(): int
    {
        return User::where('role_id', $this->adminRoleId())->where('is_active', true)->count();
    }

    private function adminRoleId(): int
    {
        return $this->adminRoleId ??= (int) DB::table('roles')
            ->where('role_name', self::ROLE_SYSTEM_ADMIN)
            ->value('id');
    }

    /**
     * Last successful login per user, derived from the existing
     * audit_logs table (module=Auth, action_type=LOGIN). Read-only
     * aggregate query - no schema change required.
     */
    private function lastLogins()
    {
        return DB::table('audit_logs')
            ->select('user_id', DB::raw('MAX(performed_at) as last_login'))
            ->where('module', 'Auth')
            ->where('action_type', 'LOGIN')
            ->groupBy('user_id')
            ->pluck('last_login', 'user_id');
    }

    private function logAction(?int $actorId, string $actionType, string $recordId, string $details): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $actorId,
            'action_type' => $actionType,
            'module' => 'User',
            'record_id' => $recordId,
            'details' => $details,
            'performed_at' => now(),
        ]);
    }
}
