<?php

// app/Policies/MemberPolicy.php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

final class MemberPolicy
{
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

    public function view(User $user, Member $member): bool
    {
        if (!$user->is_active) {
            return false;
        }

        return match ($user->role?->role_name) {
            'System Administrator' => true,
            'Field Officer' => (int) $member->association?->field_officer_id === (int) $user->id,
            'Association Member' => (int) $user->association_id === (int) $member->association_id,
            default => false,
        };
    }

    public function update(User $user, Member $member): bool
    {
        return $user->is_active
            && $user->role?->role_name === 'System Administrator';
    }

    public function archive(User $user, Member $member): bool
    {
        return $user->is_active
            && $user->role?->role_name === 'System Administrator';
    }
}