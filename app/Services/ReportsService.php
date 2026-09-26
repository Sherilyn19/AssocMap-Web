<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ReportsService
{
    public function overview(array $filters): array
    {
        $year = (int) $filters['year'];
        $associations = DB::table('associations')->where('associations.is_archived', false);
        if (! empty($filters['area_unit_id'])) {
            $associations->where('associations.area_unit_id', $filters['area_unit_id']);
        }
        if (! empty($filters['association_id'])) {
            $associations->where('associations.id', $filters['association_id']);
        }

        // Reuse the same association scope so filters agree across every report section.
        $associationIds = (clone $associations)->select('id');
        $projects = DB::table('projects')->where('is_archived', false)->whereIn('association_id', $associationIds);
        $members = DB::table('members')->where('is_archived', false)->whereIn('association_id', $associationIds);
        $trainings = DB::table('trainings')->where('is_archived', false)->whereIn('association_id', $associationIds)
            ->whereBetween('date_conducted', [$year.'-01-01', $year.'-12-31']);
        $income = $this->monitoring('income', $projects, $year);
        $production = $this->monitoring('production', $projects, $year);

        // Aggregate each register before joining it; members must not multiply project income.
        $rows = (clone $associations)->select('associations.id', 'associations.name')
            ->leftJoin('area_units as area', 'area.id', '=', 'associations.area_unit_id')
            ->addSelect('area.name as municipality')
            ->selectSub((clone $members)->whereColumn('association_id', 'associations.id')->selectRaw('COUNT(*)'), 'members')
            ->selectSub((clone $projects)->whereColumn('association_id', 'associations.id')->selectRaw('COUNT(*)'), 'projects')
            ->selectSub((clone $trainings)->whereColumn('association_id', 'associations.id')->selectRaw('COUNT(*)'), 'trainings')
            ->selectSub((clone $income)->whereColumn('p.association_id', 'associations.id')->selectRaw('COALESCE(SUM(m.gross_income), 0)'), 'income')
            ->orderBy('associations.name')->orderBy('associations.id')->get();

        $monthly = (clone $income)->select('m.month')->selectRaw('SUM(m.gross_income) as total, COUNT(*) as records')
            ->groupBy('m.month')->get()->keyBy('month');
        $months = collect(range(1, 12))->map(fn (int $month): array => [
            'label' => date('F', mktime(0, 0, 0, $month, 1, $year)),
            'total' => (string) ($monthly->get($month)?->total ?? '0'),
            'records' => (int) ($monthly->get($month)?->records ?? 0),
        ]);

        return [
            'rows' => $rows,
            'counts' => ['associations' => $rows->count(), 'members' => $members->count(),
                'projects' => $projects->count(), 'trainings' => $trainings->count()],
            'incomeTotal' => (clone $income)->sum('m.gross_income'),
            'incomeRecords' => (clone $income)->count(),
            'months' => $months,
            'projectStatuses' => (clone $projects)->leftJoin('statuses as s', 's.id', '=', 'projects.status_id')
                ->select('s.status_name')->selectRaw('COUNT(*) as total')->groupBy('s.status_name')->orderBy('s.status_name')->get(),
            // Output units are not stored separately, so show each record without adding unlike quantities.
            'production' => (clone $production)->join('associations as a', 'a.id', '=', 'p.association_id')
                ->join('quarters as q', 'q.id', '=', 'm.quarter_id')
                ->select('a.name as association', 'p.title', 'q.quarter_name', 'm.target_output', 'm.actual_output', 'm.remarks')
                ->orderBy('a.name')->orderBy('p.id')->orderBy('q.id')->orderBy('m.id')->get(),
            'generatedAt' => now('Asia/Manila'),
        ];
    }

    private function monitoring(string $type, Builder $projects, int $year): Builder
    {
        // Match both keys so an inconsistent old record cannot appear under the wrong association.
        return DB::table('monitoring_'.$type.' as m')->join('projects as p', function ($join): void {
            $join->on('p.id', '=', 'm.project_id')->on('p.association_id', '=', 'm.association_id');
        })->whereIn('p.id', (clone $projects)->select('projects.id'))->where('m.year', $year);
    }
}
