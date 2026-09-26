<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\ProjectManagementService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\UserManagementDatabaseTestCase;

class AuditLogTest extends UserManagementDatabaseTestCase
{
    private function entry(array $attributes = []): AuditLog
    {
        return AuditLog::create(array_replace(['user_id' => 1, 'action_type' => 'CREATE', 'module' => 'User', 'details' => 'Created an account.', 'performed_at' => '2026-09-25 16:00:00'], $attributes));
    }

    public function test_only_current_active_administrators_can_view_history(): void
    {
        $this->entry();
        $this->get('/admin/audit-logs')->assertRedirect('/login');
        $this->withSession($this->sessionFor(3))->get('/admin/audit-logs')->assertRedirect('/officer/dashboard');
        $this->withSession($this->sessionFor(4))->get('/admin/audit-logs')->assertRedirect('/member/dashboard');
        $this->withSession($this->sessionFor(1))->get('/admin/audit-logs')->assertOk()->assertSee('Created an account.');
        DB::table('users')->where('id', 1)->update(['role_id' => 2]);
        $this->get('/admin/audit-logs')->assertRedirect('/officer/dashboard');
        DB::table('users')->where('id', 2)->update(['is_active' => false]);
        $this->withSession($this->sessionFor(2))->get('/admin/audit-logs')->assertRedirect('/login');
    }

    public function test_filters_use_inclusive_philippine_dates_and_literal_name_search(): void
    {
        DB::table('users')->where('id', 1)->update(['name' => 'Admin_100%']);
        $wanted = $this->entry(['details' => 'Start of Philippine day']);
        $end = $this->entry(['performed_at' => '2026-09-26 15:59:59', 'details' => 'End of Philippine day']);
        $this->entry(['performed_at' => '2026-09-26 16:00:00']);
        $this->entry(['performed_at' => '2026-09-25 15:59:59']);
        $this->entry(['module' => 'Auth']);
        $this->entry(['action_type' => 'UPDATE']);
        $this->entry(['user_id' => 2]);
        $this->withSession($this->sessionFor(1))->get('/admin/audit-logs?'.http_build_query([
            'performed_by' => 'admin_100%', 'module' => 'User', 'action_type' => 'CREATE',
            'date_from' => '2026-09-26', 'date_to' => '2026-09-26',
        ]))->assertOk()->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [$end->id, $wanted->id]);
    }

    public function test_stable_pagination_retains_filters_and_reading_adds_no_entries(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->entry();
        }
        $this->withSession($this->sessionFor(1))->get('/admin/audit-logs?module=User')
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->count() === 15 && $logs->first()->id === 17 && str_contains($logs->nextPageUrl(), 'module=User'));
        $this->get('/admin/audit-logs?module=User&page=2')->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [2, 1]);
        $this->assertSame(17, AuditLog::count());
    }

    public function test_malformed_filters_and_reversed_dates_are_rejected(): void
    {
        $this->withSession($this->sessionFor(1));
        foreach ([['module' => ['bad']], ['performed_by' => ['bad']], ['action_type' => ['bad']], ['date_from' => '2026-02-30'], ['page' => '-1'], ['date_from' => '2026-09-26', 'date_to' => '2026-09-25']] as $filters) {
            $this->get('/admin/audit-logs?'.http_build_query($filters))
                ->assertRedirect(route('admin.audit-logs.index'))->assertSessionHasErrors();
        }
        $this->get('/admin/audit-logs')->assertOk();
    }

    public function test_details_are_escaped_and_missing_actor_and_details_are_supported(): void
    {
        $unsafe = str_repeat('Details ', 20)."<script>alert('test')</script>\nNew line";
        $this->entry(['details' => $unsafe]);
        $this->entry(['user_id' => null, 'details' => null, 'action_type' => 'CUSTOM_ACTION']);
        $this->withSession($this->sessionFor(1))->get('/admin/audit-logs')->assertOk()
            ->assertSee($unsafe)->assertDontSee("<script>alert('test')</script>", false)
            ->assertSee('System / unassigned')->assertSee('No details recorded.')->assertSee('CUSTOM_ACTION');
        $this->get('/admin/audit-logs?module=Missing')->assertOk()->assertSee('No audit entries found');
    }

    public function test_no_audit_write_routes_are_available(): void
    {
        $this->withSession($this->sessionFor(1));
        $this->post('/admin/audit-logs')->assertStatus(405);
        $this->put('/admin/audit-logs')->assertStatus(405);
        $this->delete('/admin/audit-logs')->assertStatus(405);
    }

    public function test_database_protects_history_from_bulk_mutation_and_rollback_retains_rows(): void
    {
        $migration = require database_path('migrations/2026_09_26_000001_protect_audit_log_history.php');
        $migration->up();
        $entry = $this->entry();
        foreach (['UPDATE audit_logs SET details = \'Changed\'', 'DELETE FROM audit_logs', 'TRUNCATE audit_logs'] as $sql) {
            try {
                DB::transaction(fn () => DB::statement($sql));
                $this->fail('Audit mutation must be rejected.');
            } catch (QueryException $error) {
                $this->assertSame('23514', $error->errorInfo[0]);
            }
        }
        $this->assertSame('Created an account.', $entry->fresh()->details);
        $migration->down();
        $this->assertSame(1, AuditLog::count());
    }

    public function test_model_sets_timestamp_and_rejects_edit_and_delete(): void
    {
        $entry = $this->entry(['performed_at' => null]);
        $this->assertNotNull($entry->performed_at);
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $entry->update(['details' => 'Changed']) : $entry->delete();
                $this->fail('Model mutation must be rejected.');
            } catch (\LogicException $error) {
                $this->assertStringContainsString('Audit entries cannot', $error->getMessage());
            }
        }
    }

    public function test_database_outage_does_not_look_like_empty_history_or_expose_sql(): void
    {
        DB::statement('ALTER TABLE audit_logs RENAME TO unavailable_audit_logs');
        $this->withSession($this->sessionFor(1))->get('/admin/audit-logs')->assertStatus(503)
            ->assertSee('Audit Logs temporarily unavailable')->assertDontSee('SQLSTATE')->assertDontSee('No audit entries found');
    }

    public function test_missing_audit_table_rolls_back_project_creation(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE program_components (id bigint PRIMARY KEY, name varchar);
            CREATE TABLE statuses (id bigint PRIMARY KEY, status_name varchar);
            INSERT INTO program_components VALUES (1, 'SAAD');
            INSERT INTO statuses VALUES (1, 'Ongoing');
            CREATE TABLE projects (id bigserial PRIMARY KEY, association_id bigint, title varchar,
                commodity_type varchar, program_component_id bigint, implementation_date date,
                budget numeric, status_id bigint, remarks text, is_archived boolean, created_at timestamp, updated_at timestamp);
            ALTER TABLE audit_logs RENAME TO unavailable_audit_logs;
            SQL);
        try {
            app(ProjectManagementService::class)->createProject([
                'association_id' => 1, 'title' => 'Synthetic project', 'commodity_type' => 'Fish',
                'program_component_id' => 1, 'implementation_date' => '2026-09-26', 'budget' => 100, 'status_id' => 1,
            ], 1);
            $this->fail('A project must not be saved without its audit event.');
        } catch (QueryException $error) {
            $this->assertSame('42P01', $error->errorInfo[0]);
        }
        $this->assertSame(0, DB::table('projects')->count());
    }
}
