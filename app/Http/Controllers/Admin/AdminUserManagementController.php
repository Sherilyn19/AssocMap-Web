<?php

// app/Http/Controllers/Admin/AdminUserManagementController.php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AssociationDeadlineException;
use App\Exceptions\AssociationRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdminUsersRequest;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Services\AdminUserManagementService;
use App\Support\UserAccountId;
use App\Support\UserManagementErrors;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AdminUserManagementController
 * User and Access Control Module, System Administrator only.
 */
class AdminUserManagementController extends Controller
{
    public function __construct(private readonly AdminUserManagementService $users) {}

    public function index(ListAdminUsersRequest $request): View|Response
    {
        $filters = $request->validated();

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

    public function update(UpdateAdminUserRequest $request, string $user): Response
    {
        $data = $request->validated();

        try {
            $this->users->update(UserAccountId::parse($user), $data, session('auth_user.id'));
        } catch (ValidationException|AssociationRuleException|PDOException|AssociationDeadlineException|ModelNotFoundException|NotFoundHttpException $error) {
            // Reopen this user's edit form without flashing the submitted password.
            return UserManagementErrors::render($error, $request);
        }

        return back()->with('success', 'User account updated successfully.');
    }

    public function activate(Request $request, string $user): Response
    {
        return $this->changeStatus($request, $user, true);
    }

    public function deactivate(Request $request, string $user): Response
    {
        return $this->changeStatus($request, $user, false);
    }

    /** Both routes share error handling, but each fixes the intended final status. */
    private function changeStatus(Request $request, string $user, bool $active): Response
    {
        try {
            $this->users->setActive(UserAccountId::parse($user), $active, session('auth_user.id'));
        } catch (AssociationRuleException|PDOException|AssociationDeadlineException|ModelNotFoundException|NotFoundHttpException $error) {
            // Explain blocked status changes without reporting them as successful saves.
            return UserManagementErrors::render($error, $request);
        }
        $status = $active ? 'active' : 'inactive';

        return back()->with('success', "User account is now {$status}.");
    }
}
