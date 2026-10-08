<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        // Check access before reading or changing monitoring records.
        $this->service->authorize($actor);

        return $actor;
    }

    public function index(Request $request)
    {
        $actor = $this->actor($request);

        // FO presentation is separate; shared monitoring write rules remain unchanged.
        if ($actor->role?->role_name === 'Field Officer') {
            return app(
                \App\Http\Controllers\FieldOfficerUser\MonitoringController::class
            )->index($request);
        }

        $filters = $request->validate([
            'type' => [
                'nullable',
                Rule::in(array_keys(MonitoringService::TYPES)),
            ],
            'search' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $type = $filters['type'] ?? 'production';
        $query = $this->service->records($type, $actor);

        if ($filters['search'] ?? null) {
            // Group search conditions to preserve the user's access scope.
            $query->where(function ($query) use ($filters) {
                $search = '%'.$filters['search'].'%';

                $query->where('p.title', 'ilike', $search)
                    ->orWhere('a.name', 'ilike', $search);
            });
        }

        if ($filters['project_id'] ?? null) {
            // Reject project IDs outside the user's authorized scope.
            $projectIsAccessible = $this->service->projects($actor)
                ->where('p.id', $filters['project_id'])
                ->exists();

            abort_unless($projectIsAccessible, 404);

            $query->where('p.id', $filters['project_id']);
        }

        if ($type !== 'materials' && ($filters['year'] ?? null)) {
            $query->where('m.year', $filters['year']);
        }

        return view('shared.monitoring.index', [
            'type' => $type,
            'filters' => $filters,
            'types' => MonitoringService::TYPES,

            'records' => $query
                ->orderByDesc('m.updated_at')
                ->orderByDesc('m.id')
                ->paginate(10)
                ->withQueryString(),

            // Keep archived projects available when filtering history.
            'projects' => $this->service->projects($actor)
                ->select(
                    'p.id',
                    'p.title',
                    'a.name as association_name'
                )
                ->orderBy('p.title')
                ->get(),
        ]);
    }

    public function create(Request $request, string $type)
    {
        return $this->form($request, $type);
    }

    public function edit(Request $request, string $type, int $record)
    {
        return $this->form($request, $type, $record);
    }

    private function form(Request $request, string $type, ?int $id = null)
    {
        $actor = $this->actor($request);

        abort_unless(isset(MonitoringService::TYPES[$type]), 404);

        // Load an existing record only within the user's authorized scope.
        $record = $id === null
            ? null
            : $this->service->records($type, $actor)
                ->where('m.id', $id)
                ->first();

        abort_if($id !== null && ! $record, 404);

        // Archived projects, associations, and materials retain read-only history.
        abort_if($record && (
            $record->project_archived
            || $record->association_archived
            || (
                $type === 'materials'
                && $record->material_archived_at !== null
            )
        ), 404);

        // Only active projects can receive new or corrected monitoring entries.
        // Include the saved production unit so the form can display it.
        $projects = $this->service->projects($actor)
            ->where('p.is_archived', false)
            ->where('a.is_archived', false)
            ->select(
                'p.id',
                'p.title',
                'p.production_unit_code',
                'p.production_unit_spec',
                'a.name as association_name'
            )
            ->orderBy('p.title')
            ->get();

        // Load active materials only when opening a materials form.
        $materials = $type === 'materials'
            ? DB::table('project_materials')
                ->whereIn('project_id', $projects->pluck('id'))
                ->whereNull('archived_at')
                ->orderBy('item_name')
                ->get(['id', 'project_id', 'item_name'])
            : collect();

        return view('shared.monitoring.form', [
            'record' => $record,
            'type' => $type,
            'label' => MonitoringService::TYPES[$type],
            'projects' => $projects,
            'materials' => $materials,

            // Quarter options are needed only for production monitoring.
            'quarters' => $type === 'production'
                ? DB::table('quarters')
                    ->orderBy('id')
                    ->pluck('quarter_name', 'id')
                : collect(),

            // Condition options are needed only for materials monitoring.
            'conditions' => $type === 'materials'
                ? DB::table('statuses')
                    ->whereIn(
                        'status_name',
                        MonitoringService::CONDITIONS
                    )
                    ->pluck('status_name', 'id')
                : collect(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        return $this->save($request, $type);
    }

    public function update(Request $request, string $type, int $record)
    {
        return $this->save($request, $type, $record);
    }

    private function save(Request $request, string $type, ?int $id = null)
    {
        $actor = $this->actor($request);

        abort_unless(isset(MonitoringService::TYPES[$type]), 404);

        try {
            // The updated service validates allowed fields, checks access,
            // and saves the monitoring record and its audit entry.
            $this->service->save(
                $type,
                $request->all(),
                $actor,
                $id
            );
        } catch (\Illuminate\Database\QueryException $error) {
            // Return a safe database error and preserve supported form input.
            return \App\Support\MonitoringErrors::render($error, $request);
        }

        // Modal requests need JSON; ordinary form submissions retain their redirect.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => MonitoringService::TYPES[$type].' monitoring record saved.',
            ]);
        }

        return redirect()
            ->route('monitoring.index', ['type' => $type])
            ->with(
                'success',
                MonitoringService::TYPES[$type].' monitoring record saved.'
            );
    }
}