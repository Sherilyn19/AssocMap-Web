<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\UserManagementDatabaseTestCase;
use Tests\Support\UserManagementFixture;

class AuthenticationAuditCommitTest extends UserManagementDatabaseTestCase
{
    public function test_login_and_logout_events_are_committed_and_visible_to_another_connection(): void
    {
        // Commit only the disposable schema so another connection can verify persistence.
        DB::commit();
        config(['database.connections.audit_observer' => config('database.connections.pgsql')]);
        try {
            $this->post('/login', ['email' => 'officer@example.test', 'password' => UserManagementFixture::PASSWORD])
                ->assertRedirect('/officer/dashboard')->assertSessionHas('auth_user');
            $this->assertSame(1, DB::connection('audit_observer')->table('audit_logs')->where('action_type', 'LOGIN')->count());
            $this->post('/logout')->assertSessionMissing('auth_user');
            $this->assertSame(1, DB::connection('audit_observer')->table('audit_logs')->where('action_type', 'LOGOUT')->count());
        } finally {
            // Remove the observer and the exact validated test schema on every exit path.
            DB::purge('audit_observer');
            UserManagementFixture::drop($this->schema);
        }
    }
}
