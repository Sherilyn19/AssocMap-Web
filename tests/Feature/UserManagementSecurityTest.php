<?php

namespace Tests\Feature;

use App\Exceptions\AssociationRuleException;
use App\Services\AdminUserManagementService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\UserManagementDatabaseTestCase;
use Tests\Support\UserManagementFixture;

class UserManagementSecurityTest extends UserManagementDatabaseTestCase
{
    public function test_last_active_admin_cannot_be_demoted_or_deactivated_through_service(): void
    {
        DB::table('users')->where('id', 2)->update(['is_active' => false]);
        foreach (['demote', 'deactivate'] as $operation) {
            try {
                $service = app(AdminUserManagementService::class);
                $operation === 'demote' ? $service->update(1, $this->payload(1, ['role_id' => 2]), 2) : $service->setActive(1, false, 2);
                $this->fail('The last active administrator must remain.');
            } catch (AssociationRuleException $error) {
                $this->assertStringContainsString('at least one', strtolower($error->getMessage()));
            }
        }
        $this->assertSame(1, DB::table('users')->where('role_id', 1)->where('is_active', true)->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_admin_self_deactivation_is_rejected_by_service_and_endpoint(): void
    {
        $this->withSession($this->sessionFor(1))->from('/admin/users')->patch('/admin/users/1/deactivate')
            ->assertRedirect('/admin/users')->assertSessionHas('error', 'You cannot deactivate your own account while logged in.');
        $this->assertTrue(DB::table('users')->where('id', 1)->value('is_active'));
        $this->expectException(AssociationRuleException::class);
        app(AdminUserManagementService::class)->setActive(1, false, 1);
    }

    public function test_inactive_admin_can_be_demoted_when_one_active_admin_remains(): void
    {
        DB::table('users')->where('id', 2)->update(['is_active' => false]);
        app(AdminUserManagementService::class)->update(2, $this->payload(2, ['role_id' => 2]), 1);
        $this->assertSame(2, DB::table('users')->where('id', 2)->value('role_id'));
    }

    public function test_assigned_officer_still_requires_reassignment_for_both_changes(): void
    {
        $this->withSession($this->sessionFor(1))->from('/admin/users');
        $this->patch('/admin/users/3/deactivate')->assertSessionHas('error');
        $this->put('/admin/users/3', $this->payload(3, ['role_id' => 1]))->assertSessionHas('error');
        $officer = DB::table('users')->where('id', 3)->first();
        $this->assertTrue($officer->is_active);
        $this->assertSame(2, $officer->role_id);
    }

    public function test_password_reset_revokes_two_existing_sessions_but_new_login_works(): void
    {
        $this->post('/login', ['email' => 'officer@example.test', 'password' => UserManagementFixture::PASSWORD])->assertRedirect('/officer/dashboard');
        $first = ['auth_user' => session('auth_user')];
        $this->post('/logout');
        $this->post('/login', ['email' => 'officer@example.test', 'password' => UserManagementFixture::PASSWORD])->assertRedirect('/officer/dashboard');
        $second = ['auth_user' => session('auth_user')];

        $this->withSession($this->sessionFor(1))->from('/admin/users')
            ->put('/admin/users/3', $this->payload(3, ['password' => 'Replacement-Password-2026']))->assertSessionHas('success');
        foreach ([$first, $second] as $saved) {
            $this->withSession($saved)->get('/officer/dashboard')->assertRedirect('/login')->assertSessionMissing('auth_user');
        }
        $this->post('/login', ['email' => 'officer@example.test', 'password' => UserManagementFixture::PASSWORD])->assertSessionMissing('auth_user');
        $this->post('/login', ['email' => 'officer@example.test', 'password' => 'Replacement-Password-2026'])->assertRedirect('/officer/dashboard');
        $this->get('/officer/dashboard')->assertOk();
        $this->assertArrayNotHasKey('password', session('auth_user'));
        $this->assertNotSame(DB::table('users')->where('id', 3)->value('password'), session('auth_user.credential_fingerprint'));
    }

    public function test_blank_password_preserves_sessions_and_resetting_same_password_revokes_them(): void
    {
        $saved = $this->sessionFor(3);
        app(AdminUserManagementService::class)->update(3, $this->payload(3, ['password' => null]), 1);
        $this->withSession($saved)->get('/officer/dashboard')->assertOk();
        app(AdminUserManagementService::class)->update(3, $this->payload(3, ['password' => UserManagementFixture::PASSWORD]), 1);
        $this->withSession($saved)->get('/officer/dashboard')->assertRedirect('/login');
    }

    public function test_failed_password_reset_rolls_back_password_and_preserves_session(): void
    {
        $saved = $this->sessionFor(3);
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_update CHECK (action_type <> 'UPDATE')");
        try {
            app(AdminUserManagementService::class)->update(3, $this->payload(3, ['password' => 'Must-Not-Be-Saved']), 1);
            $this->fail('The audit failure must roll back the reset.');
        } catch (QueryException $error) {
            $this->withSession($saved)->get('/officer/dashboard')->assertOk();
        }
    }

    public function test_deactivation_and_role_change_apply_to_existing_sessions(): void
    {
        $saved = $this->sessionFor(2);
        app(AdminUserManagementService::class)->update(2, $this->payload(2, ['role_id' => 2]), 1);
        $this->withSession($saved)->get('/admin/users')->assertRedirect('/officer/dashboard');
        $this->get('/officer/dashboard')->assertOk();
        app(AdminUserManagementService::class)->setActive(2, false, 1);
        $this->get('/officer/dashboard')->assertRedirect('/login')->assertSessionMissing('auth_user');
        $this->post('/login', ['email' => 'admin2@example.test', 'password' => UserManagementFixture::PASSWORD])->assertSessionMissing('auth_user');
    }

    public function test_legacy_or_tampered_sessions_must_log_in_again_including_on_login_page(): void
    {
        foreach (['/officer/dashboard', '/login'] as $url) {
            $this->withSession(['auth_user' => ['id' => 3, 'role_name' => 'Field Officer']])->get($url)
                ->assertRedirect('/login')->assertSessionMissing('auth_user');
            $saved = $this->sessionFor(3);
            $saved['auth_user']['credential_fingerprint'] = 'invalid';
            $this->withSession($saved)->get($url)->assertRedirect('/login')->assertSessionMissing('auth_user');
        }
    }

    public function test_all_account_endpoints_reject_non_administrators(): void
    {
        foreach ([3 => '/officer/dashboard', 4 => '/member/dashboard'] as $id => $destination) {
            $this->withSession($this->sessionFor($id));
            $this->get('/admin/users')->assertRedirect($destination);
            $this->post('/admin/users', [])->assertRedirect($destination);
            $this->put('/admin/users/1', $this->payload(1))->assertRedirect($destination);
            $this->patch('/admin/users/1/deactivate')->assertRedirect($destination);
            $this->patch('/admin/users/1/activate')->assertRedirect($destination);
        }
        $this->assertSame(0, DB::table('audit_logs')->count());
    }
}
