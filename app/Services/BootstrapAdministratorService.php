<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Create the first administrator without a source-known password or account overwrite. */
final class BootstrapAdministratorService
{
    public function create(array $input): User
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['bail', 'required', 'string', 'min:12', 'confirmed', function ($attribute, $value, $fail): void {
                if (strlen($value) > 72) {
                    $fail('The password must not exceed 72 bytes.');
                }
                if (str_contains($value, "\0")) {
                    $fail('The password contains an unsupported null character.');
                }
            }],
        ])->validate();

        return app(AssociationDatabase::class)->run(function () use ($data): User {
            // Share the account service's role lock so concurrent bootstraps cannot both win.
            $role = DB::table('roles')->where('role_name', 'System Administrator')->lockForUpdate()->first();
            if (! $role) {
                throw ValidationException::withMessages(['role' => 'Run the reference seeder before creating the first administrator.']);
            }
            if (User::where('role_id', $role->id)->exists()) {
                throw ValidationException::withMessages(['account' => 'An administrator already exists. Use the existing account-management or recovery process.']);
            }
            if (User::where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'This email already belongs to an account. No account was changed.']);
            }
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']),
                'role_id' => $role->id, 'association_id' => null, 'is_active' => true]);
            // Record the bootstrap explicitly without inventing a pre-existing administrator actor.
            DB::table('audit_logs')->insert(['user_id' => $user->id, 'action_type' => 'CREATE', 'module' => 'User',
                'record_id' => $user->id, 'details' => 'Initial administrator created through the server bootstrap command.', 'performed_at' => now()]);

            return $user;
        });
    }
}
