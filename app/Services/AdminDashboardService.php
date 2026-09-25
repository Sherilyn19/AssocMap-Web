<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class AdminDashboardService
{
    public function overview(): array
    {
        // Match each register's current-record scope, independently of operational status.
        $pending = DB::table('member_applications as applications')
            ->join('statuses', 'statuses.id', '=', 'applications.status_id')
            ->where('statuses.status_name', 'Pending');

        // Scalar aggregates avoid transferring registers or multiplying totals through joins.
        $counts = DB::query()->selectSub(
            DB::table('associations')->where('is_archived', false)->selectRaw('COUNT(*)'), 'associations'
        )->selectSub(
            DB::table('members')->where('is_archived', false)->selectRaw('COUNT(*)'), 'members'
        )->selectSub(
            DB::table('projects')->where('is_archived', false)->selectRaw('COUNT(*)'), 'projects'
        )->selectSub((clone $pending)->selectRaw('COUNT(*)'), 'pending')->first();

        $projectStatuses = ['Planned' => 0, 'Ongoing' => 0, 'Completed' => 0, 'Other / unspecified' => 0];
        $groups = DB::table('projects')
            ->leftJoin('statuses', 'statuses.id', '=', 'projects.status_id')
            ->where('projects.is_archived', false)
            ->select('statuses.status_name')->selectRaw('COUNT(*) as total')
            ->groupBy('statuses.status_name')->get();
        foreach ($groups as $group) {
            $label = in_array($group->status_name, ['Planned', 'Ongoing', 'Completed'], true)
                ? $group->status_name : 'Other / unspecified';
            $projectStatuses[$label] += (int) $group->total;
        }

        return [
            'counts' => array_map('intval', (array) $counts),
            'projectStatuses' => $projectStatuses,
            'pendingStatusId' => DB::table('statuses')->where('status_name', 'Pending')->value('id'),
            'pendingApplications' => (clone $pending)
                ->leftJoin('associations', 'associations.id', '=', 'applications.association_id')
                ->orderByRaw('applications.created_at ASC NULLS LAST')->orderBy('applications.id')
                ->limit(5)->get(['applications.id', 'applications.first_name', 'applications.last_name',
                    'applications.created_at', 'associations.name as association_name',
                    'associations.is_archived as association_archived']),
            'recentAssociations' => DB::table('associations')
                ->leftJoin('area_units', 'area_units.id', '=', 'associations.area_unit_id')
                ->leftJoin('statuses', 'statuses.id', '=', 'associations.status_id')
                ->where('associations.is_archived', false)
                ->orderByRaw('associations.created_at DESC NULLS LAST')->orderByDesc('associations.id')
                ->limit(5)->get(['associations.id', 'associations.name', 'associations.created_at',
                    'area_units.name as municipality', 'statuses.status_name']),
            'coverage' => DB::table('area_units')
                ->join('associations', function (JoinClause $join): void {
                    $join->on('associations.area_unit_id', '=', 'area_units.id')
                        ->where('associations.is_archived', false);
                })
                ->select('area_units.id', 'area_units.name')->selectRaw('COUNT(*) as total')
                ->groupBy('area_units.id', 'area_units.name')
                ->orderByDesc('total')->orderBy('area_units.name')->orderBy('area_units.id')
                ->limit(5)->get(),
            'generatedAt' => now(),
        ];
    }
}
