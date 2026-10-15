<?php

declare(strict_types=1);

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Models\AreaUnit;
use App\Models\ProgramComponent;
use App\Models\Status;
use App\Services\FieldOfficerUserAccess;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AssociationController extends Controller
{
    public function index(Request $request, SessionUserResolver $resolver, FieldOfficerUserAccess $access)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'area_unit_id' => ['nullable', 'integer', 'min:1'],
            'program_component_id' => ['nullable', 'integer', 'min:1'],
            // Keep record archival separate from operational status.
            'archive' => ['nullable', Rule::in(['current', 'archived', 'all'])],
            'status_id' => [
                'nullable',
                Rule::in(
                    Status::whereIn('status_name', ['Active', 'Inactive'])
                        ->pluck('id')->all()
                ),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // An unfiltered visit shows current records, including active and inactive ones.
        $filters['archive'] = $filters['archive'] ?? 'current';
        $scope = $access->associations($resolver->resolve($request));
        $query = (clone $scope)->with(['areaUnit', 'programComponent', 'status'])
            ->withCount(['members' => fn ($q) => $q->where('is_archived', false)]);
        if ($filters['search'] ?? null) {
            $query->where('name', 'ilike', '%'.$filters['search'].'%');
        }
        foreach (['area_unit_id', 'program_component_id'] as $field) {
            if ($filters[$field] ?? null) {
                if (! (clone $scope)->where($field, $filters[$field])->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Choose an available filter for your assigned associations.']);
                }
                $query->where($field, $filters[$field]);
            }
        }
        // Archive state and operational status are independent filters.
        if ($filters['archive'] !== 'all') {
            $query->where('is_archived', $filters['archive'] === 'archived');
        }

        if ($filters['status_id'] ?? null) {
            $query->where('status_id', $filters['status_id']);
        }

        return view('field-officer-user.associations.index', [
            'associations' => $query->orderBy('name')->orderBy('id')->paginate(10)->withQueryString(),
            'filters' => $filters,
            'areas' => AreaUnit::whereIn('id', (clone $scope)->select('area_unit_id'))->orderBy('name')->get(),
            'components' => ProgramComponent::whereIn('id', (clone $scope)->select('program_component_id'))->orderBy('name')->get(),
            'statuses' => Status::whereIn('status_name', ['Active', 'Inactive'])->orderBy('status_name')->get(),
        ]);
    }

    public function show(
        Request $request,
        int $association,
        SessionUserResolver $resolver,
        FieldOfficerUserAccess $access
    ) {
        $filters = $request->validate([
            'year' => [
                'nullable',
                'regex:/^(all|[1-9][0-9]{3})$/',
            ],
            'include_archived' => ['nullable', 'boolean'],
        ]);

        $association = $access->associations($resolver->resolve($request))
            ->with([
                'areaUnit', 'subUnit', 'programComponent',
                'fieldOfficer', 'status', 'representative',
            ])
            ->withCount([
                'members' => fn ($query) => $query->where('is_archived', false),
            ])
            ->findOrFail($association);

        $selectedYear = $filters['year']
            ?? (string) now('Asia/Manila')->year;

        $overview = app(
            \App\Services\FieldOfficerUser\AssociationOverviewService::class
        )->build(
            $association,
            $selectedYear === 'all' ? null : (int) $selectedYear,
            $request->boolean('include_archived')
        );

        return response()->view(
            'field-officer-user.associations.show',
            compact('association', 'overview')
        )->header('Cache-Control', 'private, no-store');
    }

    public function details(
        Request $request,
        int $association,
        string $section,
        SessionUserResolver $resolver,
        FieldOfficerUserAccess $access
    ) {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'include_archived' => ['nullable', 'boolean'],
            'public_only' => ['nullable', 'boolean'],
        ]);

        abort_unless(
            in_array(
                $section,
                ['address', 'officer', 'members', 'projects', 'trainings'],
                true
            ),
            404
        );

        // Recheck assignment before every drawer request and pagination request.
        $association = $access->associations($resolver->resolve($request))
            ->with(['areaUnit', 'subUnit', 'fieldOfficer'])
            ->findOrFail($association);

        // Public visibility is meaningful only for the GIS section.
        // Archived locations cannot appear in this scope.
        $publicOnly = $section === 'address'
            && $request->boolean('public_only');

        $includeArchived = !$publicOnly
            && $request->boolean('include_archived');

        $query = match ($section) {
            'address' => $association->gisLocations(),
            'members' => $association->members(),
            'projects' => $association->projects()->with('status'),
            'trainings' => $association->trainings(),
            default => null,
        };

        $records = null;

        // The association was already authorized above.
        // Reuse the public eligibility rules instead of checking the flag alone.
        if ($publicOnly) {
            $query->publiclyVisible();
        }

        if ($query !== null) {
            if (!$includeArchived) {
                $section === 'address'
                    ? $query->whereNull('archived_at')
                    : $query->where('is_archived', false);
            }

            // Keep current records before archives when both are requested.
            if ($section === 'address') {
                $query->orderByRaw('(archived_at IS NOT NULL) ASC')
                    ->orderBy('location_name');
            } else {
                $query->orderBy('is_archived');

                match ($section) {
                    'members' => $query
                        ->orderBy('last_name')->orderBy('first_name'),
                    'projects' => $query->orderBy('title'),
                    default => $query->orderByDesc('date_conducted'),
                };
            }

            $records = $query->orderBy('id')
                ->paginate(8)->withQueryString();
        }

        return response()->view(
            'field-officer-user.associations.drawer',
            compact(
                'association',
                'section',
                'records',
                'includeArchived',
                'publicOnly'
            )
        )->header('Cache-Control', 'private, no-store');
    }
}
