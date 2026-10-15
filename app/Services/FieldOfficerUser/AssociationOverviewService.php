<?php

declare(strict_types=1);

namespace App\Services\FieldOfficerUser;

use App\Models\Association;
use App\Models\TrainingParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;
use Throwable;

final class AssociationOverviewService
{
    /**
     * The controller must authorize this association before calling the service.
     * Every query below remains attached to that authorized association.
     */
    public function build(
        Association $association,
        ?int $year,
        bool $includeArchived
    ): array {
        $projects = $association->projects()->getQuery();
        $trainings = $association->trainings()->getQuery();
        $members = $association->members()->getQuery();

        $projectPeriod = $this->period(
            clone $projects,
            'implementation_date',
            $year
        );

        $trainingPeriod = $this->period(
            clone $trainings,
            'date_conducted',
            $year
        );

        $series = [
            'projects' => $this->projectStatuses(
                (clone $projectPeriod)->where('projects.is_archived', false)
            ),
            'attendance' => $this->attendance(
                $association,
                (clone $trainingPeriod)->where('trainings.is_archived', false)
            ),
        ];

        if ($includeArchived) {
            $series['archived_projects'] = $this->projectStatuses(
                (clone $projectPeriod)->where('projects.is_archived', true)
            );

            $series['archived_attendance'] = $this->attendance(
                $association,
                (clone $trainingPeriod)->where('trainings.is_archived', true)
            );
        }

        return [
            'year' => $year,
            'includeArchived' => $includeArchived,
            'years' => $this->years($members, $projects, $trainings),

            // These are current totals, independent of the selected year.
            'totals' => [
                'Official members' => $association->members_count,
                'Current projects' => (clone $projects)
                    ->where('is_archived', false)->count(),
                'Current training records' => (clone $trainings)
                    ->where('is_archived', false)->count(),
                'Public GIS locations' => $association->gisLocations()
                    ->publiclyVisible()->count(),
            ],

            // Counts remain available even if Python cannot run.
            'series' => $series,
            'charts' => $this->charts($series),

            'timeline' => $this->timeline(
                $association,
                $year,
                $includeArchived
            ),

            // Missing dates cannot be assigned to a reporting year.
            'undated' => [
                'Member registrations' => (clone $members)
                    ->where('is_archived', false)
                    ->whereNull('date_registered')->count(),
                'Project implementation dates' => (clone $projects)
                    ->where('is_archived', false)
                    ->whereNull('implementation_date')->count(),
                'Training dates' => (clone $trainings)
                    ->where('is_archived', false)
                    ->whereNull('date_conducted')->count(),
            ],
        ];
    }

    private function period(
        Builder $query,
        string $column,
        ?int $year
    ): Builder {
        if ($year !== null) {
            $query->whereBetween($column, [
                sprintf('%04d-01-01', $year),
                sprintf('%04d-12-31', $year),
            ]);
        }

        return $query;
    }

    private function projectStatuses(Builder $query): array
    {
        return $query
            ->leftJoin('statuses', 'statuses.id', '=', 'projects.status_id')
            ->selectRaw(
                "COALESCE(NULLIF(BTRIM(statuses.status_name), ''), "
                ."'Not recorded') AS label, COUNT(*) AS count"
            )
            ->groupByRaw(
                "COALESCE(NULLIF(BTRIM(statuses.status_name), ''), "
                ."'Not recorded')"
            )
            ->orderBy('label')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->label,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    private function attendance(
        Association $association,
        Builder $trainings
    ): array {
        // Upcoming sessions must not be presented as completed attendance.
        $trainings->whereDate(
            'date_conducted',
            '<=',
            now('Asia/Manila')->toDateString()
        );

        $expression = "CASE WHEN statuses.status_name IN ('Present', 'Absent') "
            ."THEN statuses.status_name ELSE 'Not recorded' END";

        $counts = TrainingParticipant::query()
            ->whereIn('training_id', $trainings->select('trainings.id'))
            ->whereHas('member', fn (Builder $member) => $member
                ->where('association_id', $association->id))
            ->leftJoin(
                'statuses',
                'statuses.id',
                '=',
                'training_participants.attendance_status_id'
            )
            ->selectRaw($expression.' AS label, COUNT(*) AS count')
            ->groupByRaw($expression)
            ->pluck('count', 'label');

        return collect(['Present', 'Absent', 'Not recorded'])
            ->map(fn (string $label): array => [
                'label' => $label,
                'count' => (int) ($counts[$label] ?? 0),
            ])
            ->all();
    }

    private function years(
        Builder $members,
        Builder $projects,
        Builder $trainings
    ): array {
        $years = collect([(int) now('Asia/Manila')->year]);

        foreach ([
            [$members, 'date_registered'],
            [$projects, 'implementation_date'],
            [$trainings, 'date_conducted'],
        ] as [$query, $column]) {
            // Column names are fixed in code, never taken from request input.
            $recorded = (clone $query)
                ->whereNotNull($column)
                ->selectRaw("EXTRACT(YEAR FROM {$column})::integer AS year")
                ->distinct()
                ->pluck('year');

            $years = $years->merge($recorded);
        }

        return $years->map(fn ($year): int => (int) $year)
            ->unique()->sortDesc()->values()->all();
    }

    private function timeline(
        Association $association,
        ?int $year,
        bool $includeArchived
    ): Collection {
        $events = collect();

        $definitions = [
            [
                $association->members()->getQuery(),
                'date_registered',
                'Member registration',
                'membership.members.show',
                ['id', 'first_name', 'middle_name', 'last_name',
                    'date_registered', 'is_archived'],
            ],
            [
                $association->projects()->getQuery(),
                'implementation_date',
                'Project implementation date',
                'officer.projects.show',
                ['id', 'title', 'implementation_date', 'is_archived'],
            ],
            [
                $association->trainings()->getQuery(),
                'date_conducted',
                'Training date',
                'officer.trainings.show',
                ['id', 'title', 'date_conducted', 'is_archived'],
            ],
        ];

        foreach ($definitions as [
            $query, $column, $kind, $route, $columns
        ]) {
            $query = $this->period($query, $column, $year)
                ->whereNotNull($column);

            if (!$includeArchived) {
                $query->where('is_archived', false);
            }

            // At most 36 small records are loaded to produce a 12-item preview.
            $records = $query->select($columns)
                ->orderByDesc($column)->orderByDesc('id')
                ->limit(12)->get();

            foreach ($records as $record) {
                $date = $record->{$column}->format('Y-m-d');

                $events->push([
                    'date' => $date,
                    'kind' => $kind,
                    'name' => $record->title ?? trim(implode(' ', array_filter([
                        $record->first_name,
                        $record->middle_name,
                        $record->last_name,
                    ]))),
                    'url' => route($route, $record->id),
                    'archived' => (bool) $record->is_archived,
                    'future' => $date > now('Asia/Manila')->toDateString(),
                    'key' => $kind.'-'.$record->id,
                ]);
            }
        }

        return $events
            ->sortBy([
                ['date', 'desc'],
                ['key', 'asc'],
            ])
            ->take(12)->values();
    }

    private function charts(array $series): ?array
    {
        $started = hrtime(true);
        $cacheHit = false;

        try {
            $script = base_path(
                'python/field-officer-user/association_overview.py'
            );

            $input = json_encode($series, JSON_THROW_ON_ERROR);
            $version = hash_file('sha256', $script);

            if ($version === false) {
                return null;
            }

            // Fresh authorized database counts determine the cache key.
            // A changed count or Python script produces a different key.
            $key = 'association-overview:charts:'.hash(
                'sha256',
                $version."\n".$input
            );

            // File caching avoids adding requests to the remote database.
            $cache = \Illuminate\Support\Facades\Cache::store('file');

            try {
                $cached = $cache->get($key);
            } catch (Throwable) {
                // Cache failure must not prevent analytics from running.
                $cached = null;
            }

            if (is_array($cached)) {
                $cacheHit = true;

                return $cached;
            }

            $process = new Process([
                (string) config('services.analytics.python', 'python'),
                $script,
            ]);

            $process->setInput($input);
            $process->setTimeout(5);
            $process->mustRun();

            $charts = json_decode(
                $process->getOutput(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (! is_array($charts)) {
                return null;
            }

            try {
                $cache->put($key, $charts, now()->addMinutes(5));
            } catch (Throwable) {
                // Return the calculated result even if caching is unavailable.
            }

            return $charts;
        } catch (Throwable) {
            // Keep recorded counts visible when Python is unavailable.
            return null;
        } finally {
            if (app()->environment('local')) {
                \Illuminate\Support\Facades\Log::debug(
                    'Association overview chart timing',
                    [
                        'cache_hit' => $cacheHit,
                        'elapsed_ms' => round(
                            (hrtime(true) - $started) / 1_000_000,
                            2
                        ),
                    ]
                );
            }
        }
    }
}