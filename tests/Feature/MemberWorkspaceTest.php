<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{Member, User};
use App\Services\MembershipWorkflowService;
use Illuminate\Support\Facades\DB;
use Tests\Support\MembershipDatabaseTestCase;

final class MemberWorkspaceTest extends MembershipDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(<<<'SQL'
            CREATE TABLE program_components (id bigserial PRIMARY KEY, name varchar);
            INSERT INTO program_components (name) VALUES ('Aquaculture');
            ALTER TABLE associations ADD program_component_id bigint, ADD status_id bigint, ADD address text, ADD date_joined date;
            UPDATE associations SET program_component_id=1;
            INSERT INTO statuses (status_name) VALUES ('Ongoing'), ('Present');
            CREATE TABLE projects (id bigserial PRIMARY KEY, association_id bigint, title varchar, commodity_type varchar, program_component_id bigint, implementation_date date, terminated_on date, status_id bigint, remarks text, is_archived boolean DEFAULT false, created_at timestamp, updated_at timestamp);
            INSERT INTO projects (association_id,title,program_component_id,status_id) VALUES (1,'Coastal livelihood',1,4),(2,'Foreign project',1,4);
            CREATE TABLE project_materials (id bigserial PRIMARY KEY, project_id bigint, item_name varchar, quantity numeric, unit varchar, unit_cost numeric, status_id bigint, delivery_date date);
            INSERT INTO project_materials (project_id,item_name,quantity,unit,unit_cost) VALUES (1,'Fishing nets',2,'sets',100);
            CREATE TABLE trainings (id bigserial PRIMARY KEY, association_id bigint, title varchar, program_component_id bigint, training_type varchar, venue varchar, date_conducted date, end_date date, stage varchar, conducted_by varchar, remarks text, is_archived boolean DEFAULT false);
            INSERT INTO trainings (association_id,title,program_component_id,training_type,venue,date_conducted,end_date,stage,conducted_by) VALUES (1,'Coastal training',1,'Skills','Municipal Hall','2026-01-01','2026-01-02','accepted','BFAR'),(2,'Foreign workshop',1,'Skills','Hall','2026-01-01','2026-01-02','accepted','BFAR');
            CREATE TABLE training_participants (id bigserial PRIMARY KEY, training_id bigint, member_id bigint, attendance_status_id bigint);
            INSERT INTO training_participants (training_id,member_id,attendance_status_id) VALUES (1,1,5),(1,2,5);
            CREATE TABLE quarters (id bigint PRIMARY KEY, quarter_name varchar);
            INSERT INTO quarters VALUES (1,'Q1');
            CREATE TABLE monitoring_production (id bigserial PRIMARY KEY, association_id bigint, project_id bigint, quarter_id bigint, year integer, target_output numeric, actual_output numeric, remarks text, updated_at timestamp);
            INSERT INTO monitoring_production (association_id,project_id,quarter_id,year,target_output,actual_output,remarks) VALUES (1,1,1,2026,100,80,'Measured in kilograms'),(2,2,1,2026,10,5,'Foreign production'),(1,2,1,2025,1,1,'Mismatched ownership');
        SQL);
        $this->withSession($this->sessionFor(3, 'Association Member'));
    }

    public function test_workspace_pages_are_scoped_and_have_no_admin_navigation(): void
    {
        foreach (['dashboard', 'association', 'members', 'applications', 'projects', 'projects/1', 'trainings', 'trainings/1', 'production'] as $page) {
            $this->get('/member/'.$page)->assertOk()->assertSee('Assigned Association')
                ->assertDontSee('Foreign project')->assertDontSee('Foreign workshop')->assertDontSee('Foreign production')
                ->assertDontSee('Mismatched ownership')->assertDontSee('User Management')->assertDontSee('Area Management');
        }
        $this->get('/member/projects/1')->assertSee('Fishing nets')->assertDontSee('Edit Project');
        $this->get('/member/trainings/1')->assertViewHas('attendance', fn ($counts) => (int) $counts['Present'] === 1);
        $this->get('/member/production')->assertSee('100.00')->assertSee('80.00');
    }

    public function test_foreign_ids_parameters_and_mutation_attempts_cannot_expand_access(): void
    {
        foreach (['projects', 'trainings'] as $type) {
            $this->get('/member/'.$type.'/2')->assertNotFound();
            $this->get('/member/'.$type.'?association_id=2')->assertOk()->assertDontSee('Foreign');
            $this->put('/member/'.$type.'/1', [])->assertStatus(405);
        }
        $this->get('/membership/members/2')->assertForbidden();
        $this->post('/monitoring/production', [])->assertForbidden();
        $this->put('/monitoring/production/1', [])->assertForbidden();
        $this->patch('/admin/gis/1/publish', [])->assertRedirect();
    }

    public function test_filters_empty_unassigned_and_archived_states(): void
    {
        $this->get('/member/projects?search=not-present')->assertOk()->assertSee('No projects found');
        $this->get('/member/production?year=2025')->assertOk()->assertSee('No production records found');
        $this->get('/member/projects?status[]=x')->assertRedirect('/member/projects')->assertSessionHasErrors('status');
        DB::table('members')->where('id', 1)->update(['is_archived' => true]);
        $this->get('/member/members?status=Archived')->assertSee('Representative One')->assertSee('Archived');
        DB::table('associations')->where('id', 1)->update(['is_archived' => true]);
        $this->get('/member/applications')->assertSee('This association is archived')->assertDontSee('Submit application');
        DB::table('users')->where('id', 3)->update(['association_id' => null]);
        $this->get('/member/dashboard')->assertOk()->assertSee('no association assigned')->assertDontSee('Coastal livelihood');
    }

    public function test_wrong_role_and_deactivated_sessions_cannot_enter_workspace(): void
    {
        foreach ([1 => 'System Administrator', 2 => 'Field Officer'] as $id => $role) {
            $this->withSession($this->sessionFor($id, $role))->get('/member/projects')->assertRedirect();
        }
        $session = $this->sessionFor(3, 'Association Member');
        DB::table('users')->where('id', 3)->update(['is_active' => false]);
        $this->withSession($session)->get('/member/dashboard')->assertRedirect('/login');
    }

    public function test_review_actions_need_private_verification_and_replacement_revokes_unlock(): void
    {
        $workflow = app(MembershipWorkflowService::class);
        $workflow->setReviewPassphrase(User::findOrFail(1), Member::findOrFail(1), 'Member-review-private');
        $application = $workflow->submit(User::findOrFail(3), ['first_name' => 'New', 'last_name' => 'Applicant', 'birthday' => '1990-01-01', 'sex_id' => 1]);
        $url = '/membership/applications/'.$application->id;
        $this->get($url)->assertOk()->assertDontSee('data-representative-review', false);
        $this->post($url.'/review-access', ['review_passphrase' => 'wrong'])->assertSessionHas('error');
        $this->get($url)->assertDontSee('data-representative-review', false);
        $this->post($url.'/review-access', ['review_passphrase' => 'Member-review-private'])->assertSessionHas('success');
        $this->get($url)->assertSee('data-representative-review', false)->assertDontSee('Member-review-private');
        DB::table('members')->where('id', 1)->update(['review_passphrase_hash' => null]);
        $this->get($url)->assertDontSee('data-representative-review', false);
    }

    public function test_database_failure_has_a_safe_recovery_page(): void
    {
        DB::statement('DROP TABLE monitoring_production');
        $this->get('/member/production')->assertStatus(503)->assertSee('Records are temporarily unavailable')->assertDontSee('SQLSTATE');
    }
}
