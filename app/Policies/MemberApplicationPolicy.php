<?php

// app/Policies/MemberApplicationPolicy.php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MemberApplication;
use App\Models\User;

final class MemberApplicationPolicy
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