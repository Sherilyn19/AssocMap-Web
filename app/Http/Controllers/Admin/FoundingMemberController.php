<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\MembershipRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFoundingMemberRequest;
use App\Models\Association;
use App\Services\FoundingMemberService;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class FoundingMemberController extends Controller
{
    public function __construct(private readonly SessionUserResolver $resolver, private readonly FoundingMemberService $service) {}

    public function create(Request $request, Association $association): mixed
    {
        Gate::forUser($this->resolver->resolve($request))->authorize('update', $association);
        try {
            $this->service->requireEmptyAssociation($association);
        } catch (MembershipRuleException $error) {
            return redirect()->route('admin.associations.show', $association)->with('error', $error->getMessage());
        }

        return view('admin-user.admin-member-management.founding', [
            'association' => $association, 'sexOptions' => DB::table('sex')->orderBy('sex_name')->get(),
        ]);
    }

    public function store(StoreFoundingMemberRequest $request, Association $association): mixed
    {
        try {
            $this->service->create($this->resolver->resolve($request), $association, $request->validated());
        } catch (MembershipRuleException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }
        app(\App\Support\AssociationRequestContext::class)->mutationCompleted = true;

        return redirect()->route('admin.associations.show', $association)->with('success',
            'Founding member registered. Assign the association representative separately.');
    }
}
