<?php

// app/Policies/MemberApplicationPolicy.php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MemberApplication;
use App\Models\User;

final class MemberApplicationPolicy
{
    /** The global administrative register is separate from scoped member access. */
    public function viewAdminRegister(User $user): bool
    {
        return $user->is_active && $user->role?->role_name === 'System Administrator';
    }

    /** Applications are submitted through the association's shared account. */
    public function create(User $user): bool
    {
        return $user->is_active && $user->role?->role_name === 'Association Member'
            && $user->association_id !== null
            && \App\Models\Association::whereKey($user->association_id)->where('is_archived', false)->exists();
    }

    /** This allows reaching the review form; the service MUST also verify the secret. */
    public function review(User $user, MemberApplication $application): bool
    {
        return $user->is_active && $user->role?->role_name === 'Association Member'
            && $user->association_id !== null
            && (int) $user->association_id === (int) $application->association_id
            && $application->association && !$application->association->is_archived
            && $application->association->representative_member_id !== null;
    }

    public function viewAny(User $user): bool
    {
        if (!$user->is_active) {
            return false;
        }

        return in_array($user->role?->role_name, [
            'System Administrator',
            'Field Officer',
            'Association Member',
        ], true);
    }

    public function view(User $user, MemberApplication $application): bool
    {
        if (!$user->is_active) {
            return false;
        }

        return match ($user->role?->role_name) {
            'System Administrator' => true,
            'Field Officer' => (int) $application->association?->field_officer_id === (int) $user->id,
            'Association Member' => (int) $user->association_id === (int) $application->association_id,
            default => false,
        };
    }
}
