<?php

// app/Http/Controllers/Admin/AdminUserManagementController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Services\AdminUserManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * AdminUserManagementController
 * User and Access Control Module, System Administrator only.
 */
class AdminUserManagementController extends Controller
{
    public function __construct(private readonly AdminUserManagementService $users)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'role_id', 'status', 'sort']);

        return view('admin-pages.admin-user-management.admin-user-index', [
            'users'   => $this->users->listForIndex($filters),
            'roles'   => $this->users->allRoles(),
            'summary' => $this->users->summaryCounts(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $this->users->create($request->validated(), session('auth_user.id'));

        return back()->with('success', 'User account created successfully.');
    }

    public function update(UpdateAdminUserRequest $request, int $user): RedirectResponse
    {
        $data = $request->validated();

        if ($this->users->wouldRemoveLastAdmin($user, (int) $data['role_id'])) {
            return back()->with('error', 'Cannot change this role - at least one active System Administrator must remain.');
        }

        $this->users->update($user, $data, session('auth_user.id'));

        return back()->with('success', 'User account updated successfully.');
    }

    /** Toggle is_active. No hard delete ever. Guards self and last-admin cases. */
    public function toggleActive(int $user): RedirectResponse
    {
        if ((int) session('auth_user.id') === $user) {
            return back()->with('error', 'You cannot deactivate your own account while logged in.');
        }

        if ($this->users->wouldDeactivateLastAdmin($user)) {
            return back()->with('error', 'Cannot deactivate - at least one active System Administrator must remain.');
        }

        $isActive = $this->users->toggleActive($user, session('auth_user.id'));
        $status = $isActive ? 'reactivated' : 'deactivated';

        return back()->with('success', "User account {$status} successfully.");
    }
}