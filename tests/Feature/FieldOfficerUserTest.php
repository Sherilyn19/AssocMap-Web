<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\MembershipDatabaseTestCase;

/** Fixtures exist only in the inherited random, rollback-only PostgreSQL schema. */
final class FieldOfficerUserTest extends MembershipDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(<<<'SQL'
            CREATE TABLE program_components (id bigserial PRIMARY KEY, name varchar);
            INSERT INTO program_components (name) VALUES ('Aquaculture');
            INSERT INTO statuses (status_name) VALUES ('Active'),('Present'),('Absent'),('Good'),('Damaged'),('For Repair');
            INSERT INTO area_units (name) VALUES ('Shared Municipality');
            INSERT INTO sub_units (area_unit_id,name) VALUES (1,'Shared Barangay');
            ALTER TABLE associations ADD program_component_id bigint, ADD status_id bigint, ADD address text;
            UPDATE associations SET area_unit_id=1,sub_unit_id=1,program_component_id=1,status_id=4;
            CREATE TABLE projects (id bigserial PRIMARY KEY, association_id bigint REFERENCES associations(id), title varchar, commodity_type varchar, program_component_id bigint, implementation_date date, terminated_on date, status_id bigint, remarks text, is_archived boolean DEFAULT false, created_at timestamp, updated_at timestamp);
            INSERT INTO projects (association_id,title,program_component_id,status_id) VALUES (1,'Assigned project',1,4),(2,'Foreign project',1,4);
            CREATE TABLE project_materials (id bigserial PRIMARY KEY, project_id bigint REFERENCES projects(id), item_name varchar, quantity numeric, unit varchar, unit_cost numeric, status_id bigint, delivery_date date, created_at timestamp, updated_at timestamp);
            INSERT INTO project_materials (project_id,item_name,quantity,unit,unit_cost,status_id) VALUES (1,'Assigned net',2,'set',100,7),(2,'Foreign net',3,'set',100,7);
            CREATE TABLE trainings (id bigserial PRIMARY KEY, association_id bigint REFERENCES associations(id), title varchar, program_component_id bigint, training_type varchar, venue varchar, date_conducted date, end_date date, stage varchar, conducted_by varchar, remarks text, is_archived boolean DEFAULT false, created_at timestamp, updated_at timestamp);
            INSERT INTO trainings (association_id,title,program_component_id,training_type,venue,date_conducted,end_date,stage,conducted_by) VALUES (1,'Assigned workshop',1,'Skills','Hall','2025-01-01','2025-01-02','accepted','BFAR'),(2,'Foreign workshop',1,'Skills','Hall','2025-01-01','2025-01-02','accepted','BFAR');
            CREATE TABLE training_participants (id bigserial PRIMARY KEY, training_id bigint REFERENCES trainings(id), member_id bigint REFERENCES members(id), attendance_status_id bigint, UNIQUE(training_id,member_id));
            CREATE TABLE quarters (id bigint PRIMARY KEY, quarter_name varchar);
            INSERT INTO quarters VALUES (1,'Q1'),(2,'Q2'),(3,'Q3'),(4,'Q4');
            CREATE TABLE monitoring_production (id bigserial PRIMARY KEY, association_id bigint, project_id bigint, quarter_id bigint, year integer, target_output numeric, actual_output numeric, remarks text, created_by bigint, created_at timestamp, updated_at timestamp, UNIQUE(project_id,quarter_id,year));
            CREATE TABLE monitoring_income (id bigserial PRIMARY KEY, association_id bigint, project_id bigint, month integer, year integer, gross_income numeric, remarks text, created_by bigint, created_at timestamp, updated_at timestamp, UNIQUE(project_id,month,year));
            CREATE TABLE monitoring_materials (id bigserial PRIMARY KEY, project_material_id bigint UNIQUE, material_description varchar, condition_status_id bigint, scheduled_maintenance date, actual_maintenance date, remarks text, created_by bigint, created_at timestamp, updated_at timestamp);
            INSERT INTO monitoring_production (association_id,project_id,quarter_id,year,target_output,actual_output,created_by) VALUES (1,1,1,2025,0,10,2),(2,2,1,2025,100,20,1);
            INSERT INTO monitoring_income (association_id,project_id,month,year,gross_income,created_by) VALUES (1,1,1,2025,150,2),(2,2,1,2025,900,1);
            INSERT INTO monitoring_materials (project_material_id,condition_status_id,created_by) VALUES (1,7,2),(2,7,1);
        SQL);

        // Add training workflow fields to the temporary test database.
        (require base_path(
            'database/migrations/2026_10_06_000002_add_training_workflow_markers.php'
        ))->up();
        
        // The foreign association belongs to another actual Field Officer.
        DB::table('users')->insert(['id' => 4, 'name' => 'Other Officer', 'email' => 'other-officer@example.test', 'role_id' => 2, 'is_active' => true]);
        DB::table('associations')->where('id', 2)->update(['field_officer_id' => 4]);
        $this->withSession($this->sessionFor(2, 'Field Officer'));
    }

    public function test_dashboard_and_navigation_are_scoped_and_read_only(): void
    {
        $this->get('/officer/dashboard')->assertOk()->assertViewHas('counts', fn ($counts) => $counts === [
            'My Associations' => 1, 'My Members' => 1, 'Monitoring Records' => 3, 'Training Records' => 1,
        ])->assertSee('Assigned Association')->assertDontSee('Other Association')->assertSee('N/A')
            ->assertDontSee('Member Approvals')->assertDontSee('User Management')->assertDontSee('Audit Log');
        $this->get('/officer/associations?area_unit_id=1')->assertOk()->assertSee('Assigned Association')->assertDontSee('Other Association');
        $this->get('/officer/associations/1')->assertOk()->assertSee('Shared Municipality');
        $this->get('/officer/associations/2')->assertNotFound();
        $this->get('/officer/associations/999')->assertNotFound();
        $this->get('/officer/associations?search=missing')->assertOk()->assertDontSee('Assigned Association');
        $this->get('/officer/projects')->assertOk()->assertSee('Assigned project')->assertDontSee('Foreign project');
        $this->get('/officer/projects/1')->assertOk()->assertSee('Assigned net')->assertDontSee('Foreign net');
        $this->get('/officer/projects/2')->assertNotFound();
        $this->get('/officer/trainings')->assertOk()->assertSee('Assigned workshop')->assertDontSee('Foreign workshop');
        $this->get('/officer/trainings/1')->assertOk()->assertSee('Training purpose');
        $this->get('/officer/trainings/2')->assertNotFound();
    }

    public function test_project_modal_reports_real_training_attendance_and_preserves_assignment_boundary(): void
    {
        DB::table('training_participants')->insert([
            ['training_id' => 1, 'member_id' => 1, 'attendance_status_id' => 5],
            // Historical mismatched members must not affect the association totals.
            ['training_id' => 1, 'member_id' => 2, 'attendance_status_id' => 1],
        ]);
        $response = $this->get('/officer/projects/1?details=1');
        $response->assertOk()->assertSee('Assigned workshop')->assertDontSee('Foreign workshop')
            ->assertSee('1 of 1 participant attendance records finalized')
            ->assertSee('Initiation / Proposal')->assertSee('Accepted')->assertSee('Termination')
            ->assertSee('Project proposal acceptance date')->assertSee('Hall')->assertSee('BFAR')
            ->assertSee('Assigned net')->assertDontSee('<html', false);
        $this->get('/officer/projects/2?details=1')->assertNotFound();

        DB::table('trainings')->where('id', 1)->update(['is_archived' => true]);
        $this->get('/officer/projects/1?details=1')->assertOk()->assertSee('Assigned workshop')
            ->assertSee('0 of 0 participant attendance records finalized')->assertSee('No attendance yet');
    }

    public function test_project_register_summary_and_filters_stay_within_assignments(): void
    {
        // Keep current, archived, undated, and empty projects distinct in the fixture.
        DB::table('projects')->insert([
            'association_id' => 1, 'title' => 'Archived empty project', 'is_archived' => true,
        ]);
        $response = $this->get('/officer/projects?delivery=missing');
        $response->assertOk()->assertViewHas('summary', fn ($summary) => $summary === [
            'total' => 2, 'current' => 1, 'associations' => 1, 'missing' => 1,
        ])->assertViewHas('projects', fn ($rows) => $rows->total() === 1 && $rows->first()->id === 1)
            ->assertDontSee('Foreign project');

        $this->get('/officer/projects?archive=archived&delivery=none')->assertOk()
            ->assertViewHas('projects', fn ($rows) => $rows->total() === 1 && $rows->first()->is_archived);
        DB::table('project_materials')->where('id', 1)->update(['delivery_date' => '2025-01-10']);
        $this->get('/officer/projects?delivery=recorded')->assertOk()
            ->assertViewHas('projects', fn ($rows) => $rows->total() === 1 && $rows->first()->recorded_deliveries_count === 1);
        $this->get('/officer/projects?association_id=2')->assertNotFound();
        $this->getJson('/officer/projects?delivery=invalid')->assertUnprocessable();
    }

    public function test_login_current_identity_invalid_password_and_inactive_account(): void
    {
        $password = 'Officer-Fixture-2026';
        DB::table('users')->where('id', 2)->update(['password' => Hash::make($password)]);
        $this->flushSession();
        $this->post('/login', ['email' => 'officer@example.test', 'password' => 'incorrect'])->assertSessionHas('error');
        $this->post('/login', ['email' => 'officer@example.test', 'password' => $password])->assertRedirect('/officer/dashboard');
        $this->get('/officer/dashboard')->assertOk();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'LOGIN')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'LOGOUT')->count());
        DB::table('users')->where('id', 2)->update(['is_active' => false]);
        $this->post('/login', ['email' => 'officer@example.test', 'password' => $password])->assertSessionHas('error');
        $this->withSession($this->sessionFor(2, 'Field Officer'))->get('/officer/dashboard')->assertRedirect('/login');
        $this->withSession($this->sessionFor(3, 'Field Officer'))->get('/officer/dashboard')->assertRedirect('/member/dashboard');
    }

    public function test_membership_tabs_are_scoped_and_administrator_actions_remain_denied(): void
    {
        $application = DB::table('member_applications')->insertGetId(['association_id' => 1, 'first_name' => 'Pending', 'last_name' => 'Applicant', 'birthday' => '1990-01-01', 'status_id' => 1]);
        // Each tab displays only its own table.
        $this->get('/membership?tab=members')
            ->assertOk()
            ->assertSee('Representative')
            ->assertDontSee('Pending Applicant');

        $this->get('/membership?tab=applications')
            ->assertOk()
            ->assertSee('Pending Applicant')
            ->assertDontSee('Submit Application');
        $this->get('/membership/members/1')->assertOk();
        $this->get('/membership/members/2')->assertForbidden();
        $this->get('/membership/applications/'.$application)->assertOk()->assertDontSee('name="review_passphrase"', false);
        $this->postJson('/membership/applications', [])->assertForbidden();
        foreach (['Approved', 'Rejected'] as $decision) {
            $this->patchJson('/membership/applications/'.$application.'/review', ['decision' => $decision, 'reviewed_by_member_id' => 1])->assertForbidden();
        }
        foreach ([['POST', '/admin/projects'], ['PUT', '/admin/projects/1'], ['PATCH', '/admin/projects/1/archive'], ['PUT', '/admin/members/1'], ['PATCH', '/admin/members/1/archive'], ['POST', '/admin/trainings'], ['PATCH', '/admin/trainings/1/archive'], ['PATCH', '/admin/associations/1/representative'], ['GET', '/admin/audit-logs']] as [$method, $path]) {
            $this->call($method, $path)->assertRedirect('/officer/dashboard');
        }
        $this->assertSame(1, (int) DB::table('member_applications')->where('id', $application)->value('status_id'));
    }

    public function test_member_register_preserves_filtered_totals_and_independent_pagination(): void
    {
        // These additional records exist only in the rollback-only test schema.
        for ($i = 1; $i <= 16; $i++) {
            DB::table('members')->insert([
                'association_id' => 1, 'first_name' => 'Listed', 'last_name' => sprintf('Person %02d', $i),
                'birthday' => '1990-01-01', 'date_registered' => '2020-01-01',
            ]);
            DB::table('member_applications')->insert([
                'association_id' => 1, 'first_name' => 'Listed', 'last_name' => sprintf('Applicant %02d', $i),
                'birthday' => '1990-01-01', 'status_id' => 1,
            ]);
        }
        $foreign = DB::table('member_applications')->insertGetId([
            'association_id' => 2, 'first_name' => 'Foreign', 'last_name' => 'Application',
            'birthday' => '1990-01-01', 'status_id' => 1,
        ]);

        $this->get('/membership?tab=members')->assertOk()->assertViewIs('field-officer-user.members.index')
            ->assertViewHas('members', fn ($rows) => $rows->total() === 17 && $rows->count() === 15)
            ->assertViewHas('applications', fn ($rows) => $rows->total() === 16 && $rows->count() === 15)
            ->assertDontSee('Other Person')->assertDontSee('Foreign Application');
        $this->get('/membership?tab=members&members_page=2')->assertOk()
            ->assertViewHas('members', fn ($rows) => $rows->currentPage() === 2 && $rows->count() === 2)
            ->assertViewHas('applications', fn ($rows) => $rows->currentPage() === 1);
        $this->get('/membership?tab=applications&page=2')->assertOk()
            ->assertViewHas('applications', fn ($rows) => $rows->currentPage() === 2 && $rows->count() === 1)
            ->assertViewHas('members', fn ($rows) => $rows->currentPage() === 1);
        $this->get('/membership?tab=applications&search=Listed&status=Approved')->assertOk()
            ->assertViewHas('members', fn ($rows) => $rows->total() === 16)
            ->assertViewHas('applications', fn ($rows) => $rows->total() === 0);
        // Empty-state messages belong to the selected register.
        $this->get('/membership?tab=members&search=NoMatch')
            ->assertOk()
            ->assertSee('No official members found');

        $this->get('/membership?tab=applications&search=NoMatch')
            ->assertOk()
            ->assertSee('No applications found');
        $this->get('/membership/applications/'.$foreign)->assertForbidden();
    }

    public function test_officer_record_dialog_uses_authorized_details_and_preserves_other_role_views(): void
    {
        $application = DB::table('member_applications')->insertGetId([
            'association_id' => 1, 'first_name' => 'Reviewed', 'last_name' => 'Applicant',
            'birthday' => '1990-01-01', 'status_id' => 3,
            'reviewed_at' => '2025-01-02 10:00:00', 'reviewed_by_member_id' => 1,
            'rejection_reason' => 'Missing required information.',
        ]);
        $this->get('/membership/applications/'.$application)->assertOk()
            ->assertViewIs('field-officer-user.members.record')
            ->assertSee('data-record-content', false)->assertSee('Missing required information.')
            ->assertSee('Representative One')->assertDontSee('name="review_passphrase"', false);
        $this->get('/membership/members/1')->assertOk()
            ->assertViewIs('field-officer-user.members.record')->assertSee('MEMBER-000001');
        $this->withSession($this->sessionFor(3, 'Association Member'))
            ->get('/membership/applications/'.$application)->assertOk()
            ->assertViewIs('shared.membership.application');
        $this->withSession($this->sessionFor(1, 'System Administrator'))
            ->get('/membership/members/1')->assertOk()->assertViewIs('shared.membership.member');
    }

    public function test_delivery_allows_only_date_and_checks_nested_ownership(): void
    {
        $url = '/officer/projects/1/materials/1/delivery';
        $this->patch($url, ['delivery_date' => '2025-01-10'])->assertRedirect('/officer/projects/1')->assertSessionHasNoErrors();
        $this->assertSame('2025-01-10', DB::table('project_materials')->where('id', 1)->value('delivery_date'));
        foreach (['item_name' => 'Forged', 'quantity' => 99, 'unit' => 'box', 'unit_cost' => 0, 'status_id' => 2, 'project_id' => 2] as $field => $value) {
            $this->patchJson($url, ['delivery_date' => null, $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->patchJson('/officer/projects/1/materials/2/delivery', ['delivery_date' => null])->assertNotFound();
        $this->patchJson('/officer/projects/2/materials/2/delivery', ['delivery_date' => null])->assertNotFound();
        $this->assertSame(1, DB::table('audit_logs')->where('module', 'Project Materials')->where('action_type', 'UPDATE')->count());
        $this->assertEquals(2, DB::table('project_materials')->where('id', 1)->value('quantity'));
    }

    public function test_training_dialog_shows_scoped_totals_projects_and_read_only_dates(): void
    {
        DB::table('training_participants')->insert([
            ['training_id' => 1, 'member_id' => 1, 'attendance_status_id' => 5],
            ['training_id' => 1, 'member_id' => 2, 'attendance_status_id' => 1],
        ]);
        $this->get('/officer/trainings')->assertOk()->assertSee('1 / 1 recorded');
        $this->get('/officer/trainings/1?details=1')->assertOk()
            ->assertSee('Training purpose')->assertSee('After proposal acceptance')
            ->assertSee('1 of 1 attendance records finalized')->assertSee('Assigned project')
            ->assertDontSee('Foreign project')->assertDontSee('Stage')->assertDontSee('<html', false)
            ->assertDontSee('name="date_conducted"', false)->assertDontSee('name="end_date"', false);
        $this->get('/officer/trainings/2?details=1')->assertNotFound();
        DB::table('training_participants')->where('member_id', 1)->update(['attendance_status_id' => null]);
        $this->get('/officer/trainings/1?details=1')->assertOk()->assertSee('0 of 1 attendance records finalized');
        $this->putJson('/officer/trainings/1', ['venue' => 'Hall', 'conducted_by' => 'BFAR', 'date_conducted' => '2030-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_conducted');
    }

    public function test_training_fields_attendance_duplicates_and_relationships(): void
    {
        $data = ['venue' => 'New venue', 'conducted_by' => 'BFAR facilitator', 'remarks' => 'Updated information'];
        $this->put('/officer/trainings/1', $data)->assertRedirect('/officer/trainings/1')->assertSessionHasNoErrors();
        foreach (['stage' => 'terminated', 'date_conducted' => '2026-01-01', 'end_date' => '2026-01-02', 'association_id' => 2, 'title' => 'Changed', 'training_cost' => 100] as $key => $value) {
            $this->putJson('/officer/trainings/1', [...$data, $key => $value])->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $this->putJson('/officer/trainings/2', $data)->assertNotFound();
        $this->post('/officer/trainings/1/participants', ['member_id' => 1])->assertSessionHasNoErrors();
        $this->postJson('/officer/trainings/1/participants', ['member_id' => 1])->assertUnprocessable();
        $this->postJson('/officer/trainings/1/participants', ['member_id' => 2])->assertUnprocessable();
        $participant = (int) DB::table('training_participants')->value('id');
        $this->patch('/officer/trainings/1/participants/'.$participant, ['attendance_status_id' => 5])->assertSessionHasNoErrors();
        $this->patchJson('/officer/trainings/1/participants/'.$participant, ['attendance_status_id' => 7])->assertUnprocessable();
        $this->patchJson('/officer/trainings/2/participants/'.$participant, ['attendance_status_id' => 5])->assertNotFound();
        $this->deleteJson('/officer/trainings/1/participants/'.$participant)->assertStatus(405);
        $this->assertSame('accepted', DB::table('trainings')->where('id', 1)->value('stage'));
        DB::table('members')->where('id', 1)->update(['is_archived' => true]);
        $this->patchJson('/officer/trainings/1/participants/'.$participant, ['attendance_status_id' => 6])->assertNotFound();
    }

    public function test_reassignment_and_archived_parents_are_checked_on_save(): void
    {
        $this->get('/officer/projects/1')->assertOk();
        $this->get('/officer/trainings/1')->assertOk();
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 1]);
        $this->patchJson('/officer/projects/1/materials/1/delivery', ['delivery_date' => null])->assertNotFound();
        $this->putJson('/officer/trainings/1', ['venue' => 'Hall', 'conducted_by' => 'BFAR'])->assertNotFound();
        $this->postJson('/officer/trainings/1/participants', ['member_id' => 1])->assertNotFound();
        $this->get('/officer/reports?year=2025')->assertOk()->assertSee('No records found for the selected criteria')->assertDontSee('Assigned project');
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 2, 'is_archived' => true]);
        $this->patchJson('/officer/projects/1/materials/1/delivery', ['delivery_date' => null])->assertForbidden();
        $this->putJson('/officer/trainings/1', ['venue' => 'Hall', 'conducted_by' => 'BFAR'])->assertForbidden();
        DB::table('associations')->where('id', 1)->update(['is_archived' => false]);
        DB::table('projects')->where('id', 1)->update(['is_archived' => true]);
        DB::table('trainings')->where('id', 1)->update(['is_archived' => true]);
        $this->patchJson('/officer/projects/1/materials/1/delivery', ['delivery_date' => null])->assertForbidden();
        $this->postJson('/officer/trainings/1/participants', ['member_id' => 1])->assertUnprocessable();
        $this->assertNull(DB::table('project_materials')->where('id', 1)->value('delivery_date'));
    }

    public function test_reports_and_exports_use_current_scope_and_training_attendance(): void
    {
        DB::table('training_participants')->insert([['training_id' => 1, 'member_id' => 1, 'attendance_status_id' => 5], ['training_id' => 2, 'member_id' => 2, 'attendance_status_id' => 5]]);
        $data = app(ReportsService::class)->overview(['year' => 2025], User::with('role')->findOrFail(2));
        $this->assertEquals(150, $data['incomeTotal']);
        $this->assertSame(1, $data['rows']->count());
        $this->assertEquals(1, $data['trainedMembers']->first()->members);
        foreach (['/officer/reports', '/officer/reports/export'] as $path) {
            $this->get($path.'?year=2025')->assertOk()->assertSee('Assigned Association')->assertSee('Assigned project')->assertDontSee('Other Association')->assertDontSee('Foreign project');
            $this->getJson($path.'?year=2025&association_id=2')->assertNotFound();
        }
        DB::table('associations')->where('id', 1)->update(['name' => '=1+1']);
        $this->get('/officer/reports/export?year=2025')->assertSee("'=1+1", false);
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => null]);
        $this->get('/officer/reports/export?year=2025')->assertOk()->assertSee('No records found for the selected criteria')->assertDontSee('Assigned project');
    }

    public function test_monitoring_derives_identity_rejects_duplicates_and_foreign_records(): void
    {
        $data = ['project_id' => 1, 'quarter_id' => 2, 'year' => 2025, 'target_output' => 100, 'actual_output' => 80, 'association_id' => 2, 'created_by' => 1];
        $this->post('/monitoring/production', $data)->assertSessionHasNoErrors();
        $record = DB::table('monitoring_production')->where('quarter_id', 2)->sole();
        $this->assertSame(1, (int) $record->association_id);
        $this->assertSame(2, (int) $record->created_by);
        $this->put('/monitoring/production/'.$record->id, [...$data, 'actual_output' => 90])->assertSessionHasNoErrors();
        $this->postJson('/monitoring/production', $data)->assertUnprocessable();
        $this->postJson('/monitoring/production', [...$data, 'target_output' => -1])->assertUnprocessable();
        foreach (['production', 'income', 'materials'] as $type) {
            $this->get('/monitoring/'.$type.'/2/edit')->assertNotFound();
        }
        $this->postJson('/monitoring/income', ['project_id' => 2, 'year' => 2025, 'month' => 2, 'gross_income' => 10])->assertNotFound();
        $this->postJson('/monitoring/materials', ['project_id' => 1, 'project_material_id' => 2, 'condition_status_id' => 7])->assertUnprocessable();
        $this->post('/monitoring/income', ['project_id' => 1, 'year' => 2025, 'month' => 2, 'gross_income' => 10])->assertSessionHasNoErrors();
        $this->put('/monitoring/materials/1', ['project_id' => 1, 'project_material_id' => 1, 'condition_status_id' => 8])->assertSessionHasNoErrors();
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 1]);
        $this->putJson('/monitoring/production/'.$record->id, $data)->assertNotFound();
    }

    public function test_denials_are_audited_without_secrets_or_success_events(): void
    {
        $this->getJson('/officer/projects/2?password=private-value')->assertNotFound();
        $this->get('/admin/audit-logs')->assertRedirect('/officer/dashboard');
        $events = DB::table('audit_logs')->where('action_type', 'UNAUTHORIZED_ACCESS')->get();
        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertSame(2, (int) $event->user_id);
            $this->assertStringNotContainsString('private-value', $event->details);
            $this->assertStringNotContainsString('password', $event->details);
        }
        $this->assertSame(0, DB::table('audit_logs')->where('action_type', 'UPDATE')->count());
    }

    public function test_audit_failure_rolls_back_delivery_and_training_changes(): void
    {
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_officer_changes CHECK (action_type <> 'UPDATE')");
        $this->patchJson('/officer/projects/1/materials/1/delivery', ['delivery_date' => '2025-01-10'])->assertStatus(503);
        $this->putJson('/officer/trainings/1', ['venue' => 'Changed hall', 'conducted_by' => 'BFAR'])->assertStatus(503);
        $this->assertNull(DB::table('project_materials')->where('id', 1)->value('delivery_date'));
        $this->assertSame('Hall', DB::table('trainings')->where('id', 1)->value('venue'));
    }

    public function test_development_setup_preserves_name_and_assignments(): void
    {
        $this->artisan('assocmap:prepare-officer', ['--email' => 'officer@example.test'])
            ->expectsQuestion('Development password', 'Fixture-Only-2026')
            ->expectsQuestion('Confirm password', 'Fixture-Only-2026')->assertSuccessful();
        $user = User::findOrFail(2);
        $this->assertSame('Officer', $user->name);
        $this->assertTrue(Hash::check('Fixture-Only-2026', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertSame(2, (int) DB::table('associations')->where('id', 1)->value('field_officer_id'));
    }

    public function test_archive_filter_takes_precedence_over_operational_status(): void
    {
        $inactive = DB::table('statuses')->insertGetId(['status_name' => 'Inactive']);
        DB::table('associations')->insert([
            ['name' => 'Archived assigned', 'field_officer_id' => 2, 'status_id' => 4, 'is_archived' => true],
            ['name' => 'Inactive assigned', 'field_officer_id' => 2, 'status_id' => $inactive, 'is_archived' => false],
        ]);
        $this->get('/officer/associations?status_id=4')->assertOk()->assertSee('Assigned Association')->assertDontSee('Archived assigned')->assertDontSee('Inactive assigned');
        $this->get('/officer/associations?status_id='.$inactive)->assertOk()->assertSee('Inactive assigned')->assertDontSee('Archived assigned')->assertDontSee('Assigned Association');
        $this->get('/officer/associations?status_id=archived')->assertOk()->assertSee('Archived assigned')->assertDontSee('Assigned Association');
        $this->getJson('/officer/associations?status_id=5')->assertUnprocessable();
    }

    public function test_filters_show_feedback_and_association_context_without_foreign_disclosure(): void
    {
        foreach (['associations', 'projects', 'trainings'] as $module) {
            $url = '/officer/'.$module;
            $this->from($url.'?page=invalid')->get($url.'?page=invalid')->assertRedirect($url)->assertSessionHasErrors('page');
            $this->get($url)->assertOk()->assertSee('Please correct the following:');
        }
        foreach (['projects', 'trainings'] as $module) {
            $this->get('/officer/'.$module.'?association_id=1')->assertOk()->assertSee('Association context')->assertSee('Assigned Association')->assertSee('Show all assigned associations');
            foreach ([2, 999999] as $id) {
                $this->get('/officer/'.$module.'?association_id='.$id)->assertNotFound();
            }
        }
        foreach (['/officer/reports', '/officer/reports/export'] as $url) {
            foreach ([2, 999999] as $id) {
                $this->getJson($url.'?association_id='.$id)->assertNotFound();
            }
            $this->get($url.'?year=invalid')->assertRedirect('/officer/reports')->assertSessionHasErrors('year');
        }
        $this->get('/monitoring?type=invalid')->assertRedirect('/monitoring')->assertSessionHasErrors('type');
        $this->get('/monitoring?project_id=2')->assertNotFound();
    }

    public function test_future_attendance_is_explained_and_backend_still_rejects_it(): void
    {
        DB::table('trainings')->where('id', 1)->update(['date_conducted' => now('Asia/Manila')->addDay()->toDateString()]);
        $this->post('/officer/trainings/1/participants', ['member_id' => 1])->assertSessionHasNoErrors();
        $participant = DB::table('training_participants')->sole()->id;
        $this->get('/officer/trainings/1')->assertOk()->assertSee('Present and Absent attendance can only be recorded on or after the training date.')
            ->assertSee('value="5" disabled', false)->assertSee('value="6" disabled', false);
        $this->patchJson('/officer/trainings/1/participants/'.$participant, ['attendance_status_id' => 5])->assertUnprocessable();
        $this->assertSame(1, (int) DB::table('training_participants')->value('attendance_status_id'));
    }

    public function test_failed_row_forms_preserve_only_the_attempted_record_values(): void
    {
        DB::table('project_materials')->insert(['project_id' => 1, 'item_name' => 'Second material', 'delivery_date' => '2025-02-01']);
        $this->from('/officer/projects/1')->patch('/officer/projects/1/materials/1/delivery', ['delivery_date' => 'invalid-date', '_material_id' => 1])->assertSessionHasErrors('delivery_date');
        $this->get('/officer/projects/1')->assertOk()->assertSee('value="invalid-date"', false)->assertSee('value="2025-02-01"', false);
        $this->from('/officer/trainings/1')->put('/officer/trainings/1', ['venue' => 'Attempted venue', 'conducted_by' => '', 'remarks' => 'Keep these notes'])->assertSessionHasErrors('conducted_by');
        $this->get('/officer/trainings/1')->assertOk()->assertSee('Attempted venue')->assertSee('Keep these notes');
    }

    public function test_foreign_operational_writes_and_administrative_lifecycle_are_denied(): void
    {
        $this->postJson('/officer/trainings/2/participants', ['member_id' => 2])->assertNotFound();
        $this->postJson('/monitoring/production', ['project_id' => 2, 'quarter_id' => 2, 'year' => 2025, 'target_output' => 10, 'actual_output' => 5])->assertNotFound();
        foreach (['production' => ['quarter_id' => 1, 'year' => 2025, 'target_output' => 10, 'actual_output' => 5], 'income' => ['month' => 1, 'year' => 2025, 'gross_income' => 10], 'materials' => ['project_material_id' => 2, 'condition_status_id' => 7]] as $type => $data) {
            $this->putJson('/monitoring/'.$type.'/2', ['project_id' => 2, ...$data])->assertNotFound();
        }
        foreach (['associations', 'projects', 'trainings'] as $module) {
            $this->put('/admin/'.$module.'/2', [])->assertRedirect('/officer/dashboard');
            $this->patch('/admin/'.$module.'/2/archive', [])->assertRedirect('/officer/dashboard');
            $this->delete('/officer/'.$module.'/2')->assertStatus(405);
        }
        $this->assertFalse((bool) DB::table('associations')->where('id', 2)->value('is_archived'));
    }
}
