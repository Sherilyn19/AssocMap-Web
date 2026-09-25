<?php

namespace Tests\Feature;

use App\Exceptions\AssociationRuleException;
use App\Services\AdminUserManagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Support\UserManagementDatabaseTestCase;
use Tests\Support\UserManagementFixture;

class UserProvisioningTest extends UserManagementDatabaseTestCase
{
    private function account(array $extra = []): array
    {
        return array_replace(['name' => 'New Shared Account', 'email' => 'new@example.test', 'password' => UserManagementFixture::PASSWORD, 'role_id' => 3, 'association_id' => 1], $extra);
    }

    public function test_create_saves_link_password_and_exactly_one_audit_event(): void
    {
        $this->withSession($this->sessionFor(1))->post('/admin/users', $this->account())->assertSessionHas('success');
        $user = DB::table('users')->where('email', 'new@example.test')->first();
        $this->assertSame(1, $user->association_id);
        $this->assertTrue(Hash::check(UserManagementFixture::PASSWORD, $user->password));
        $this->assertTrue($user->is_active);
        $log = DB::table('audit_logs')->sole();
        $this->assertSame(1, $log->user_id);
        $this->assertSame($user->id, $log->record_id);
        $this->assertSame('CREATE', $log->action_type);
        $this->assertStringContainsString('association 1', $log->details);
        $this->assertStringNotContainsString(UserManagementFixture::PASSWORD, $log->details);
        $this->get('/admin/users')->assertOk()->assertSee('Synthetic Association')->assertSee('new@example.test');
    }

    public function test_missing_nonexistent_and_archived_associations_are_rejected_and_form_recovers(): void
    {
        DB::table('associations')->insert(['name' => 'Archived', 'is_archived' => true]);
        foreach ([null, 999, 2] as $id) {
            $this->withSession($this->sessionFor(1))->post('/admin/users', $this->account(['association_id' => $id]))
                ->assertRedirect('/admin/users')->assertSessionHasErrors('association_id')
                ->assertSessionHas('user_form.mode', 'create')->assertSessionHas('_old_input.name', 'New Shared Account')
                ->assertSessionMissing('_old_input.password');
            $this->get('/admin/users')->assertOk()->assertSee('data-recovery', false)->assertDontSee(UserManagementFixture::PASSWORD);
        }
        $this->assertSame(4, DB::table('users')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_link_changes_and_role_transitions_clear_unintended_association_scope(): void
    {
        DB::table('associations')->insert(['name' => 'Other Association']);
        $this->withSession($this->sessionFor(1))->put('/admin/users/4', $this->payload(4, ['association_id' => 2]))->assertSessionHas('success');
        $this->assertSame(2, DB::table('users')->where('id', 4)->value('association_id'));
        $this->put('/admin/users/4', $this->payload(4, ['role_id' => 2, 'association_id' => 2]))->assertSessionHas('success');
        $this->assertNull(DB::table('users')->where('id', 4)->value('association_id'));
        $this->put('/admin/users/4', $this->payload(4, ['role_id' => 3]))->assertSessionHasErrors('association_id')->assertSessionHas('user_form.id', 4);
        $this->assertSame(2, DB::table('users')->where('id', 4)->value('role_id'));
        $this->put('/admin/users/4', $this->payload(4, ['role_id' => 3, 'association_id' => 1]))->assertSessionHas('success');
        $this->assertSame(1, DB::table('users')->where('id', 4)->value('association_id'));
    }

    public function test_non_shared_accounts_ignore_submitted_association_and_unknown_roles_are_rejected(): void
    {
        $this->withSession($this->sessionFor(1))->post('/admin/users', $this->account(['role_id' => 2, 'association_id' => ['invalid']]))->assertSessionHas('success');
        $this->assertNull(DB::table('users')->where('email', 'new@example.test')->value('association_id'));
        $id = DB::table('roles')->insertGetId(['role_name' => 'Unsupported Role']);
        $this->post('/admin/users', $this->account(['email' => 'unknown@example.test', 'role_id' => $id]))->assertSessionHasErrors('role_id');
        $this->assertFalse(DB::table('users')->where('email', 'unknown@example.test')->exists());
    }

    public function test_audit_failure_rolls_back_creation_and_returns_safe_recoverable_error(): void
    {
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_creation CHECK (action_type <> 'CREATE')");
        $this->withSession($this->sessionFor(1))->post('/admin/users', $this->account())
            ->assertRedirect('/admin/users')->assertSessionHas('error')->assertSessionMissing('success')
            ->assertSessionHas('user_form.mode', 'create')->assertSessionMissing('_old_input.password');
        $this->assertSame(4, DB::table('users')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
        $this->get('/admin/users')->assertOk()->assertSee('could not be confirmed')->assertDontSee('reject_creation')->assertDontSee('SQLSTATE');
    }

    public function test_account_insert_failure_cannot_leave_an_audit_event(): void
    {
        DB::statement("ALTER TABLE users ADD CONSTRAINT reject_new_user CHECK (email <> 'new@example.test')");
        $this->withSession($this->sessionFor(1))->postJson('/admin/users', $this->account())
            ->assertStatus(503)->assertDontSee('SQLSTATE')->assertDontSee('reject_new_user')->assertDontSee(UserManagementFixture::PASSWORD);
        $this->assertSame(4, DB::table('users')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_every_account_write_requires_an_active_administrator_actor(): void
    {
        DB::table('users')->where('id', 2)->update(['is_active' => false]);
        foreach ([null, 999, 2, 3, 4] as $actor) {
            foreach (['create', 'update', 'toggle'] as $operation) {
                try {
                    $service = app(AdminUserManagementService::class);
                    match ($operation) {
                        'create' => $service->create($this->account(), $actor),
                        'update' => $service->update(4, $this->payload(4, ['association_id' => 1]), $actor),
                        'toggle' => $service->setActive(2, false, $actor),
                    };
                    $this->fail('An unauthorized actor must not change accounts.');
                } catch (AssociationRuleException $error) {
                    $this->assertStringContainsString('active System Administrator', $error->getMessage());
                }
            }
        }
        $this->assertSame(4, DB::table('users')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_service_rechecks_duplicate_email_and_archived_association_after_validation(): void
    {
        $service = app(AdminUserManagementService::class);
        $service->create($this->account(), 1);
        try {
            $service->create($this->account(), 1);
            $this->fail('Stale validation must not permit a duplicate account.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('email', $error->errors());
        }
        DB::table('associations')->where('id', 1)->update(['is_archived' => true]);
        try {
            $service->create($this->account(['email' => 'another@example.test']), 1);
            $this->fail('An association archived since validation must be rejected.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('association_id', $error->errors());
        }
        $this->assertSame(5, DB::table('users')->count());
        $this->assertSame(1, DB::table('audit_logs')->count());
    }

    public function test_database_duplicate_email_after_request_validation_has_a_field_error(): void
    {
        // A competing insert after validation reproduces the database uniqueness boundary.
        $original = app(AdminUserManagementService::class);
        $this->mock(AdminUserManagementService::class, function ($mock) use ($original) {
            $mock->shouldReceive('create')->once()->andReturnUsing(function ($data, $actor) use ($original) {
                $original->create($data, $actor);

                // Exercise the real PostgreSQL unique constraint, bypassing only the earlier service recheck.
                return DB::transaction(fn () => DB::table('users')->insert([
                    'name' => 'Race', 'email' => $data['email'], 'password' => Hash::make($data['password']), 'role_id' => 2,
                ]));
            });
        });
        $this->withSession($this->sessionFor(1))->postJson('/admin/users', $this->account())
            ->assertUnprocessable()->assertJsonValidationErrors('email')->assertDontSee('SQLSTATE')->assertDontSee(UserManagementFixture::PASSWORD);
        $this->assertSame(1, DB::table('users')->where('email', 'new@example.test')->count());
        $this->assertSame(1, DB::table('audit_logs')->count());
    }

    public function test_validation_lookup_failure_is_safe_even_before_controller_entry(): void
    {
        DB::statement('ALTER TABLE associations RENAME TO unavailable_associations');
        DB::beginTransaction();
        try {
            $this->withSession($this->sessionFor(1))->postJson('/admin/users', $this->account())
                ->assertStatus(503)->assertDontSee('SQLSTATE')->assertDontSee('unavailable_associations');
        } finally {
            DB::rollBack(); // Recover the outer fixture after deliberately failing a PostgreSQL query.
        }
        $this->assertSame(4, DB::table('users')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_duplicate_update_recovers_edit_form_without_overwriting_account(): void
    {
        $this->withSession($this->sessionFor(1))->put('/admin/users/4', $this->payload(4, ['email' => 'admin1@example.test', 'association_id' => 1, 'password' => 'Secret-Not-Preserved']))
            ->assertSessionHasErrors('email')->assertSessionHas('user_form.mode', 'edit')->assertSessionHas('user_form.id', 4)
            ->assertSessionMissing('_old_input.password');
        $this->assertSame('association@example.test', DB::table('users')->where('id', 4)->value('email'));
        $this->get('/admin/users')->assertOk()->assertDontSee('Secret-Not-Preserved');
    }
}
