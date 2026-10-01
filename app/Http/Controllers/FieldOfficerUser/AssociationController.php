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
            'status_id' => ['nullable', Rule::in(['archived', ...Status::whereIn('status_name', ['Active', 'Inactive'])->pluck('id')->all()])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
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
        // Archive is separate from operational status and takes precedence in the list.
        if (($filters['status_id'] ?? null) === 'archived') {
            $query->where('is_archived', true);
        } elseif ($filters['status_id'] ?? null) {
            $query->where('is_archived', false)->where('status_id', $filters['status_id']);
        }

        return view('field-officer-user.associations.index', [
            'associations' => $query->orderBy('name')->orderBy('id')->paginate(10)->withQueryString(),
            'filters' => $filters,
            'areas' => AreaUnit::whereIn('id', (clone $scope)->select('area_unit_id'))->orderBy('name')->get(),
            'components' => ProgramComponent::whereIn('id', (clone $scope)->select('program_component_id'))->orderBy('name')->get(),
            'statuses' => Status::whereIn('status_name', ['Active', 'Inactive'])->orderBy('status_name')->get(),
        ]);
    }

    public function show(Request $request, int $association, SessionUserResolver $resolver, FieldOfficerUserAccess $access)
    {
        return view('field-officer-user.associations.show', [
            'association' => $access->associations($resolver->resolve($request))
                ->with(['areaUnit', 'subUnit', 'programComponent', 'fieldOfficer', 'status'])
                ->withCount(['members' => fn ($q) => $q->where('is_archived', false)])->findOrFail($association),
        ]);
    }
}
