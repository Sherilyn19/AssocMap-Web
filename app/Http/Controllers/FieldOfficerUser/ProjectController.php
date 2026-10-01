<?php

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldOfficerUser\UpdateDeliveryRequest;
use App\Models\Project;

use App\Services\FieldOfficerUserAccess;
use App\Services\ProjectManagementService;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;

final class ProjectController extends Controller
{
    public function index(Request $request, SessionUserResolver $resolver, FieldOfficerUserAccess $access)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'association_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1']]);
        $actor = $resolver->resolve($request);
        $association = empty($filters['association_id']) ? null : $access->associations($actor)->findOrFail($filters['association_id']);
        $query = $access->scope(Project::with(['association', 'status', 'programComponent']), $actor);
        if ($filters['association_id'] ?? null) {
            $query->where('association_id', $filters['association_id']);
        }
        if ($filters['search'] ?? null) {
            $query->where('title', 'ilike', '%'.$filters['search'].'%');
        }

        return view('field-officer-user.projects.index', ['association' => $association, 'projects' => $query->orderBy('title')->orderBy('id')->paginate(10)->withQueryString()]);
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
