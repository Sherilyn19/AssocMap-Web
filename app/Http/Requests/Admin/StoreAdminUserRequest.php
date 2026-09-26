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
            'name' => ['bail', 'required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
            'role_id' => ['bail', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX, Rule::exists('roles', 'id')->whereIn('role_name', AdminUserManagementService::ROLES)],
            'association_id' => [Rule::excludeIf(! $this->isSharedAccount()), 'bail', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX, Rule::exists('associations', 'id')->where(fn ($query) => $query->where('is_archived', false))],
        ];
    }

    protected function isSharedAccount(): bool
    {
        $roleId = $this->input('role_id');

        // Reject malformed role values before using them in the association-rule lookup.
        return is_scalar($roleId) && filter_var($roleId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false
            && DB::table('roles')->where('id', $roleId)->value('role_name') === 'Association Member';
    }

    protected function passwordRules(bool $optional = false): array
    {
        // bcrypt accepts at most 72 bytes. Validate before hashing, including multibyte
        // input, so oversized passwords become field errors instead of server failures.
        return ['bail', $optional ? 'nullable' : 'required', 'string', 'min:8', function ($attribute, $value, $fail): void {
            if (strlen($value) > 72) {
                $fail('The password must not exceed 72 bytes. Use a shorter password.');
            }
            // bcrypt also rejects null bytes; report the problem before saving anything.
            if (str_contains($value, "\0")) {
                $fail('The password contains an unsupported null character.');
            }
        }];
    }
}
