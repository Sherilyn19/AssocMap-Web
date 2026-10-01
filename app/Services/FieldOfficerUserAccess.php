<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Current assignment is the boundary for every officer register and write. */
final class FieldOfficerUserAccess
{
    public function associations(User $actor): Builder
    {
        abort_unless($actor->is_active && $actor->role?->role_name === 'Field Officer', 403);

        return Association::query()->assignedTo((int) $actor->id);
    }

    public function scope(Builder $query, User $actor): Builder
    {
        return $query->whereIn('association_id', $this->associations($actor)->select('id'));
    }

    public function lockAssociation(User $actor, int $id): Association
    {
        // A shared account lock prevents deactivation during the transaction. The parent
        // lock serializes this operation with reassignment and association archival.
        $current = User::with('role')->sharedLock()->findOrFail($actor->id);
        $association = $this->associations($current)->lockForUpdate()->findOrFail($id);
        abort_if($association->is_archived, 403, 'Archived associations cannot be changed.');

        return $association;
    }
}
