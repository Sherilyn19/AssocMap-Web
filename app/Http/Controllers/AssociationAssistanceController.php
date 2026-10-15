<?php

namespace App\Http\Controllers;

use App\Models\Association;
use App\Services\AssociationAssistanceService;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AssociationAssistanceController extends Controller
{
    public function eligibility(Request $request, int $association, SessionUserResolver $resolver, AssociationAssistanceService $service)
    {
        $record = $service->authorized($resolver->resolve($request), $association);
        $counts = $service->pending($association);
        return response()->json([
            'eligible' => !$record->is_archived && $record->assistance_status !== 'terminated' && array_sum($counts) === 0,
            'counts' => $counts,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $association, SessionUserResolver $resolver, AssociationAssistanceService $service)
    {
        $actor = $resolver->resolve($request);
        $service->authorized($actor, $association);
        $data = $request->validate([
            'assistance_status' => ['required', Rule::in(array_keys(Association::ASSISTANCE_STATUSES))],
            'previous_status' => ['nullable', Rule::in(array_keys(Association::ASSISTANCE_STATUSES))],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
            'assessment_date' => ['exclude_unless:assistance_status,terminated', 'required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'assistance_confirmation' => ['exclude_unless:assistance_status,terminated', 'required', 'string'],
        ]);
        $service->update($actor, $association, $data);
        $url = route($actor->role?->role_name === 'System Administrator' ? 'admin.associations.show' : 'officer.associations.show', $association);
        $message = $data['assistance_status'] === 'terminated'
            ? 'Assistance terminated. Association records are now read-only.' : 'Assistance status saved.';
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'redirect_url' => $url])
            : redirect($url)->with('success', $message);
    }
}
