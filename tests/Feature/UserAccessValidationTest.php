<?php

namespace Tests\Feature;

use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\UserManagementDatabaseTestCase;
use Tests\Support\UserManagementFixture;

/** Exercise status retries, invalid requests and role changes using disposable accounts. */
class UserAccessValidationTest extends UserManagementDatabaseTestCase
{
    public function test_repeated_status_actions_keep_the_requested_state_and_log_only_real_changes(): void
    {
        $this->withSession($this->sessionFor(1))->from('/admin/users');
        foreach (['deactivate' => false, 'activate' => true] as $action => $active) {
            $this->patch('/admin/users/2/'.$action)->assertSessionHas('success');
            $this->patch('/admin/users/2/'.$action)->assertSessionHas('success');
            $this->assertSame($active, DB::table('users')->where('id', 2)->value('is_active'));
            $this->assertSame(1, DB::table('audit_logs')->where('action_type', strtoupper($action))->count());
        }
        // A legacy form must fail safely rather than invert the account's current state.
        $this->patch('/admin/users/2/toggle-active')->assertNotFound();
        $this->assertTrue(DB::table('users')->where('id', 2)->value('is_active'));
        $this->get('/admin/users')->assertOk()->assertSee('/admin/users/2/deactivate', false)->assertDontSee('toggle-active');
    }

    public function test_status_audit_failure_rolls_back_the_change_and_shows_safe_feedback(): void
    {
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_status CHECK (action_type <> 'DEACTIVATE')");
        $this->withSession($this->sessionFor(1))->patchJson('/admin/users/2/deactivate')
            ->assertStatus(503)->assertDontSee('SQLSTATE')->assertDontSee('reject_status');
        $this->assertTrue(DB::table('users')->where('id', 2)->value('is_active'));
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_invalid_filters_recover_to_a_clean_list_and_json_has_field_errors(): void
    {
        $this->withSession($this->sessionFor(1));
        $invalid = ['search' => ['array'], 'role_id' => '999999999999999999999999', 'status' => ['active'], 'sort' => 'password', 'page' => -1];
        $url = '/admin/users?'.http_build_query($invalid);
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        $this->get($url)->assertRedirect('/admin/users')->assertSessionHasErrors(array_keys($invalid));
        $this->get('/admin/users')->assertOk()->assertSee('role="alert"', false)->assertSee('Please correct the account filters.');
        $this->getJson('/admin/users?search='.str_repeat('x', 256).'&page=1000001')->assertUnprocessable()->assertJsonValidationErrors(['search', 'page']);
        $this->getJson('/admin/users?role_id=999')->assertUnprocessable()->assertJsonValidationErrors('role_id');
        $this->get('/admin/users?role_id=2&status=active&sort=email&search=officer&page=1')
            ->assertOk()->assertSee('officer@example.test')->assertDontSee('admin2@example.test');
    }

    public function test_missing_and_malformed_account_ids_return_404_without_writes(): void
    {
        $this->withSession($this->sessionFor(1));
        foreach (['abc', '0', '-1', '99999999999999999999999', '999'] as $id) {
            $this->patchJson('/admin/users/'.$id.'/deactivate')->assertNotFound()->assertDontSee('SQLSTATE');
            $this->putJson('/admin/users/'.$id, ['name' => 'Missing', 'email' => 'missing@example.test', 'role_id' => 2])
                ->assertNotFound()->assertDontSee('SQLSTATE');
        }
        $this->patch('/admin/users/999/activate')->assertNotFound()->assertSee('Account unavailable');
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_unknown_role_cannot_log_in_or_leave_a_successful_login_event(): void
    {
        $roleId = DB::table('roles')->insertGetId(['role_name' => 'Unsupported Role']);
        DB::table('users')->where('id', 2)->update(['role_id' => $roleId]);
        $this->from('/login')->post('/login', ['email' => 'admin2@example.test', 'password' => UserManagementFixture::PASSWORD])
            ->assertRedirect('/login')->assertSessionMissing('auth_user')->assertSessionMissing('_old_input.password');
        $this->get('/login')->assertOk()->assertSee('Your account role is unavailable.');
        $this->assertSame(0, DB::table('audit_logs')->where('action_type', 'LOGIN')->count());
    }

    public function test_existing_unknown_role_sessions_are_cleared_on_login_and_protected_routes(): void
    {
        $saved = $this->sessionFor(2);
        $roleId = DB::table('roles')->insertGetId(['role_name' => 'Unsupported Role']);
        DB::table('users')->where('id', 2)->update(['role_id' => $roleId]);
        foreach (['/login', '/admin/users', '/membership'] as $url) {
            $this->withSession($saved)->get($url)->assertRedirect('/login')->assertSessionMissing('auth_user');
            $this->get('/login')->assertOk();
        }
    }

    public function test_supported_roles_still_log_in_to_their_own_dashboard(): void
    {
        foreach (['admin1@example.test' => '/admin/dashboard', 'officer@example.test' => '/officer/dashboard', 'association@example.test' => '/member/dashboard'] as $email => $dashboard) {
            $this->post('/login', ['email' => $email, 'password' => UserManagementFixture::PASSWORD])
                ->assertRedirect($dashboard)->assertSessionHas('auth_user');
            $this->post('/logout');
        }
        $this->assertSame(3, DB::table('audit_logs')->where('action_type', 'LOGIN')->count());
    }

    public function test_login_lookup_outage_does_not_create_a_session_or_expose_details(): void
    {
        // Simulate the service boundary so an unavailable database cannot abort the fixture transaction.
        $this->mock(AuthService::class)->shouldReceive('findUserWithRole')->once()->andThrow(new RuntimeException('private database detail'));
        $this->post('/login', ['email' => 'admin1@example.test', 'password' => UserManagementFixture::PASSWORD])
            ->assertRedirect('/login')->assertSessionMissing('auth_user')->assertSessionMissing('_old_input.password');
        $this->get('/login')->assertOk()->assertSee('Sign-in is temporarily unavailable.')->assertDontSee('private database detail');
    }
}
