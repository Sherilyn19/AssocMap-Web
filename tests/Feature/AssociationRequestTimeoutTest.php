<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AssociationDeadlineException;
use App\Models\Association;
use App\Services\AssociationDatabase;
use App\Session\AssociationDatabaseSessionHandler;
use App\Support\AssociationRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\AssociationDatabaseTestCase;

/** Real PostgreSQL cancellation and HTTP middleware tests, using only rollback-only fixtures. */
final class AssociationRequestTimeoutTest extends AssociationDatabaseTestCase
{
    private function databaseSession(): void
    {
        DB::statement('CREATE TABLE sessions (id varchar PRIMARY KEY, user_id bigint, ip_address varchar, user_agent text, payload text NOT NULL, last_activity integer NOT NULL)');
        config(['session.driver' => 'database', 'session.lottery' => [0, 100]]);
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $session = app('session')->driver();
        $this->assertInstanceOf(AssociationDatabaseSessionHandler::class, $session->getHandler());
        $session->save();
        $this->withCookie($session->getName(), $session->getId());
    }

    private function delayNextRead(string $table): void
    {
        $armed = true;
        DB::connection()->beforeExecuting(function ($query) use ($table, &$armed): void {
            if ($armed && str_contains($query, '"'.$table.'"') && str_starts_with(strtolower($query), 'select')) {
                $armed = false;
                // Execute a real slow statement inside the target stage's bounded transaction.
                DB::select('SELECT pg_sleep(0.15)');
            }
        });
        config(['association.statement_timeout_ms' => 50]);
    }

    public function test_database_session_round_trip_and_safe_timing_metadata(): void
    {
        $this->databaseSession();
        config(['association.log_successful_requests' => true]);
        Log::spy();
        $response = $this->get('/admin/associations?search=Assigned')->assertOk();
        $reference = $response->headers->get('X-Request-ID');
        Log::shouldHaveReceived('log')->withArgs(function ($level, $message, $data) use ($reference) {
            return $message === 'Association request timing'
                && $data['reference'] === $reference && $data['status'] === 200
                && isset($data['stages_ms']['session_read'], $data['stages_ms']['session_write'], $data['stages_ms']['authentication'])
                && ! str_contains(json_encode($data), 'Assigned')
                && ! str_contains(json_encode($data), 'admin@example.test');
        })->once();
        $this->assertFalse(app(AssociationRequestContext::class)->active);
        $this->assertGreaterThan(0, DB::table('sessions')->count());
    }

    public function test_session_load_timeout_returns_standalone_error_before_any_write(): void
    {
        $this->databaseSession();
        $this->delayNextRead('sessions');
        $this->post('/admin/associations', $this->payload())->assertStatus(503)->assertDontSee('SQLSTATE')->assertDontSee('pg_sleep');
        $context = app(AssociationRequestContext::class);
        $this->assertTrue($context->sessionFailed);
        $this->assertSame('session_read', $context->failedStage);
        $this->assertSame(2, Association::count());
    }

    public function test_session_save_timeout_does_not_claim_a_committed_association_was_rolled_back(): void
    {
        $this->databaseSession();
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION slow_session_save() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(0.15); RETURN NEW; END $$;
            CREATE TRIGGER slow_session BEFORE INSERT OR UPDATE ON sessions FOR EACH ROW EXECUTE FUNCTION slow_session_save();
        SQL);
        config(['association.statement_timeout_ms' => 50]);
        $this->postJson('/admin/associations', $this->payload())->assertStatus(503)
            ->assertJsonPath('mutation_completed', true)->assertJsonPath('outcome_unknown', false)
            ->assertSee('was saved')->assertDontSee('were not completed')->assertDontSee('pg_sleep');
        // The session scope rolls back independently; the business row and audit still exist.
        $this->assertSame(3, Association::count());
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'CREATE')->count());
        $this->assertSame('session_write', app(AssociationRequestContext::class)->failedStage);
    }

    public function test_authentication_timeout_preserves_role_checks_and_recovers_next_request(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->delayNextRead('users');
        $this->getJson('/admin/associations')->assertStatus(503)->assertDontSee('pg_sleep');
        $this->assertSame('authentication', app(AssociationRequestContext::class)->failedStage);
        config(['association.statement_timeout_ms' => 5000]);
        $this->get('/admin/associations')->assertOk();
        $this->assertNull(app(AssociationRequestContext::class)->failedStage);
    }

    public function test_route_binding_is_bounded_and_missing_record_still_returns_404(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->delayNextRead('associations');
        $this->getJson('/admin/associations/1')->assertStatus(503)->assertDontSee('pg_sleep');
        $this->assertSame('route_binding', app(AssociationRequestContext::class)->failedStage);
        config(['association.statement_timeout_ms' => 5000]);
        $this->getJson('/admin/associations/999999')->assertNotFound();
    }

    public function test_request_deadline_rolls_back_before_commit_and_does_not_leak(): void
    {
        $context = app(AssociationRequestContext::class);
        $context->start();
        try {
            app(AssociationDatabase::class)->run(function () {
                DB::table('associations')->where('id', 1)->update(['name' => 'Must roll back']);
                // Expire deterministically after the write, without a flaky elapsed-time sleep.
                config(['association.request_timeout_ms' => 0]);
            });
            $this->fail('The request deadline should stop commit');
        } catch (AssociationDeadlineException) {
            $this->assertSame('operation', $context->failedStage);
        } finally {
            $context->active = false;
        }
        $this->assertSame('Assigned Association', Association::findOrFail(1)->name);
        app(AssociationDatabase::class)->run(fn () => DB::select('SELECT 1'));
        $this->assertFalse($context->active);
    }

    public function test_non_association_session_access_keeps_framework_behavior(): void
    {
        $this->databaseSession();
        config(['association.request_timeout_ms' => 0]);
        $this->get('/login')->assertRedirect()->assertHeaderMissing('X-Request-ID');
        $this->assertFalse(app(AssociationRequestContext::class)->active);
    }
}
