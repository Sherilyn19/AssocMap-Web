<?php

// app/Policies/AssociationPolicy.php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Association;
use App\Models\User;

final class AssociationPolicy
{
    public function administer(User $user): bool
    {
        // The global administrator register must never inherit a scoped viewer's permissions.
        return $user->is_active && $user->role?->role_name === 'System Administrator';
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role?->role_name, [
            'System Administrator',
            'Field Officer',
        ], true);
    }

    public function view(User $user, Association $association): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return match ($user->role?->role_name) {
            'System Administrator' => true,
            'Field Officer' => (int) $association->field_officer_id === (int) $user->id,
            'Association Member' => (int) $user->association_id === (int) $association->id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $this->administer($user);
    }

    public function update(User $user, Association $association): bool
    {
        return $this->administer($user);
    }

    public function archive(User $user, Association $association): bool
    {
        return $this->administer($user);
    }

    public function restore(User $user, Association $association): bool
    {
        return $this->administer($user);
    }
}
