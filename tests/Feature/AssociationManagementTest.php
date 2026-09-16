<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AssociationRuleException;
use App\Models\Association;
use App\Services\AdminUserManagementService;
use App\Services\AssociationDatabase;
use App\Services\AssociationManagementService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssociationDatabaseTestCase;

final class AssociationManagementTest extends AssociationDatabaseTestCase
{
    public function test_joined_reads_preserve_labels_counts_and_authorization(): void
    {
        $service = app(AssociationManagementService::class);
        $record = $service->paginate([])->first();
        $this->assertSame('Municipality A', $record->areaUnit->name);
        $this->assertSame('Officer', $record->fieldOfficer->name);
        $this->assertSame(1, $record->members_count);
        $this->assertSame(1, $service->findDetailed(Association::findOrFail(1))->published_gis_locations_count);
        $this->withSession($this->sessionFor(2, 'System Administrator'))->get('/admin/associations')->assertRedirect(route('dashboard.officer'));
        $this->withSession($this->sessionFor(3, 'Association Member'))->get('/admin/associations/1')->assertRedirect(route('dashboard.member'));
        $this->withSession($this->sessionFor(1, 'System Administrator'))->get('/admin/associations?search[]=bad')->assertRedirect(route('admin.associations.index'));
    }

    public function test_create_and_validation_keep_database_consistent(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->postJson('/admin/associations?search=New', $this->payload())->assertOk()->assertJsonPath('redirect_url', route('admin.associations.index', ['search' => 'New']));
        $this->postJson('/admin/associations', $this->payload(['name' => '  new   association  ']))->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/admin/associations', $this->payload(['name' => ['bad'], 'date_joined' => 'not-a-date']))->assertUnprocessable()->assertJsonValidationErrors(['name', 'date_joined']);
        $this->postJson('/admin/associations', $this->payload(['name' => 'Wrong Barangay', 'sub_unit_id' => 2]))->assertUnprocessable()->assertJsonValidationErrors('sub_unit_id');
        $this->assertSame(3, Association::count());
        $this->assertSame(1, DB::table('audit_logs')->where('module', 'Association')->where('action_type', 'CREATE')->count());
    }

    public function test_edit_failure_recovers_only_the_attempted_form(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'))->from('/admin/associations?search=Assigned')
            ->put('/admin/associations/1?search=Assigned', $this->payload(['name' => 'Attempted correction', 'date_joined' => '2099-01-01']))
            ->assertRedirect(route('admin.associations.index', ['search' => 'Assigned']))->assertSessionHasErrors('date_joined')->assertSessionHas('association_form.mode', 'edit');
        $response = $this->get('/admin/associations?search=Assigned')->assertOk();
        $response->assertSee('Attempted correction')->assertSee('data-recovery', false)->assertSee('data-selected-value="1"', false);
        $this->assertSame('Assigned Association', Association::findOrFail(1)->name);
        $this->putJson('/admin/associations/1', $this->payload(['name' => 'Corrected association']))->assertOk();
        $this->assertSame('Corrected association', Association::findOrFail(1)->name);
    }

    public function test_archive_audit_failure_rolls_back_gis_and_record(): void
    {
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_archive CHECK (action_type <> 'ARCHIVE')");
        $this->withSession($this->sessionFor(1, 'System Administrator'))->patchJson('/admin/associations/1/archive')->assertStatus(503)->assertDontSee('reject_archive')->assertDontSee('SQLSTATE');
        $this->assertFalse(Association::findOrFail(1)->is_archived);
        $this->assertTrue((bool) DB::table('gis_locations')->where('association_id', 1)->value('is_published'));
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT reject_archive');
        $this->patchJson('/admin/associations/1/archive')->assertOk();
        $this->patchJson('/admin/associations/1/archive')->assertOk();
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'ARCHIVE')->count());
        $this->putJson('/admin/associations/1', $this->payload())->assertUnprocessable();
        $this->patchJson('/admin/associations/1/restore')->assertOk();
        $this->assertFalse((bool) DB::table('gis_locations')->where('association_id', 1)->value('is_published'));
    }

    public function test_officer_changes_require_reassignment_and_restore_rechecks(): void
    {
        $users = app(AdminUserManagementService::class);
        try {
            $users->toggleActive(2, 1);
            $this->fail('Assigned officer deactivation accepted');
        } catch (AssociationRuleException $error) {
            $this->assertStringContainsString('Reassign', $error->getMessage());
        }
        try {
            $users->update(2, ['name' => 'Officer', 'email' => 'officer@example.test', 'role_id' => 3], 1);
            $this->fail('Assigned officer demotion accepted');
        } catch (AssociationRuleException $error) {
            $this->assertStringContainsString('Reassign', $error->getMessage());
        }
        DB::table('associations')->update(['is_archived' => true]);
        $this->assertFalse($users->toggleActive(2, 1));
        $this->withSession($this->sessionFor(1, 'System Administrator'))->patchJson('/admin/associations/1/restore')->assertUnprocessable()->assertJsonValidationErrors('field_officer_id');
    }

    public function test_representative_rejects_other_association_and_unchanged_assignment_has_no_audit(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->patchJson('/admin/associations/1/representative', ['representative_member_id' => 2])->assertUnprocessable();
        $this->patchJson('/admin/associations/1/representative', ['representative_member_id' => 1])->assertOk();
        $this->assertSame(0, DB::table('audit_logs')->count());
        $this->patchJson('/admin/associations/1/representative', ['representative_member_id' => null])->assertOk();
        $this->assertNull(Association::findOrFail(1)->representative_member_id);
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'REMOVE_REPRESENTATIVE')->count());
    }

    public function test_statement_timeout_rolls_back_and_restores_transaction_settings(): void
    {
        $before = DB::selectOne("SELECT current_setting('statement_timeout') AS value")->value;
        config(['association.statement_timeout_ms' => 50]);
        try {
            app(AssociationDatabase::class)->run(function () {
                DB::table('associations')->where('id', 1)->update(['name' => 'Must Roll Back']);
                DB::select('SELECT pg_sleep(0.15)');
            });
            $this->fail('Expected a real PostgreSQL timeout');
        } catch (QueryException $error) {
            $this->assertSame('57014', $error->errorInfo[0]);
        }
        $this->assertSame('Assigned Association', Association::findOrFail(1)->name);
        $this->assertSame($before, DB::selectOne("SELECT current_setting('statement_timeout') AS value")->value);
    }

    public function test_timeout_during_audit_returns_safe_json_and_rolls_back_creation(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION slow_fixture_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(0.15); RETURN NEW; END $$;
            CREATE TRIGGER slow_audit BEFORE INSERT ON audit_logs FOR EACH ROW EXECUTE FUNCTION slow_fixture_audit();
        SQL);
        config(['association.statement_timeout_ms' => 50]);
        $this->withSession($this->sessionFor(1, 'System Administrator'))->postJson('/admin/associations', $this->payload())
            ->assertStatus(503)->assertJsonPath('outcome_unknown', false)->assertDontSee('pg_sleep')->assertDontSee('SQLSTATE');
        $this->assertSame(2, Association::count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_pre_controller_database_failure_returns_safe_json(): void
    {
        $armed = true;
        DB::connection()->beforeExecuting(function ($query) use (&$armed) {
            if ($armed && str_contains($query, '"associations"')) {
                $armed = false;
                throw new \PDOException('Sensitive connection detail must never appear');
            }
        });
        $this->withSession($this->sessionFor(1, 'System Administrator'))->getJson('/admin/associations/1')
            ->assertStatus(503)->assertJsonPath('outcome_unknown', true)->assertDontSee('Sensitive connection detail');
    }

    public function test_lock_timeout_uses_a_second_connection_without_touching_public_records(): void
    {
        config(['database.connections.association_lock_peer' => config('database.connections.pgsql'), 'association.lock_timeout_ms' => 50]);
        $peer = DB::connection('association_lock_peer');
        $key = random_int(1, 2000000000);
        $peer->beginTransaction();
        try {
            $peer->select('SELECT pg_advisory_xact_lock(?, ?)', [19515, $key]);
            try {
                app(AssociationDatabase::class)->run(fn () => DB::select('SELECT pg_advisory_xact_lock(?, ?)', [19515, $key]));
                $this->fail('Expected lock timeout');
            } catch (QueryException $error) {
                $this->assertSame('55P03', $error->errorInfo[0]);
            }
        } finally {
            $peer->rollBack();
            DB::purge('association_lock_peer');
        }
    }

    public function test_browser_fixture_exports_only_synthetic_records(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        foreach (['index' => '/admin/associations', 'details' => '/admin/associations/1'] as $name => $url) {
            $response = $this->get($url)->assertOk();
            if (getenv('ASSOCMAP_EXPORT_ASSOCIATION_FIXTURES') === '1') {
                $dir = base_path('../Capstone-AssocMap/association-qa');
                if (! is_dir($dir)) {
                    mkdir($dir,0755,true);
                }
                file_put_contents($dir.'/'.$name.'.html',$response->getContent());
            }
        }
    }
}
