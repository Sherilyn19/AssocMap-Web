<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Training;
use Illuminate\Database\Eloquent\Collection;

final class ProjectTrainingDetails
{
    /** Call only after the project has passed the current user's association scope. */
    public function forProject(Project $project): Collection
    {
        // Training has no project foreign key. Show association records without implying a project assignment.
        return Training::with('programComponent')
            ->where('association_id', $project->association_id)
            ->withCount([
                'participants as registered_count' => fn ($query) => $query->whereHas('member', fn ($member) => $member->where('association_id', $project->association_id)),
                'participants as recorded_count' => fn ($query) => $query
                    ->whereHas('member', fn ($member) => $member->where('association_id', $project->association_id))
                    ->whereHas('attendanceStatus', fn ($status) => $status->whereIn('status_name', ['Present', 'Absent'])),
                'participants as present_count' => fn ($query) => $query
                    ->whereHas('member', fn ($member) => $member->where('association_id', $project->association_id))
                    ->whereHas('attendanceStatus', fn ($status) => $status->where('status_name', 'Present')),
            ])->orderBy('date_conducted')->orderBy('id')->get();

    }
}

