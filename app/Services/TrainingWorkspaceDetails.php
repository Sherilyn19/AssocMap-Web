<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Training;

final class TrainingWorkspaceDetails
{
    /** The controller must authorize the training before requesting its association data. */
    public function forTraining(Training $training): array
    {
        // Keep unmatched attendance statuses in the total so missing records never inflate progress.
        $attendance = $training->participants()
            ->whereHas('member', fn ($query) => $query->where('association_id', $training->association_id))
            ->leftJoin('statuses', 'statuses.id', '=', 'training_participants.attendance_status_id')
            ->selectRaw('statuses.status_name, count(*) as total')
            ->groupBy('statuses.status_name')->pluck('total', 'status_name');

        return [
            'attendance' => $attendance,
            // There is no project assignment on a training; label these as association projects.
            'projects' => Project::with(['status', 'programComponent'])
                ->where('association_id', $training->association_id)->orderBy('title')->orderBy('id')->get(),
        ];
    }
}
