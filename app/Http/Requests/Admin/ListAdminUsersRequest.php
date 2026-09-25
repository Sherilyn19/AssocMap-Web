<?php

namespace App\Http\Requests\Admin;

use App\Services\AdminUserManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate list inputs before they reach database queries or pagination. */
class ListAdminUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Administrator access is checked by the route middleware.
    }

    public function rules(): array
    {
        // Empty filters are allowed; arrays, unsupported choices and oversized numbers are not.
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role_id' => ['bail', 'nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX, Rule::exists('roles', 'id')->whereIn('role_name', AdminUserManagementService::ROLES)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['name', 'email', 'role_name', 'created_at'])],
            // Keep the page offset within a practical range without accepting integer overflow.
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
