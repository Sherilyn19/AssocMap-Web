<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Every list uses the same ownership rule; filtering an ID never grants access. */
final class MembershipAccess
{
    public function scope(Builder $query, User $actor): Builder
    {
        if (!$actor->is_active) {
            return $query->whereRaw('1 = 0');
        }

        return match ($actor->role?->role_name) {
            'System Administrator' => $query,
            'Field Officer' => $query->whereHas('association', fn (Builder $association) => $association->where('field_officer_id', $actor->id)),
            'Association Member' => $actor->association_id
                ? $query->where('association_id', $actor->association_id)
                : $query->whereRaw('1 = 0'),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
