<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Association;
use App\Models\Project;
use App\Services\AssociationManagementService;
use App\Services\GisIndexService;
use App\Services\ProjectManagementService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\GisDatabaseTestCase;

final class GisPublicationImpactTest extends GisDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Extend only the isolated rollback-only test schema.
        DB::unprepared(<<<'SQL'
            ALTER TABLE gis_locations
                ADD COLUMN location_name varchar,
                ADD COLUMN latitude numeric,
                ADD COLUMN longitude numeric,
                ADD COLUMN created_at timestamp;

            UPDATE gis_locations
            SET location_name = 'Assigned site',
                latitude = 10,
                longitude = 123,
                is_published = true,
                created_at = now(),
                updated_at = now();

            ALTER TABLE projects
                ADD COLUMN program_component_id bigint,
                ADD COLUMN implementation_date date,
                ADD COLUMN terminated_on date,
                ADD COLUMN status_id bigint,
                ADD COLUMN remarks text,
                ADD COLUMN budget numeric(14,2),
                ADD COLUMN created_at timestamp,
                ADD COLUMN updated_at timestamp;

            INSERT INTO statuses (status_name) VALUES ('Ongoing');

            INSERT INTO projects (
                association_id, title, commodity_type,
                program_component_id, implementation_date,
                status_id, is_archived, created_at, updated_at
            )
            VALUES (
                1, 'Coastal livelihood', 'Milkfish',
                1, '2025-01-01',
                (SELECT id FROM statuses WHERE status_name = 'Ongoing'),
                false, now(), now()
            );

            UPDATE gis_locations SET project_id = 1 WHERE id = 1;

            INSERT INTO gis_locations (
                association_id, location_name, latitude, longitude,
                is_published, created_at, updated_at
            )
            VALUES (
                2, 'Other site', 11, 124,
                true, now(), now()
            );
        SQL);
    }

    private function changeAssociation(array $changes): void
    {
        $association = Association::findOrFail(1);

        app(AssociationManagementService::class)->update(
            $association,
            $this->payload([
                'name' => $association->name,
                ...$changes,
            ]),
            1
        );
    }

    private function projectPayload(array $changes = []): array
    {
        return array_replace([
            'association_id' => 1,
            'title' => 'Coastal livelihood',
            'commodity_type' => 'Milkfish',
            'program_component_id' => 1,
            'implementation_date' => '2025-01-01',
            'status_id' => DB::table('statuses')
                ->where('status_name', 'Ongoing')
                ->value('id'),
            'remarks' => null,
        ], $changes);
    }

    private function revision(): string
    {
        return app(GisIndexService::class)
            ->overview()['records']
            ->firstWhere('id', 1)['revision'];
    }

    public function test_reactivation_requires_explicit_publication(): void
    {
        $inactive = DB::table('statuses')
            ->where('status_name', 'Inactive')->value('id');

        $active = DB::table('statuses')
            ->where('status_name', 'Active')->value('id');

        $oldRevision = $this->revision();

        $this->changeAssociation(['status_id' => $inactive]);

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'is_published' => false,
        ]);

        // Another association's publication must remain untouched.
        $this->assertDatabaseHas('gis_locations', [
            'id' => 2,
            'is_published' => true,
        ]);

        $this->changeAssociation(['status_id' => $active]);

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'is_published' => false,
        ]);

        $this->getJson('/map/locations?search=Assigned')
            ->assertOk()
            ->assertJsonCount(0, 'records');

        $this->withSession($this->sessionFor(2, 'Field Officer'));

        // The automatic change invalidates the previous GIS form revision.
        $this->patchJson('/officer/gis/1/publish', [
            'revision' => $oldRevision,
            'confirmed' => true,
        ])->assertConflict();

        $this->patchJson('/officer/gis/1/publish', [
            'revision' => $this->revision(),
            'confirmed' => true,
        ])->assertOk();

        $this->getJson('/map/locations?search=Assigned')
            ->assertOk()
            ->assertJsonCount(1, 'records');

        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('module', 'GIS')
                ->where('record_id', 1)
                ->where('action_type', 'UNPUBLISH')
                ->count()
        );
    }

    public function test_public_association_changes_unpublish_but_address_does_not(): void
    {
        // Address is not part of the public GIS response.
        $this->changeAssociation(['address' => 'Updated internal address']);

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'is_published' => true,
        ]);

        $this->changeAssociation(['name' => 'Renamed association']);

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'is_published' => false,
        ]);

        $audit = DB::table('audit_logs')
            ->where('module', 'GIS')
            ->where('record_id', 1)
            ->where('action_type', 'UNPUBLISH')
            ->first();

        $this->assertNotNull($audit);

        $details = json_decode($audit->details, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            'Renamed association',
            $details['after']['association_name']
        );

        $this->assertArrayNotHasKey('address', $details['after']);
        $this->assertSame(1, (int) $audit->user_id);
    }

    public function test_project_public_changes_unpublish_but_remarks_do_not(): void
    {
        $service = app(ProjectManagementService::class);

        $service->updateProject(
            Project::findOrFail(1),
            $this->projectPayload(['remarks' => 'Internal progress note']),
            1
        );

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'is_published' => true,
        ]);

        $service->updateProject(
            Project::findOrFail(1),
            $this->projectPayload(['title' => 'Updated coastal project']),
            1
        );

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'is_published' => false,
        ]);

        $this->assertDatabaseHas('gis_locations', [
            'id' => 2,
            'is_published' => true,
        ]);
    }

    public function test_project_archive_unpublishes_without_archiving_location(): void
    {
        app(ProjectManagementService::class)->archiveProject(
            Project::findOrFail(1),
            1
        );

        $this->assertDatabaseHas('projects', [
            'id' => 1,
            'is_archived' => true,
        ]);

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'is_published' => false,
            'archived_at' => null,
        ]);
    }

    public function test_failed_gis_audit_rolls_back_the_association_change(): void
    {
        $originalName = Association::findOrFail(1)->name;

        DB::statement(
            "ALTER TABLE audit_logs ADD CONSTRAINT reject_impact_audit
             CHECK (module <> 'GIS')"
        );

        try {
            $this->changeAssociation(['name' => 'Must not persist']);
            $this->fail('The failed GIS audit must cancel the update.');
        } catch (QueryException $error) {
            $this->assertSame('23514', (string) $error->getCode());
        }

        $this->assertDatabaseHas('associations', [
            'id' => 1,
            'name' => $originalName,
        ]);

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'is_published' => true,
        ]);
    }
}