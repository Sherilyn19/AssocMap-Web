<?php

declare(strict_types=1);

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class MonitoringController extends Controller
{
    public function __construct(
        private readonly MonitoringService $service
    ) {}

    private function actor(Request $request): User
    {
        $actor = $request->attributes->get('assocmap.actor');

        abort_unless(
            $actor instanceof User
            && $actor->is_active
            && $actor->role?->role_name === 'Field Officer',
            403
        );

        return $actor;
    }

    public function index(Request $request)
    {
        $actor = $this->actor($request);

        $filters = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(MonitoringService::TYPES))],
            'search' => ['nullable', 'string', 'max:255'],
            'association_id' => ['nullable', 'integer', 'min:1'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'quarter_id' => ['nullable', 'integer', 'exists:quarters,id'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'condition' => ['nullable', Rule::in(MonitoringService::CONDITIONS)],
            'observed_from' => ['nullable', 'date_format:Y-m-d'],
            'observed_to' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when(
                    $request->filled('observed_from'),
                    ['after_or_equal:observed_from']
                ),
            ],
            'observation' => ['nullable', Rule::in(['all', 'dated', 'undated'])],
            'archive' => ['nullable', Rule::in(['all', 'current', 'archived'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $type = $filters['type'] ?? 'production';

        $associations = DB::table('associations')
            ->where('field_officer_id', $actor->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        if (!empty($filters['association_id'])) {
            abort_unless(
                $associations->contains('id', (int) $filters['association_id']),
                404
            );
        }

        $projects = $this->service->projects($actor)
            ->select('p.id', 'p.title', 'a.name as association_name')
            ->orderBy('p.title')
            ->get();

        if (!empty($filters['project_id'])) {
            abort_unless(
                $projects->contains('id', (int) $filters['project_id']),
                404
            );
        }

        $query = $this->service->records($type, $actor);

        if (!empty($filters['search'])) {
            // Keep OR search conditions inside the authorized query scope.
            $query->where(function ($query) use ($filters): void {
                $search = '%'.$filters['search'].'%';

                $query->where('p.title', 'ilike', $search)
                    ->orWhere('a.name', 'ilike', $search);
            });
        }

        foreach ([
            'association_id' => 'a.id',
            'project_id' => 'p.id',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        if ($type !== 'materials') {
            if (!empty($filters['year'])) {
                $query->where('m.year', $filters['year']);
            }

            $period = $type === 'production' ? 'quarter_id' : 'month';

            if (!empty($filters[$period])) {
                $query->where('m.'.$period, $filters[$period]);
            }
        } else {
            if (!empty($filters['condition'])) {
                $query->where('s.status_name', $filters['condition']);
            }

            if (!empty($filters['observed_from'])) {
                $query->where('m.observed_on', '>=', $filters['observed_from']);
            }

            if (!empty($filters['observed_to'])) {
                $query->where('m.observed_on', '<=', $filters['observed_to']);
            }

            if (($filters['observation'] ?? 'all') === 'dated') {
                $query->whereNotNull('m.observed_on');
            } elseif (($filters['observation'] ?? 'all') === 'undated') {
                $query->whereNull('m.observed_on');
            }
        }

        $archive = $filters['archive'] ?? 'all';

        if ($archive === 'current') {
            $query->where('p.is_archived', false)
                ->where('a.is_archived', false);

            if ($type === 'materials') {
                $query->whereNull('pm.archived_at');
            }
        } elseif ($archive === 'archived') {
            // "Archived" means any parent makes this history read-only.
            $query->where(function ($query) use ($type): void {
                $query->where('p.is_archived', true)
                    ->orWhere('a.is_archived', true);

                if ($type === 'materials') {
                    $query->orWhereNotNull('pm.archived_at');
                }
            });
        }

        return view('field-officer-user.monitoring.index', [
            'type' => $type,
            'types' => MonitoringService::TYPES,
            'filters' => $filters,
            'associations' => $associations,
            'projects' => $projects,
            'quarters' => DB::table('quarters')
                ->orderBy('id')->pluck('quarter_name', 'id'),
            'records' => $query
                ->orderByDesc('m.updated_at')
                ->orderByDesc('m.id')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function details(Request $request, string $type, int $record)
    {
        $actor = $this->actor($request);
        abort_unless(isset(MonitoringService::TYPES[$type]), 404);

        // Production has dated entries and its own compact achievement presentation.
        if ($type === 'production') {
            return app(ProductionProgressController::class)->show($request, $record);
        }

        $input = $request->validate([
            'history_year' => ['nullable', 'integer', 'between:1900,2100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Resolve the selected record through the current FO assignment first.
        $selected = $this->service->records($type, $actor)
            ->where('m.id', $record)
            ->first();

        abort_unless($selected, 404);

        $history = collect();
        $years = collect();
        $material = null;
        $historyYear = null;

        if ($type === 'income') {
            $base = $this->service->records('income', $actor)
                ->where('p.id', $selected->project_id);

            $years = (clone $base)
                ->reorder()
                ->select('m.year')
                ->distinct()
                ->orderByDesc('m.year')
                ->pluck('m.year');

            $historyYear = (int) ($input['history_year'] ?? $selected->year);

            // At most twelve monthly rows for the chosen project/year.
            $history = (clone $base)
                ->where('m.year', $historyYear)
                ->orderBy('m.month')
                ->orderBy('m.id')
                ->get();
        }

        if ($type === 'materials') {
            // The material is reached through an already authorized project.
            $material = DB::table('project_materials as pm')
                ->leftJoin('statuses as s', 's.id', '=', 'pm.status_id')
                ->where('pm.id', $selected->project_material_id)
                ->where('pm.project_id', $selected->project_id)
                ->select(
                    'pm.item_name',
                    'pm.quantity',
                    'pm.unit',
                    'pm.delivery_date',
                    'pm.archived_at',
                    's.status_name as register_status'
                )
                ->first();

            abort_unless($material, 404);

            $history = $this->service->records('materials', $actor)
                ->where('pm.id', $selected->project_material_id)
                ->orderByRaw('m.observed_on DESC NULLS LAST')
                ->orderByDesc('m.id')
                ->paginate(10)
                ->withQueryString();
        }

        return view('field-officer-user.monitoring.details', [
            'type' => $type,
            'record' => $selected,
            'history' => $history,
            'years' => $years,
            'historyYear' => $historyYear,
            'material' => $material,
        ]);
    }
}