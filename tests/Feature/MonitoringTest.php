<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\MembershipDatabaseTestCase;

final class MonitoringTest extends MembershipDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(<<<'SQL'
            CREATE TABLE projects (id bigserial PRIMARY KEY, association_id bigint REFERENCES associations(id), title varchar, is_archived boolean DEFAULT false);
            CREATE TABLE project_materials (id bigserial PRIMARY KEY, project_id bigint REFERENCES projects(id), item_name varchar);
            CREATE TABLE quarters (id bigserial PRIMARY KEY, quarter_name varchar);
            INSERT INTO quarters (quarter_name) VALUES ('Q1'),('Q2'),('Q3'),('Q4');
            INSERT INTO statuses (status_name) VALUES ('Good'),('Damaged'),('For Repair');
            INSERT INTO projects (association_id,title) VALUES (1,'Assigned Fish Project'),(2,'Private Other Project');
            INSERT INTO project_materials (project_id,item_name) VALUES (1,'Fishing Net'),(2,'Private Boat');
            CREATE TABLE monitoring_production (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), project_id bigint NOT NULL REFERENCES projects(id),
                quarter_id bigint NOT NULL REFERENCES quarters(id), year integer NOT NULL, target_output numeric(10,2) NOT NULL,
                actual_output numeric(10,2) NOT NULL, remarks text, created_by bigint REFERENCES users(id), created_at timestamp, updated_at timestamp,
                UNIQUE(project_id,quarter_id,year)
            );
            CREATE TABLE monitoring_income (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), project_id bigint NOT NULL REFERENCES projects(id),
                month integer NOT NULL, year integer NOT NULL, gross_income numeric(12,2) NOT NULL, remarks text,
                created_by bigint REFERENCES users(id), created_at timestamp, updated_at timestamp, UNIQUE(project_id,month,year)
            );
            CREATE TABLE monitoring_materials (
                id bigserial PRIMARY KEY, project_material_id bigint NOT NULL REFERENCES project_materials(id), material_description varchar(255),
                condition_status_id bigint REFERENCES statuses(id), scheduled_maintenance date, actual_maintenance date, remarks text,
                created_by bigint REFERENCES users(id), created_at timestamp, updated_at timestamp
            );
        SQL);
    }

    private function production(array $extra = []): array
    {
        return array_replace(['project_id' => 1, 'quarter_id' => 1, 'year' => 2025, 'target_output' => '100.00', 'actual_output' => '80.00', 'remarks' => 'Output in kilograms.'], $extra);
    }

    public function test_authentication_and_current_role_are_enforced(): void
    {
        $this->get('/monitoring')->assertRedirect('/login');
        $this->withSession($this->sessionFor(3, 'System Administrator'));
        $this->get('/monitoring')->assertForbidden();
        $this->post('/monitoring/production', $this->production())->assertForbidden();
        $this->get('/monitoring/production/create')->assertForbidden();
        $this->assertSame(0, DB::table('monitoring_production')->count());
    }

    public function test_production_create_edit_duplicate_and_zero_target(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->get('/monitoring/production/create')->assertOk()->assertSee('Target Output');
        $this->post('/monitoring/production', $this->production(['created_by' => 3, 'association_id' => 2]))->assertSessionHasNoErrors();
        $record = DB::table('monitoring_production')->first();
        $this->assertSame(1, $record->association_id);
        $this->assertSame(1, $record->created_by);
        $this->get('/monitoring')->assertOk()->assertSee('80.0%');
        $this->get('/monitoring/production/1/edit')->assertOk()->assertSee('Output in kilograms.');
        $this->postJson('/monitoring/production', $this->production())->assertUnprocessable()->assertJsonValidationErrors('project_id');
        $this->put('/monitoring/production/1', $this->production(['target_output' => 0]))->assertSessionHasNoErrors();
        $this->get('/monitoring')->assertOk()->assertSee('N/A (zero target)');
        $this->assertSame(2, DB::table('audit_logs')->where('module', 'Monitoring')->count());
    }

    public function test_officers_cannot_read_or_modify_other_associations(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->post('/monitoring/production', $this->production(['project_id' => 2]))->assertSessionHasNoErrors();
        $this->withSession($this->sessionFor(2, 'System Administrator'));
        $this->get('/monitoring')->assertOk()->assertDontSee('Private Other Project');
        $this->get('/monitoring/materials/create')->assertOk()->assertDontSee('Private Boat');
        $this->get('/monitoring/production/1/edit')->assertNotFound();
        $this->postJson('/monitoring/production', $this->production(['project_id' => 2]))->assertNotFound();
        $this->putJson('/monitoring/production/1', $this->production())->assertNotFound();
        $this->post('/monitoring/production', $this->production())->assertSessionHasNoErrors();
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 1]);
        $this->putJson('/monitoring/production/2', $this->production())->assertNotFound();
        $this->get('/monitoring')->assertOk()->assertDontSee('Assigned Fish Project');
    }

    public function test_income_validation_and_monthly_duplicates(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $data = ['project_id' => 1, 'month' => 3, 'year' => 2025, 'gross_income' => '15000.25'];
        $this->get('/monitoring/income/create')->assertOk()->assertSee('Gross Income');
        $this->post('/monitoring/income', $data)->assertSessionHasNoErrors();
        $this->get('/monitoring?type=income')->assertOk()->assertSee('15,000.25')->assertSee('March');
        $this->postJson('/monitoring/income', $data)->assertUnprocessable();
        $this->put('/monitoring/income/1', [...$data, 'gross_income' => '0'])->assertSessionHasNoErrors();
        $this->postJson('/monitoring/income', [...$data, 'month' => 13, 'gross_income' => '-1'])->assertJsonValidationErrors(['month', 'gross_income']);
        $this->postJson('/monitoring/production', $this->production(['target_output' => '100000000', 'actual_output' => '1.001', 'quarter_id' => 999]))->assertJsonValidationErrors(['target_output', 'actual_output', 'quarter_id']);
        $this->travelTo(now('Asia/Manila')->setDate(2026, 1, 5));
        $this->postJson('/monitoring/income', [...$data, 'year' => 2026, 'month' => 2])->assertJsonValidationErrors('month');
        $this->travelBack();
    }

    public function test_material_condition_ownership_maintenance_and_duplicates(): void
    {
        $this->withSession($this->sessionFor(2, 'Field Officer'));
        $data = ['project_id' => 1, 'project_material_id' => 1, 'condition_status_id' => 5, 'scheduled_maintenance' => '2025-01-01', 'material_description' => 'Net needs repair.'];
        $this->post('/monitoring/materials', $data)->assertSessionHasNoErrors();
        $this->get('/monitoring?type=materials')->assertOk()->assertSee('Damaged')->assertSee('Maintenance overdue');
        $this->get('/monitoring/materials/1/edit')->assertOk()->assertSee('Net needs repair.');
        $this->postJson('/monitoring/materials', $data)->assertUnprocessable();
        $this->put('/monitoring/materials/1', [...$data, 'actual_maintenance' => '2025-01-02'])->assertSessionHasNoErrors();
        $this->get('/monitoring?type=materials')->assertOk()->assertDontSee('Maintenance overdue');
        $this->postJson('/monitoring/materials', [...$data, 'project_material_id' => 2])->assertJsonValidationErrors('project_material_id');
        $this->postJson('/monitoring/materials', [...$data, 'condition_status_id' => 1])->assertJsonValidationErrors('condition_status_id');
        $this->putJson('/monitoring/materials/1', [...$data, 'actual_maintenance' => now('Asia/Manila')->addDay()->toDateString()])->assertJsonValidationErrors('actual_maintenance');
    }

    public function test_archived_projects_and_associations_retain_read_only_history(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->post('/monitoring/production', $this->production())->assertSessionHasNoErrors();
        DB::table('projects')->where('id', 1)->update(['is_archived' => true]);
        $this->get('/monitoring')->assertOk()->assertSee('Read only');
        $this->get('/monitoring/production/1/edit')->assertNotFound();
        $this->putJson('/monitoring/production/1', $this->production())->assertJsonValidationErrors('project_id');
        DB::table('projects')->where('id', 1)->update(['is_archived' => false]);
        DB::table('associations')->where('id', 1)->update(['is_archived' => true]);
        $this->postJson('/monitoring/production', $this->production(['quarter_id' => 2]))->assertJsonValidationErrors('project_id');
        $this->assertSame(1, DB::table('monitoring_production')->count());
    }

    public function test_audit_failure_rolls_back_and_keeps_form_input_private(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_monitoring CHECK (module <> 'Monitoring')");
        $this->postJson('/monitoring/production', $this->production())->assertStatus(503)->assertDontSee('SQLSTATE');
        $this->from('/monitoring/production/create')->post('/monitoring/production', $this->production(['unexpected' => 'private']))
            ->assertRedirect('/monitoring/production/create')->assertSessionHas('_old_input.remarks', 'Output in kilograms.')->assertSessionMissing('_old_input.unexpected');
        $this->assertSame(0, DB::table('monitoring_production')->count());
    }

    public function test_filters_pagination_escaping_and_invalid_types(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        for ($year = 2010; $year < 2022; $year++) {
            $this->post('/monitoring/production', $this->production(['year' => $year, 'remarks' => '<script>alert(1)</script>']))->assertSessionHasNoErrors();
        }
        $this->get('/monitoring?page=2')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/monitoring?year=2000')->assertOk()->assertSee('No monitoring records found');
        $this->get('/monitoring?search=missing')->assertOk()->assertSee('No monitoring records found');
        $this->get('/monitoring?project_id=2')->assertOk()->assertSee('No monitoring records found');
        $this->getJson('/monitoring?type[]=production')->assertUnprocessable();
        $this->get('/monitoring/unknown/create')->assertNotFound();
        $this->putJson('/monitoring/production/1', $this->production(['project_id' => 2]))->assertJsonValidationErrors('project_id');
    }
}
