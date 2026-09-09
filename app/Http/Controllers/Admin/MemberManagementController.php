<?php

// app/Http/Controllers/Admin/MemberManagementController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMemberRequest;
use App\Models\Member;
use App\Services\MemberManagementService;
use App\Services\SessionUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use App\Exceptions\MembershipRuleException;
use Illuminate\Database\QueryException;
use Throwable;

final class MemberManagementController extends Controller
{
    private const LIST_STATE_KEYS = [
        'search',
        'association_id',
        'area_unit_id',
        'sub_unit_id',
        'sex_id',
        'role_in_assoc',
        'beneficiary_type',
        'record_state',
        'registered_from',
        'registered_to',
        'sort',
        'per_page',
        'page',
    ];

    public function __construct(
        private readonly MemberManagementService $service,
        private readonly SessionUserResolver $sessionUser
    ) {
    }

    public function index(\App\Http\Requests\Membership\MemberFiltersRequest $request): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('viewAdminRegister', Member::class);

        $filters = $request->only([
            'search',
            'association_id',
            'area_unit_id',
            'sub_unit_id',
            'sex_id',
            'role_in_assoc',
            'beneficiary_type',
            'record_state',
            'registered_from',
            'registered_to',
            'sort',
            'per_page',
        ]);

        return view('admin-pages.admin-member-management.index', [
            'members' => $this->service->paginate($filters),
            'summary' => $this->service->summary(),
            'analytics' => $this->service->analytics(),
            'filters' => $filters,
            'listState' => $this->listState($request),
            ...$this->service->filterOptions(),
        ]);
    }

    public function show(Request $request, Member $member): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('view', $member);

        return view('admin-pages.admin-member-management.show', [
            'member' => $this->service->findDetailed($member),
            'backToListUrl' => route('members.index', $this->listState($request)),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('update', $member);

        try {
            $this->service->update($member, $request->validated(), $actor->id);

            return back()->with('success', 'Member profile updated successfully.');
        } catch (QueryException $exception) {
            // Constraint races are ordinary conflicts; never display SQL or bindings.
            report($exception);
            $message = $exception->getCode() === '23505'
                ? 'A member with the same name and birthday already exists in this association.'
                : 'The member could not be saved. Please try again.';
            return back()->withInput()->with('edit_member_id', $member->id)->withErrors(['first_name' => $message]);
        } catch (MembershipRuleException $exception) {
            return back()->withInput()->with('edit_member_id', $member->id)->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('edit_member_id', $member->id)->with(
                'error',
                'The member profile could not be updated. Please try again.'
            );
        }
    }

    public function archive(Request $request, Member $member): RedirectResponse
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('archive', $member);

        try {
            $this->service->archive($member, $actor->id);

            return back()->with('success', 'Member archived successfully.');
        } catch (MembershipRuleException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'The member could not be archived. Please try again.'
            );
        }
    }

    /**
     * Keep only scalar, non-empty GET state when linking to a detail record.
     *
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
