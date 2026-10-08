<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MemberDraft;
use App\Models\User;

final class MemberDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role?->role_name, [
            'Field Officer',
            'System Administrator',
        ], true);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->role?->role_name === 'Field Officer';
    }

    public function view(User $user, MemberDraft $draft): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->role?->role_name === 'System Administrator') {
            return true;
        }

        // Both authorship and current assignment are required.
        return $user->role?->role_name === 'Field Officer'
            && (int) $draft->created_by_user_id === (int) $user->id
            && (int) $draft->association?->field_officer_id === (int) $user->id;
    }

    public function update(User $user, MemberDraft $draft): bool
    {
        return $this->create($user)
            && $this->view($user, $draft)
            && $draft->state === 'draft'
            && $draft->association !== null
            && !$draft->association->is_archived;
    }

    public function cancel(User $user, MemberDraft $draft): bool
    {
        return $this->update($user, $draft);
    }

    public function submit(User $user, MemberDraft $draft): bool
    {
        // Representative readiness is checked again inside the transaction.
        return $this->update($user, $draft);
    }
}