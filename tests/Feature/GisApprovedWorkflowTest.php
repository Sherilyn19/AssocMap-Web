<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GisIndexService;
use Illuminate\Support\Facades\DB;
use Tests\Support\GisDatabaseTestCase;

final class GisApprovedWorkflowTest extends GisDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

            UPDATE associations SET field_officer_id = 1 WHERE id = 2;

            INSERT INTO gis_locations
                (association_id, location_name, latitude, longitude,
                 is_published, created_at, updated_at)
            VALUES
                (2, 'Private other site', 11, 124, false, now(), now());
        SQL);

        $this->withSession($this->sessionFor(2, 'Field Officer'));
    }

    private function revision(int $id = 1): string
    {
        return app(GisIndexService::class)
            ->overview()['records']->firstWhere('id', $id)['revision'];
    }

    private function editPayload(array $extra = []): array
    {
        return array_replace([
            'location_name' => 'Assigned site',
            'latitude' => '10',
            'longitude' => '123',
            'revision' => $this->revision(),
        ], $extra);
    }

    public function test_public_visibility_and_publication_require_active_association(): void
    {
        $this->getJson('/map/locations')
            ->assertOk()
            ->assertJsonCount(1, 'records');

        DB::table('associations')->where('id', 1)->update([
            'status_id' => DB::table('statuses')
                ->where('status_name', 'Inactive')->value('id'),
        ]);

        $this->getJson('/map/locations')
            ->assertOk()
            ->assertJsonCount(0, 'records');

        // Even an already-published flag must not bypass eligibility.
        $this->patchJson('/officer/gis/1/publish', [
            'revision' => $this->revision(),
            'confirmed' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('association_id');

        // An inactive association may still remove its publication flag.
        $this->patchJson('/officer/gis/1/unpublish', [
            'revision' => $this->revision(),
            'confirmed' => true,
        ])->assertOk();

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'is_published' => false,
        ]);
    }

    public function test_confirmation_and_automatic_unpublish_preserve_revision_checks(): void
    {
        $this->patchJson('/officer/gis/1/unpublish', [
            'revision' => $this->revision(),
        ])->assertUnprocessable()->assertJsonValidationErrors('confirmed');

        $old = $this->revision();

        $this->putJson('/officer/gis/1', $this->editPayload([
            'location_name' => 'Updated site',
        ]))->assertOk();

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'location_name' => 'Updated site',
            'is_published' => false,
        ]);

        foreach (['UPDATE', 'UNPUBLISH'] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'module' => 'GIS',
                'record_id' => 1,
                'action_type' => $action,
                'user_id' => 2,
            ]);
        }

        $this->patchJson('/officer/gis/1/publish', [
            'revision' => $old,
            'confirmed' => true,
        ])->assertConflict();

        $this->patchJson('/officer/gis/1/publish', [
            'revision' => $this->revision(),
            'confirmed' => true,
        ])->assertOk();

        $this->getJson('/map/locations')
            ->assertJsonPath('records.0.name', 'Updated site');
    }

    public function test_numeric_formatting_alone_does_not_unpublish(): void
    {
        $this->putJson('/officer/gis/1', $this->editPayload([
            'latitude' => '10.0000',
            'longitude' => '123.0000',
        ]))->assertOk();

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'is_published' => true,
        ]);

        $this->assertSame(
            0,
            DB::table('audit_logs')->where('action_type', 'UNPUBLISH')->count()
        );
    }

    public function test_archives_and_history_follow_current_assignment(): void
    {
        $this->patchJson('/officer/gis/1/archive', [
            'revision' => $this->revision(),
            'confirmed' => true,
        ])->assertOk();

        $this->assertNotNull(
            DB::table('gis_locations')->where('id', 1)->value('archived_at')
        );

        $this->get('/officer/gis/archived')
            ->assertOk()->assertSee('Assigned site')
            ->assertDontSee('Private other site');

        $this->get('/officer/gis/1/history')
            ->assertOk()->assertSee('Archive');

        $this->get('/officer/gis/2/history')->assertNotFound();

        $this->getJson('/map/locations')->assertJsonCount(0, 'records');

        DB::table('associations')->where('id', 1)->update([
            'field_officer_id' => 1,
        ]);

        $this->get('/officer/gis/1/history')->assertNotFound();
        $this->get('/officer/gis/archived')
            ->assertOk()->assertDontSee('Assigned site');

        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->get('/admin/gis/1/history')->assertOk();

        $this->withSession($this->sessionFor(3, 'Association Member'));
        $this->get('/officer/gis/1/history')->assertRedirect();
    }

    public function test_audit_failure_rolls_back_edit_and_unpublication(): void
    {
        DB::statement(
            "ALTER TABLE audit_logs ADD CONSTRAINT reject_gis_audit CHECK (module <> 'GIS')"
        );

        $this->putJson('/officer/gis/1', $this->editPayload([
            'location_name' => 'Must roll back',
        ]))->assertStatus(503);

        $this->assertDatabaseHas('gis_locations', [
            'id' => 1,
            'location_name' => 'Assigned site',
            'is_published' => true,
        ]);
    }
}