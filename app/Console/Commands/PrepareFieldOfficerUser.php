<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Explicit development setup; never runs as part of normal database seeding. */
final class PrepareFieldOfficerUser extends Command
{
    protected $signature = 'assocmap:prepare-officer {--email=} {--name=}';

    protected $description = 'Prepare a local test Field Officer using a hidden password prompt';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) || ! $this->input->isInteractive()) {
            $this->error('This command requires local/testing and an interactive password prompt.');

            return self::FAILURE;
        }
        $data = validator([
            'email' => $this->option('email'),
            'password' => $this->secret('Development password', false),
            'password_confirmation' => $this->secret('Confirm password', false),
        ], ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8', 'confirmed']])->validate();
        $user = DB::transaction(function () use ($data): User {
            $role = Role::where('role_name', 'Field Officer')->sole();
            $user = User::where('email', $data['email'])->lockForUpdate()->first();
            if (! $user) {
                $name = validator(['name' => $this->option('name')], ['name' => ['required', 'string', 'max:255']])->validate()['name'];
                $user = new User(['email' => $data['email'], 'name' => $name]);
            }
            $user->role_id = $role->id;
            $user->is_active = true;
            if (! Hash::check($data['password'], $user->password ?? '')) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();

            return $user;
        });
        $count = \App\Models\Association::assignedTo((int) $user->id)->count();
        $this->info($count ? 'Active Field Officer ready. Existing assignments: '.$count : 'The Field Officer account exists, but no association is currently assigned to it.');

        return self::SUCCESS;
    }
}
