<?php

namespace App\Http\Requests\Admin;

use App\Services\AdminUserManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * app/Http/Requests/Admin/StoreAdminUserRequest.php
 * Validation for creating a new system account.
 * Route access is already restricted to System Administrator by the
 * assocmap.auth middleware, so authorize() just returns true here.
 */
class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Only shared accounts accept an association link. Excluding it for other roles
        // prevents stale or manually submitted values from granting unintended access.
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereIn('role_name', AdminUserManagementService::ROLES)],
            'association_id' => [Rule::excludeIf(! $this->isSharedAccount()), 'required', 'integer', Rule::exists('associations', 'id')->where(fn ($query) => $query->where('is_archived', false))],
        ];
    }

    protected function isSharedAccount(): bool
    {
        $roleId = $this->input('role_id');

        // Reject malformed role values before using them in the association-rule lookup.
        return is_scalar($roleId) && ctype_digit((string) $roleId)
            && DB::table('roles')->where('id', $roleId)->value('role_name') === 'Association Member';
    }
}
