<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Provides the association scope for the shared area records pages.
 */
final class AreaRecordAccess
{
    public function __construct(
        private FieldOfficerUserAccess $officerAccess
    ) {}

    public function associations(User $actor): Builder
    {
        abort_unless($actor->is_active, 403);

        if ($actor->role?->role_name === 'Field Officer') {
            return $this->officerAccess->associations($actor);
        }

        abort_unless(
            $actor->role?->role_name === 'Association Member',
            403
        );

        // Ownership comes from the current account, never from URL filters.
        // An account without an association receives no records.
        return Association::query()
            ->where('associations.id', (int) ($actor->association_id ?? 0));
    }
}