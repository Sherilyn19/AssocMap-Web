<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
            ->select('users.*', 'roles.role_name')
            ->join('roles', 'roles.id', '=', 'users.role_id');

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'ilike', $term)
                  ->orWhere('users.email', 'ilike', $term);
            });
        }

        if (!empty($filters['role_id'])) {
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
            'total'          => User::count(),
            'admins'         => $this->countByRole(self::ROLE_SYSTEM_ADMIN),
            'field_officers' => $this->countByRole('Field Officer'),
            'members'        => $this->countByRole('Association Member'),
            'inactive'       => User::where('is_active', false)->count(),
        ];
    }

    /** Lookup list for the Add/Edit User role dropdown. */
    public function allRoles()
    {
        return DB::table('roles')->orderBy('role_name')->get();
    }

    public function create(array $data, ?int $actorId): User
    {
        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'role_id'   => $data['role_id'],
            'is_active' => true,
        ]);

        $this->logAction($actorId, 'CREATE', (string) $user->id, "Created user {$user->email}");

        return $user;
    }

    public function update(int $userId, array $data, ?int $actorId): User
    {
        $user = User::findOrFail($userId);

        $payload = [
            'name'    => $data['name'],
            'email'   => $data['email'],
            'role_id' => $data['role_id'],
        ];

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        $this->logAction($actorId, 'UPDATE', (string) $user->id, "Updated user {$user->email}");

        return $user;
    }

    public function toggleActive(int $userId, ?int $actorId): bool
    {
        $user = User::findOrFail($userId);
        $user->is_active = !$user->is_active;
        $user->save();

        $action = $user->is_active ? 'ACTIVATE' : 'DEACTIVATE';
        $this->logAction($actorId, $action, (string) $user->id, "{$action} user {$user->email}");

        return $user->is_active;
    }

    /**
     * Guard: would this role change leave zero active System
     * Administrators? Checked by the controller BEFORE update().
     */
    public function wouldRemoveLastAdmin(int $userId, int $newRoleId): bool
    {
        $user = User::find($userId);
        if (!$user || $user->role_id !== $this->adminRoleId()) {
            return false;
        }

        return $newRoleId !== $this->adminRoleId() && $this->activeAdminCount() <= 1;
    }

    /**
     * Guard: would deactivating this account leave zero active
     * admins? Checked by the controller BEFORE toggleActive().
     */
    public function wouldDeactivateLastAdmin(int $userId): bool
    {
        $user = User::find($userId);
        if (!$user || $user->role_id !== $this->adminRoleId() || !$user->is_active) {
            return false;
        }

        return $this->activeAdminCount() <= 1;
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
        if (!$actorId) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id'      => $actorId,
            'action_type'  => $actionType,
            'module'       => 'User',
            'record_id'    => $recordId,
            'details'      => $details,
            'performed_at' => now(),
        ]);
    }
}