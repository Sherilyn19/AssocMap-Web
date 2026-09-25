<?php

// app/Http/Controllers/Admin/AdminUserManagementController.php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AssociationDeadlineException;
use App\Exceptions\AssociationRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Services\AdminUserManagementService;
use App\Support\UserManagementErrors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdminUserManagementController
 * User and Access Control Module, System Administrator only.
 */
class AdminUserManagementController extends Controller
{
    public function __construct(private readonly AdminUserManagementService $users) {}

    public function index(Request $request): View|Response
    {
        $filters = $request->only(['search', 'role_id', 'status', 'sort']);

        try {
            return view('admin-pages.admin-user-management.admin-user-index', [
                'users' => $this->users->listForIndex($filters),
                'roles' => $this->users->allRoles(),
                'associations' => $this->users->associationOptions(),
                'summary' => $this->users->summaryCounts(),
                'filters' => $filters,
            ]);
        } catch (PDOException|AssociationDeadlineException $error) {
            // Show a standalone error page if loading the account register fails.
            return UserManagementErrors::render($error, $request);
        }
    }

    public function store(StoreAdminUserRequest $request): Response
    {
        try {
            $this->users->create($request->validated(), session('auth_user.id'));
        } catch (ValidationException|AssociationRuleException|PDOException|AssociationDeadlineException $error) {
            // The transaction has finished handling rollback before we recover the form.
            // Use the shared handler so HTML and JSON callers receive safe, consistent errors.
            return UserManagementErrors::render($error, $request);
        }

        // Show success only after both the account and its audit event have been saved.
        return back()->with('success', 'User account created successfully.');
    }

    public function update(UpdateAdminUserRequest $request, int $user): Response
    {
        $data = $request->validated();

        try {
            $this->users->update($user, $data, session('auth_user.id'));
        } catch (ValidationException|AssociationRuleException|PDOException|AssociationDeadlineException $error) {
            // Reopen this user's edit form without flashing the submitted password.
            return UserManagementErrors::render($error, $request);
        }

        return back()->with('success', 'User account updated successfully.');
    }

    /** Toggle is_active. No hard delete ever. Guards self and last-admin cases. */
    public function toggleActive(Request $request, int $user): Response
    {
        try {
            $isActive = $this->users->toggleActive($user, session('auth_user.id'));
        } catch (AssociationRuleException|PDOException|AssociationDeadlineException $error) {
            // Explain blocked status changes without reporting them as successful saves.
            return UserManagementErrors::render($error, $request);
        }
        $status = $isActive ? 'reactivated' : 'deactivated';

        return back()->with('success', "User account {$status} successfully.");
    }
}
