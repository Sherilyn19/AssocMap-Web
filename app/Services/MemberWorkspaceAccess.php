<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Reads use the current account's association, never a request-provided owner. */
final class MemberWorkspaceAccess
{
    public function association(User $actor): ?Association
    {
        abort_unless($actor->is_active && $actor->role?->role_name === 'Association Member', 403);
        return Association::with(['areaUnit', 'subUnit', 'programComponent', 'fieldOfficer', 'representative', 'status'])->find($actor->association_id ?? 0);
    }

    public function scope(Builder $query, ?Association $association): Builder
    {
        return $query->where('association_id', $association?->id ?? 0);
    }

    public function production(?Association $association): \Illuminate\Database\Query\Builder
    {
        // Verify both recorded ownership and the parent project's association.
        return DB::table('monitoring_production as m')->join('projects as p', function ($join): void {
            $join->on('p.id', '=', 'm.project_id')->on('p.association_id', '=', 'm.association_id');
        })->join('quarters as q', 'q.id', '=', 'm.quarter_id')->where('m.association_id', $association?->id ?? 0)
            ->select('m.id', 'm.year', 'm.target_output', 'm.actual_output', 'm.remarks', 'm.updated_at',
                'p.title as project_title', 'p.is_archived as project_archived', 'q.quarter_name');
    }
}
