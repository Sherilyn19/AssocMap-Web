<?php

// app/Http/Controllers/Dashboard/DashboardController.php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AreaUnit;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\Project;
use App\Models\Training;
use App\Services\FieldOfficerUserAccess;
use App\Services\GisReadService;
use App\Services\MonitoringService;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function officer(
        Request $request,
        SessionUserResolver $resolver,
        FieldOfficerUserAccess $access,
        MonitoringService $monitoring,
        GisReadService $gis
    ): View {
        $actor = $resolver->resolve($request);
        $assigned = $access->associations($actor)->current();
        $assignedIds = (clone $assigned)->select('id');

        // Count only this officer's associations, even in shared municipalities.
        $areas = AreaUnit::whereIn(
            'id',
            (clone $assigned)->select('area_unit_id')
        )->withCount([
            'associations' => fn ($query) =>
                $query->whereIn('id', clone $assignedIds),
        ])->orderBy('name')->get();

        $counts = [
            'My Associations' => (clone $assigned)->count(),
            'My Members' => Member::whereIn('association_id', clone $assignedIds)
                ->where('is_archived', false)->count(),
            'Monitoring Records' => 0,
            'Training Records' => Training::whereIn('association_id', clone $assignedIds)
                ->where('is_archived', false)->count(),
        ];

        foreach (array_keys(MonitoringService::TYPES) as $type) {
            $counts['Monitoring Records'] += $monitoring->records($type, $actor)
                ->where('a.is_archived', false)
                ->where('p.is_archived', false)
                ->count();
        }

        $pendingApplications = MemberApplication::whereIn(
            'association_id',
            clone $assignedIds
        )->whereHas(
            'status',
            fn ($query) => $query->where('status_name', 'Pending')
        )->count();

        $projectCount = Project::whereIn('association_id', clone $assignedIds)
            ->where('is_archived', false)->count();

        $associations = (clone $assigned)
            ->with(['areaUnit', 'programComponent', 'status'])
            ->withCount([
                'members' => fn ($query) => $query->where('is_archived', false),
            ])
            ->orderBy('name')->limit(10)->get();

        $recent = $monitoring->records('production', $actor)
            ->where('a.is_archived', false)
            ->where('p.is_archived', false)
            ->orderByDesc('m.updated_at')
            ->orderByDesc('m.id')->limit(3)->get();

        $trainings = Training::whereIn('association_id', clone $assignedIds)
            ->where('is_archived', false)
            ->with('association:id,name')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')->limit(3)->get();

        // Reuse the existing coordinate validation and officer access rules.
        // The viewer returns at most 200 locations; the page labels this limit.
        $mapData = $gis->locations([], $actor);

        return view('field-officer-user.dashboard', compact(
            'actor', 'counts', 'areas', 'pendingApplications',
            'projectCount', 'associations', 'recent', 'trainings', 'mapData'
        ));
    }
}