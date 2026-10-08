<?php

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldOfficerUser\UpdateDeliveryRequest;
use App\Models\Project;

use App\Services\FieldOfficerUserAccess;
use App\Services\ProjectManagementService;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ProjectController extends Controller
{
    public function index(Request $request, SessionUserResolver $resolver, FieldOfficerUserAccess $access)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'association_id' => ['nullable', 'integer', 'min:1'],
            'archive' => ['nullable', Rule::in(['all', 'current', 'archived'])],
            'delivery' => ['nullable', Rule::in(['all', 'missing', 'recorded', 'none'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $actor = $resolver->resolve($request);
        $association = empty($filters['association_id']) ? null : $access->associations($actor)->findOrFail($filters['association_id']);
        $scope = $access->scope(Project::query(), $actor);

        // Stable totals describe all assigned projects, including archived records.
        // A missing delivery date is a data gap, not proof of an overdue delivery.
        $totals = (clone $scope)->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE is_archived = FALSE) AS current')
            ->selectRaw('COUNT(DISTINCT association_id) AS associations')->first();
        $summary = [
            'total' => (int) $totals->total,
            'current' => (int) $totals->current,
            'associations' => (int) $totals->associations,
            'missing' => (clone $scope)->whereHas('materials', fn ($q) => $q->whereNull('delivery_date'))->count(),
        ];
        $associationOptions = $access->associations($actor)->orderBy('name')->get(['id', 'name']);
        $query = (clone $scope)->with(['association', 'status', 'programComponent'])
            ->withCount(['materials', 'materials as recorded_deliveries_count' => fn ($q) => $q->whereNotNull('delivery_date')]);
        if ($filters['association_id'] ?? null) {
            $query->where('association_id', $filters['association_id']);
        }
        if ($filters['search'] ?? null) {
            $query->where('title', 'ilike', '%'.$filters['search'].'%');
        }
        if (in_array($filters['archive'] ?? 'all', ['current', 'archived'], true)) {
            $query->where('is_archived', $filters['archive'] === 'archived');
        }
        // Apply every filter to the same authorized query, never to a global list.
        match ($filters['delivery'] ?? 'all') {
            'missing' => $query->whereHas('materials', fn ($q) => $q->whereNull('delivery_date')),
            'recorded' => $query->whereHas('materials')->whereDoesntHave('materials', fn ($q) => $q->whereNull('delivery_date')),
            'none' => $query->whereDoesntHave('materials'),
            default => null,
        };

        return view('field-officer-user.projects.index', [
            'association' => $association, 'associationOptions' => $associationOptions,
            'summary' => $summary, 'filters' => $filters,
            'projects' => $query->orderBy('title')->orderBy('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function show(Request $request, int $project, SessionUserResolver $resolver, FieldOfficerUserAccess $access)
    {
        $actor = $resolver->resolve($request);
        $project = $access->scope(Project::with(['association', 'status', 'programComponent', 'materials.status']), $actor)->findOrFail($project);
        $trainings = app(\App\Services\ProjectTrainingDetails::class)->forProject($project);

        return view($request->boolean('details') ? 'shared.projects.details' : 'field-officer-user.projects.show', compact('project', 'trainings'));
    }

    public function delivery(UpdateDeliveryRequest $request, int $project, int $material, SessionUserResolver $resolver, ProjectManagementService $service)
    {
        $service->updateDelivery($resolver->resolve($request), $project, $material, $request->validated());

        return redirect()->route('officer.projects.show', $project)->with('success', 'Delivery date updated.');
    }
}
