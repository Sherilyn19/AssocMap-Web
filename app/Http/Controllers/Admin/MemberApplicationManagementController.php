<?php

// app/Http/Controllers/Admin/MemberApplicationManagementController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberApplication;
use App\Services\MemberApplicationManagementService;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class MemberApplicationManagementController extends Controller
{
    private const LIST_STATE_KEYS = [
        'search',
        'association_id',
        'status_id',
        'submitted_from',
        'submitted_to',
        'sort',
        'per_page',
        'page',
    ];

    public function __construct(
        private readonly MemberApplicationManagementService $service,
        private readonly SessionUserResolver $sessionUser
    ) {
    }

    public function index(Request $request): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('viewAny', MemberApplication::class);

        $filters = $request->only([
            'search',
            'association_id',
            'status_id',
            'submitted_from',
            'submitted_to',
            'sort',
            'per_page',
        ]);

        return view('admin-pages.admin-member-management.applications', [
            'applications' => $this->service->paginate($filters),
            'summary' => $this->service->summary(),
            'filters' => $filters,
            ...$this->service->filterOptions(),
        ]);
    }

    public function show(Request $request, MemberApplication $application): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('view', $application);

        return view('admin-pages.admin-member-management.application-show', [
            'application' => $this->service->findDetailed($application),
            'backToListUrl' => route('members.applications.index', $this->listState($request)),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function listState(Request $request): array
    {
        $state = [];

        foreach (self::LIST_STATE_KEYS as $key) {
            $value = $request->query($key);

            if (!is_scalar($value) || $value === '') {
                continue;
            }

            $state[$key] = (string) $value;
        }

        if (isset($state['page']) && (!ctype_digit($state['page']) || (int) $state['page'] < 1)) {
            unset($state['page']);
        }

        return $state;
    }
}