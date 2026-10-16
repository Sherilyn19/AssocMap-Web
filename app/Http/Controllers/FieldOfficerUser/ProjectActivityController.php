<?php

declare(strict_types=1);

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\FieldOfficerUserAccess;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

final class ProjectActivityController extends Controller
{
    public function handle(Request $request, SessionUserResolver $resolver, FieldOfficerUserAccess $access)
    {
        $actor = $resolver->resolve($request);
        $project = $access->scope(Project::query(), $actor)->findOrFail($request->route('project'));
        $activityId = $request->route('activity');
        $activity = $activityId ? DB::table('project_activities')->where('project_id', $project->id)->where('id', $activityId)->first() : null;
        abort_if($activityId && !$activity, 404);
        abort_if($project->is_archived || $project->association->is_archived || $project->association->assistance_status === 'terminated', 403);
        if ($request->isMethod('get')) {
            return view('field-officer-user.projects.activity-editor', compact('project', 'activity'));
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$project->implementation_date?->format('Y-m-d')],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'state' => ['required', Rule::in(['Planned', 'Ongoing', 'Completed'])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'project_id' => ['prohibited'],
            'responsible_id' => ['prohibited'],
        ]);
        try {
            DB::transaction(function () use ($actor, $access, $project, $activityId, $data): void {
                $association = $access->lockAssociation($actor, (int) $project->association_id);
                $current = $association->projects()->lockForUpdate()->findOrFail($project->id);
                abort_if($current->is_archived, 403);
                // Revalidate against the current project, not the form's earlier snapshot.
                validator($data, ['starts_on' => ['after_or_equal:'.$current->implementation_date?->format('Y-m-d')]])->validate();
                $values = array_merge($data, ['updated_at' => now()]);
                if ($activityId) {
                    $query = DB::table('project_activities')->where('project_id', $current->id)->where('id', $activityId);
                    abort_unless((clone $query)->lockForUpdate()->first(), 404);
                    $query->update($values);
                    $id = $activityId;
                } else {
                    $id = DB::table('project_activities')->insertGetId(array_merge($values, ['project_id' => $current->id, 'created_at' => now()]));
                }
                DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action_type' => $activityId ? 'UPDATE' : 'CREATE', 'module' => 'Project Activities', 'record_id' => $id, 'details' => json_encode(['project_id' => $current->id, 'changes' => $data], JSON_THROW_ON_ERROR), 'performed_at' => now()]);
            }, 3);
            return response()->json(['message' => 'Activity saved.']);
        } catch (QueryException $exception) {
            report($exception);
            return response()->json(['message' => 'The save could not be confirmed. Reload the project before retrying.'], 503);
        }
    }
}
