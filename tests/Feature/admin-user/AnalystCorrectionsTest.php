<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Association;
use App\Models\Member;
use App\Models\Project;
use App\Models\Training;
use App\Services\AssociationManagementService;
use App\Services\MemberManagementService;
use App\Services\ProjectManagementService;
use App\Services\TrainingManagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AssociationDatabaseTestCase;

final class AnalystCorrectionsTest extends AssociationDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(<<<'SQL'
            ALTER TABLE projects ADD COLUMN title varchar, ADD COLUMN commodity_type varchar,
                ADD COLUMN program_component_id bigint REFERENCES program_components(id), ADD COLUMN implementation_date date,
                ADD COLUMN budget numeric(12,2), ADD COLUMN status_id bigint REFERENCES statuses(id), ADD COLUMN remarks text,
                ADD COLUMN created_at timestamp, ADD COLUMN updated_at timestamp;
            CREATE TABLE project_materials (id bigserial PRIMARY KEY, project_id bigint REFERENCES projects(id), item_name varchar,
                quantity numeric, unit varchar, unit_cost numeric, status_id bigint REFERENCES statuses(id), delivery_date date, created_at timestamp, updated_at timestamp);
            ALTER TABLE trainings ADD COLUMN title varchar, ADD COLUMN program_component_id bigint REFERENCES program_components(id),
                ADD COLUMN training_type varchar, ADD COLUMN venue varchar, ADD COLUMN date_conducted date,
                ADD COLUMN training_cost numeric, ADD COLUMN conducted_by varchar, ADD COLUMN remarks text,
                ADD COLUMN created_at timestamp, ADD COLUMN updated_at timestamp;
            CREATE TABLE training_participants (id bigserial PRIMARY KEY, training_id bigint REFERENCES trainings(id), member_id bigint REFERENCES members(id), attendance_status_id bigint REFERENCES statuses(id), UNIQUE(training_id, member_id));
            INSERT INTO statuses (status_name) VALUES ('Planned'), ('Ongoing'), ('Completed'), ('Delivered');
        SQL);
        $this->migration()->up();
        $this->withSession($this->sessionFor(1, 'System Administrator'));
    }

    private function migration(): object
    {
        return require base_path('database/migrations/2026_09_28_120000_add_analyst_lifecycle_fields.php');
    }

    private function projectData(): array
    {
        return ['association_id' => 1, 'title' => 'Fish project', 'commodity_type' => 'Bangus', 'program_component_id' => 1,
            'implementation_date' => '2025-01-01', 'status_id' => DB::table('statuses')->where('status_name', 'Ongoing')->value('id')];
    }

    private function trainingData(): array
    {
        return ['association_id' => 1, 'title' => 'Fish handling', 'program_component_id' => 1, 'training_type' => 'Workshop',
            'venue' => 'Association hall', 'date_conducted' => '2025-03-10', 'end_date' => '2025-03-12', 'stage' => 'proposal', 'conducted_by' => 'BFAR'];
    }

    public function test_contact_only_update_ignores_forged_identity_and_retains_audit(): void
    {
        $before = Member::findOrFail(1)->getAttributes();
        $this->putJson('/admin/members/1', ['contact_number' => '09123456789', 'first_name' => 'Forged', 'association_id' => 2,
            'birthday' => '2000-01-01', 'role_in_assoc' => 'President', 'date_registered' => '2025-01-01', 'address' => 'Forged'])
            ->assertRedirect();
        $member = Member::findOrFail(1);
        foreach (['first_name', 'association_id', 'birthday', 'role_in_assoc', 'date_registered', 'address'] as $field) {
            $this->assertEquals($before[$field] ?? null, $member->getRawOriginal($field));
        }
        $this->assertSame('09123456789', $member->contact_number);
        app(MemberManagementService::class)->update($member, ['contact_number' => null, 'first_name' => 'Still forged'], 1);
        $this->assertSame($before['first_name'], $member->fresh()->first_name);
        $this->assertTrue(DB::table('audit_logs')->where('module', 'Member')->exists());
    }

    public function test_association_date_is_immutable_at_request_and_service_boundaries(): void
    {
        $association = Association::findOrFail(1);
        $before = $association->date_joined->toDateString();
        $data = $association->only(['name', 'address', 'area_unit_id', 'sub_unit_id', 'program_component_id', 'field_officer_id', 'status_id']);
        $data['date_joined'] = '2099-01-01';
        $this->putJson('/admin/associations/1', $data)->assertSuccessful();
        app(AssociationManagementService::class)->update($association, $data, 1);
        $this->assertSame($before, $association->fresh()->date_joined->toDateString());
    }

    public function test_project_budget_is_ignored_and_related_training_scope_is_explicit(): void
    {
        $this->post('/admin/projects', [...$this->projectData(), 'budget' => 12345])->assertSessionHasNoErrors();
        $project = Project::firstOrFail();
        $this->assertNull(DB::table('projects')->value('budget'));
        DB::table('projects')->where('id', $project->id)->update(['budget' => 500]);
        $this->put('/admin/projects/'.$project->id, [...$this->projectData(), 'budget' => 999, 'terminated_on' => '2025-05-01'])->assertSessionHasNoErrors();
        $this->assertEquals(500, DB::table('projects')->value('budget'));
        $this->assertSame('Ongoing', $project->fresh()->status->status_name);
        app(TrainingManagementService::class)->save($this->trainingData(), 1);
        app(TrainingManagementService::class)->save([...$this->trainingData(), 'association_id' => 2, 'title' => 'Other association training'], 1);
        $this->get('/admin/projects/'.$project->id)->assertOk()->assertSee('Fish handling')->assertSee('Terminated')
            ->assertDontSee('Other association training')->assertDontSee('Budget');
        app(ProjectManagementService::class)->addMaterial($project, ['item_name' => 'Net', 'quantity' => 2, 'unit' => 'pieces',
            'unit_cost' => 100, 'status_id' => DB::table('statuses')->where('status_name', 'Delivered')->value('id'), 'delivery_date' => '2025-02-01'], 1);
        $this->assertEquals(200, $project->materials()->first()->total_cost);
        $this->patch('/admin/projects/'.$project->id.'/archive')->assertSessionHasNoErrors();
        $this->assertTrue($project->fresh()->is_archived);
    }

    public function test_training_date_range_is_validated_and_cannot_be_overwritten(): void
    {
        $this->postJson('/admin/trainings', [...$this->trainingData(), 'end_date' => '2025-03-09'])->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->postJson('/admin/trainings', [...$this->trainingData(), 'stage' => ['proposal', 'accepted']])->assertUnprocessable()->assertJsonValidationErrors('stage');
        $this->post('/admin/trainings', [...$this->trainingData(), 'training_cost' => 999])->assertSessionHasNoErrors();
        $training = Training::firstOrFail();
        DB::table('trainings')->where('id', $training->id)->update(['training_cost' => 200]);
        $data = [...$this->trainingData(), 'date_conducted' => '2099-01-01', 'end_date' => '2099-12-31', 'training_cost' => 1, 'stage' => 'accepted'];
        $this->put('/admin/trainings/'.$training->id, $data)->assertSessionHasNoErrors();
        app(TrainingManagementService::class)->save($data, 1, $training);
        $this->assertSame('2025-03-10', $training->fresh()->date_conducted->toDateString());
        $this->assertSame('2025-03-12', $training->fresh()->end_date->toDateString());
        $this->assertEquals(200, DB::table('trainings')->value('training_cost'));
        $this->get('/admin/trainings/'.$training->id.'/edit')->assertOk()->assertSee('To Date (read-only)')->assertDontSee('name="end_date"', false);
    }

    public function test_migration_rollback_preserves_historical_columns(): void
    {
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('trainings', 'end_date'));
        $this->assertTrue(Schema::hasColumn('trainings', 'date_conducted'));
        $this->assertTrue(Schema::hasColumn('trainings', 'training_cost'));
        $this->assertTrue(Schema::hasColumn('projects', 'budget'));
        $this->migration()->up();
        $this->assertTrue(Schema::hasColumn('projects', 'terminated_on'));
    }
}
