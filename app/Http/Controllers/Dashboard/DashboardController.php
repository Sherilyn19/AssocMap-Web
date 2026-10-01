<?php

// app/Http/Controllers/Dashboard/DashboardController.php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function officer(\Illuminate\Http\Request $request, \App\Services\SessionUserResolver $resolver,
        \App\Services\FieldOfficerUserAccess $access, \App\Services\MonitoringService $monitoring): View
    {
        $actor = $resolver->resolve($request);
        $associations = $access->associations($actor)->current();
        $counts = [
            'My Associations' => (clone $associations)->count(),
            'My Members' => \App\Models\Member::whereIn('association_id', (clone $associations)->select('id'))->where('is_archived', false)->count(),
            'Monitoring Records' => 0,
            'Training Records' => \App\Models\Training::whereIn('association_id', (clone $associations)->select('id'))->where('is_archived', false)->count(),
        ];
        foreach (array_keys($monitoring::TYPES) as $type) {
            $counts['Monitoring Records'] += $monitoring->records($type, $actor)->where('a.is_archived', false)->where('p.is_archived', false)->count();
        }

        return view('field-officer-user.dashboard', [
            'actor' => $actor, 'counts' => $counts,
            'associations' => $associations->with(['areaUnit', 'programComponent', 'status'])
                ->withCount(['members' => fn ($q) => $q->where('is_archived', false)])->orderBy('name')->limit(10)->get(),
            'recent' => $monitoring->records('production', $actor)->where('a.is_archived', false)->where('p.is_archived', false)
                ->orderByDesc('m.updated_at')->orderByDesc('m.id')->limit(5)->get(),
        ]);
    }

    public function member(): View
    {
        $user = session('auth_user');

        return view('association-member-user.dashboard', compact('user'));
    }
}
