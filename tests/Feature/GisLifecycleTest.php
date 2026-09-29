<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GisIndexService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GisDatabaseTestCase;

final class GisLifecycleTest extends GisDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared("ALTER TABLE gis_locations ADD COLUMN location_name varchar, ADD COLUMN latitude numeric, ADD COLUMN longitude numeric, ADD COLUMN created_at timestamp;
            UPDATE gis_locations SET location_name='Original site', latitude=10, longitude=123, updated_at=now();
            INSERT INTO projects (association_id,title,commodity_type,is_archived) VALUES
            (1,'Coastal livelihood','Milkfish',false),(2,'Other association project','Seaweed',false),(1,'Old project','Crab',true);");
        $this->withSession($this->sessionFor(1, 'System Administrator'));
    }

    private function locationPayload(array $extra = []): array
    {
        return array_replace(['association_id' => 1, 'location_name' => 'Linked site', 'latitude' => 11, 'longitude' => 124,
            'submission_token' => (string) Str::uuid(), 'project_id' => 1], $extra);
    }

    private function revision(int $id): string
    {
        return app(GisIndexService::class)->overview()['records']->firstWhere('id', $id)['revision'];
    }

    public function test_project_links_are_optional_scoped_and_public_fields_follow_project_archival(): void
    {
        $this->assertNull(DB::table('gis_locations')->where('id', 1)->value('project_id'));
        foreach ([2, 3, 999] as $projectId) {
            $this->postJson('/admin/gis', $this->locationPayload(['project_id' => $projectId]))->assertUnprocessable()->assertJsonValidationErrors('project_id');
        }
        $id = $this->postJson('/admin/gis', $this->locationPayload())->assertCreated()->json('id');
        $this->patchJson('/admin/gis/'.$id.'/publish', ['revision' => $this->revision($id)])->assertOk();
        $this->getJson('/map/locations?commodity=Milkfish')->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.project_title', 'Coastal livelihood')->assertJsonPath('records.0.commodity', 'Milkfish')
            ->assertDontSee('project_id')->assertDontSee('remarks');
        $this->postJson('/admin/gis/export', ['format' => 'csv', 'commodity' => 'Milkfish'])->assertOk();
        DB::table('projects')->where('id', 1)->update(['is_archived' => true]);
        $this->getJson('/map/locations?commodity=Milkfish')->assertJsonCount(0, 'records');
        $this->getJson('/map/locations?search=Linked')->assertJsonCount(1, 'records')->assertJsonPath('records.0.project_title', null)->assertJsonPath('records.0.commodity', null);
        $this->putJson('/admin/gis/'.$id, $this->locationPayload(['revision' => $this->revision($id), 'location_name' => 'Corrected site']))->assertOk();
        $this->putJson('/admin/gis/'.$id, $this->locationPayload(['revision' => $this->revision($id), 'project_id' => null]))->assertOk();
        $this->assertNull(DB::table('gis_locations')->where('id', $id)->value('project_id'));
    }

    public function test_archive_requires_confirmation_and_revision_then_preserves_history_and_removes_visibility(): void
    {
        $revision = $this->revision(1);
        $this->patchJson('/admin/gis/1/archive', compact('revision'))->assertUnprocessable();
        $this->patchJson('/admin/gis/1/archive', ['revision' => str_repeat('0', 32).':0', 'confirmed' => true])->assertConflict();
        foreach ([1, 2] as $attempt) {
            $this->patchJson('/admin/gis/1/archive', ['revision' => $revision, 'confirmed' => true])->assertOk();
        }
        $row = DB::table('gis_locations')->where('id', 1)->first();
        $this->assertNotNull($row->archived_at);
        $this->assertFalse($row->is_published);
        $this->assertSame('10', $row->latitude);
        $this->assertSame(1, DB::table('audit_logs')->where('module', 'GIS')->where('action_type', 'ARCHIVE')->count());
        $this->getJson('/map/locations')->assertJsonCount(0, 'records');
        $this->getJson('/gis/locations')->assertJsonCount(0, 'records');
        $this->get('/admin/gis')->assertDontSee('Original site');
        $this->postJson('/admin/gis/export', ['format' => 'csv'])->assertUnprocessable();
        $this->patchJson('/admin/gis/1/publish', compact('revision'))->assertConflict();
        $this->putJson('/admin/gis/1', $this->locationPayload(compact('revision')))->assertConflict();
        $this->deleteJson('/admin/gis/1')->assertStatus(405);
    }

    public function test_officer_archive_is_assigned_only_and_audit_failure_rolls_back(): void
    {
        $this->withSession($this->sessionFor(2, 'Field Officer'));
        $revision = $this->revision(1);
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 1]);
        $this->patchJson('/officer/gis/1/archive', ['revision' => $revision, 'confirmed' => true])->assertNotFound();
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 2]);
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT deny_archive CHECK (action_type <> 'ARCHIVE')");
        $this->patchJson('/officer/gis/1/archive', ['revision' => $revision, 'confirmed' => true])->assertStatus(503);
        $this->assertNull(DB::table('gis_locations')->where('id', 1)->value('archived_at'));
        $this->assertTrue(DB::table('gis_locations')->where('id', 1)->value('is_published'));
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT deny_archive');
        $this->patchJson('/officer/gis/1/archive', ['revision' => $revision, 'confirmed' => true])->assertOk();
        $this->withSession($this->sessionFor(3, 'Association Member'));
        $this->patchJson('/officer/gis/1/archive', ['revision' => $revision, 'confirmed' => true])->assertRedirect();
    }

    public function test_database_rejects_cross_association_links_and_published_archives(): void
    {
        foreach ([['project_id' => 2], ['archived_at' => now()]] as $invalid) {
            try {
                DB::transaction(fn () => DB::table('gis_locations')->where('id', 1)->update($invalid));
                $this->fail('The database must reject an inconsistent GIS record.');
            } catch (QueryException $error) {
                $this->assertContains($error->getCode(), ['23503', '23514']);
            }
        }
        DB::table('gis_locations')->where('id', 1)->update(['project_id' => 1]);
        try {
            DB::transaction(fn () => DB::table('projects')->where('id', 1)->update(['association_id' => 2]));
            $this->fail('A linked project cannot move across associations.');
        } catch (QueryException $error) {
            $this->assertSame('23503', $error->getCode());
        }
        $this->assertNull(DB::table('gis_locations')->where('id', 1)->value('archived_at'));
    }
}
