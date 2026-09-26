<?php

namespace Tests\Feature;

use App\Services\AuthService;
use App\Services\LoginAttemptLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Tests\Support\UserManagementDatabaseTestCase;
use Tests\Support\UserManagementFixture;

/** Use real rejected PostgreSQL inserts to verify authentication failure recovery. */
class AuthenticationAuditTest extends UserManagementDatabaseTestCase
{
    public function test_login_audit_is_saved_before_session_is_granted_and_logout_is_recorded(): void
    {
        $service = new AuthService;
        $this->partialMock(AuthService::class)->shouldReceive('writeAuditLog')->once()->andReturnUsing(function ($userId, $actionType, $module, $details) use ($service) {
            // Inspect the boundary that matters: no authenticated session before the audit write.
            $this->assertFalse(session()->has('auth_user'));
            $service->writeAuditLog($userId, $actionType, $module, $details);

            return true;
        });
        $this->post('/login', $this->credentials())->assertRedirect('/officer/dashboard')->assertSessionHas('auth_user.id', 3);
        $event = DB::table('audit_logs')->sole();
        $this->assertSame('LOGIN', $event->action_type);
        $this->assertSame(3, $event->user_id);
        $this->assertSame('Auth', $event->module);
        $this->assertStringNotContainsString(UserManagementFixture::PASSWORD, $event->details);
        $this->app->instance(AuthService::class, $service);
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('auth_user')->assertSessionHas('success');
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'LOGOUT')->count());
    }

    public function test_login_audit_failure_denies_access_preserves_only_email_and_can_recover(): void
    {
        Log::spy();
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_login CHECK (action_type <> 'LOGIN')");
        $this->withSession($this->sessionFor(3))->post('/login', $this->credentials())
            ->assertRedirect('/login')->assertSessionMissing('auth_user')->assertSessionMissing('_old_input.password')
            ->assertSessionHas('_old_input.email', 'officer@example.test')->assertSessionHas('error');
        $this->assertSame(0, DB::table('audit_logs')->count());
        $this->get('/officer/dashboard')->assertRedirect('/login');
        Log::shouldHaveReceived('error')->once()->with('Authentication audit write failed', \Mockery::on(function ($context) {
            return $context['actionType'] === 'LOGIN' && $context['user_id'] === 3
                && $context['sqlstate'] === '23514'
                && ! array_key_exists('message', $context) && ! array_key_exists('email', $context);
        }));
        // The transaction wrapper must restore the connection after the rejected insert.
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT reject_login');
        $this->post('/login', $this->credentials())->assertRedirect('/officer/dashboard');
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'LOGIN')->count());
    }

    public function test_audit_outage_does_not_consume_password_failure_allowance(): void
    {
        $request = Request::create('/login', 'POST', $this->credentials(), server: ['REMOTE_ADDR' => '127.0.0.1']);
        $limiter = app(LoginAttemptLimiter::class);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $limiter->recordFailure($request);
        }
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_login CHECK (action_type <> 'LOGIN')");
        $this->post('/login', $this->credentials())->assertSessionMissing('auth_user')->assertSessionHas('error');
        $this->assertSame(0, $limiter->retryAfter($request));
        $limiter->recordFailure($request);
        $this->assertGreaterThan(0, $limiter->retryAfter($request));
    }

    public function test_logout_audit_failure_still_invalidates_session_and_reports_the_missing_record(): void
    {
        $this->withSession($this->sessionFor(3));
        $oldId = session()->getId();
        $oldToken = session()->token();
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_logout CHECK (action_type <> 'LOGOUT')");
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('auth_user')
            ->assertSessionHas('error', 'You have been logged out, but the logout record could not be confirmed. Please inform the System Administrator.');
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertSame(0, DB::table('audit_logs')->count());
        $this->get('/login')->assertOk()->assertDontSee('SQLSTATE')->assertDontSee('reject_logout');
        $this->get('/officer/dashboard')->assertRedirect('/login');
    }

    public function test_logout_finally_clears_access_even_for_an_unexpected_audit_error(): void
    {
        $this->withoutExceptionHandling();
        $this->mock(AuthService::class)->shouldReceive('writeAuditLog')->once()->andThrow(new LogicException('Synthetic unexpected error'));
        try {
            $this->withSession($this->sessionFor(3))->post('/logout');
            $this->fail('Unexpected programming errors should still reach normal error handling.');
        } catch (LogicException $error) {
            $this->assertFalse(session()->has('auth_user'));
        }
    }

    public function test_guest_and_repeated_logout_do_not_create_extra_audit_events(): void
    {
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('auth_user');
        $this->assertSame(0, DB::table('audit_logs')->count());
        $this->withSession($this->sessionFor(3))->post('/logout')->assertSessionMissing('auth_user');
        $this->post('/logout')->assertSessionMissing('auth_user');
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'LOGOUT')->count());
    }

    public function test_rejected_credentials_and_inactive_accounts_do_not_write_login_events(): void
    {
        $this->from('/login')->post('/login', ['email' => 'officer@example.test', 'password' => 'Incorrect-Password'])
            ->assertSessionMissing('auth_user');
        DB::table('users')->where('id', 3)->update(['is_active' => false]);
        $this->post('/login', $this->credentials())->assertSessionMissing('auth_user');
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    private function credentials(): array
    {
        return ['email' => 'officer@example.test', 'password' => UserManagementFixture::PASSWORD];
    }
}
