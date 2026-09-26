<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ReportsService;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssociationDatabaseTestCase;

final class ReportsTest extends AssociationDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // These tables exist only inside the test transaction; real records are never changed.
        DB::unprepared(<<<'SQL'
            ALTER TABLE projects ADD COLUMN title varchar, ADD COLUMN status_id bigint REFERENCES statuses(id);
            ALTER TABLE trainings ADD COLUMN date_conducted date;
            CREATE TABLE quarters (id bigint PRIMARY KEY, quarter_name varchar);
            INSERT INTO quarters VALUES (1,'Q1');
            CREATE TABLE monitoring_income (id bigserial PRIMARY KEY, association_id bigint, project_id bigint, year integer, month integer, gross_income numeric(12,2));
            CREATE TABLE monitoring_production (id bigserial PRIMARY KEY, association_id bigint, project_id bigint, year integer, quarter_id bigint, target_output numeric(10,2), actual_output numeric(10,2), remarks text);
            UPDATE associations SET area_unit_id=2, sub_unit_id=2 WHERE id=2;
            INSERT INTO projects (association_id,title,status_id,is_archived) VALUES (1,'Fish project',4,false),(1,'Archived project',4,true),(2,'Other project',NULL,false);
            INSERT INTO trainings (association_id,date_conducted,is_archived) VALUES (1,'2025-02-01',false),(1,'2024-02-01',false),(1,'2025-02-01',true),(2,'2025-02-01',false);
            INSERT INTO monitoring_income (association_id,project_id,year,month,gross_income) VALUES (1,1,2025,1,100.25),(1,1,2025,2,200.50),(1,1,2024,1,500),(1,2,2025,1,900),(2,3,2025,1,400),(2,1,2025,1,999);
            INSERT INTO monitoring_production (association_id,project_id,year,quarter_id,target_output,actual_output,remarks) VALUES (1,1,2025,1,100,80,'Output in kilograms.'),(2,3,2025,1,0,20,'No target set.');
        SQL);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
    }

    public function test_filters_totals_and_export_use_the_same_records(): void
    {
        $filters = ['year' => 2025, 'area_unit_id' => 1];
        $data = app(ReportsService::class)->overview($filters);
        $this->assertSame(['associations' => 1, 'members' => 1, 'projects' => 1, 'trainings' => 1], $data['counts']);
        $this->assertEquals(300.75, $data['incomeTotal']);
        $this->assertSame(2, $data['incomeRecords']);
        $this->assertEquals(300.75, $data['rows']->first()->income);
        $this->assertCount(12, $data['months']);
        $this->assertSame(0, $data['months'][2]['records']);
        $this->get(route('reports.index', $filters))->assertOk()->assertSee('300.75')->assertSee('80.0%')
            ->assertSee('No records')->assertSee(route('reports.export', $filters))->assertDontSee('Archived project');
        $this->get(route('reports.export', $filters))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('300.75')->assertSee('Output in kilograms.')->assertDontSee('Other project')->assertDontSee('999');
    }

    public function test_current_scopes_zero_targets_and_empty_filters_are_clear(): void
    {
        $this->get('/admin/reports?year=2025')->assertOk()->assertSee('N/A (zero target)')->assertSee('Unspecified');
        DB::table('associations')->where('id', 2)->update(['is_archived' => true]);
        $data = app(ReportsService::class)->overview(['year' => 2025]);
        $this->assertSame(1, $data['counts']['associations']);
        $this->assertEquals(300.75, $data['incomeTotal']);
        $this->get('/admin/reports?year=2025&area_unit_id=2&association_id=1')->assertOk()
            ->assertSee('No current associations match these filters.')->assertSee('No production records match these filters.');
        $this->get('/admin/reports?year=')->assertOk()->assertViewHas('filters', fn ($filters) => $filters['year'] === now('Asia/Manila')->year);
    }

    public function test_names_are_escaped_and_csv_formulas_are_neutralized(): void
    {
        DB::table('associations')->where('id', 1)->update(['name' => '=1+1']);
        DB::table('monitoring_production')->where('id', 1)->update(['remarks' => '<script>alert(1)</script>']);
        $this->get('/admin/reports?year=2025')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/admin/reports/export?year=2025')->assertOk()->assertSee("'=1+1", false);
    }

    public function test_validation_and_live_permissions_protect_page_and_export(): void
    {
        foreach (['/admin/reports', '/admin/reports/export'] as $path) {
            $this->getJson($path.'?year[]=2025')->assertUnprocessable();
            $this->getJson($path.'?year=2200&association_id=999')->assertUnprocessable();
        }
        $this->withSession($this->sessionFor(2, 'System Administrator'));
        $this->get('/admin/reports')->assertRedirect(route('dashboard.officer'));
        $this->get('/admin/reports/export')->assertRedirect(route('dashboard.officer'));
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->get('/admin/reports/export')->assertRedirect(route('login'));
        $this->flushSession();
        $this->get('/admin/reports')->assertRedirect(route('login'));
    }

    public function test_database_failure_returns_retry_page_instead_of_a_partial_download(): void
    {
        DB::statement('ALTER TABLE monitoring_income RENAME TO unavailable_income');
        $this->get('/admin/reports?year=2025')->assertStatus(503)->assertSee('Reports are temporarily unavailable')->assertDontSee('SQLSTATE');
    }

    public function test_export_database_failure_does_not_send_a_csv(): void
    {
        DB::statement('ALTER TABLE monitoring_income RENAME TO unavailable_income');
        $this->get('/admin/reports/export?year=2025')->assertStatus(503)
            ->assertHeaderMissing('Content-Disposition')->assertSee('Try again')->assertDontSee('SQLSTATE');
    }
}
