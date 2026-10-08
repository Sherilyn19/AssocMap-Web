<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Association;
use App\Models\GisLocation;
use App\Services\AssociationManagementService;
use App\Services\GisIndexService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GisDatabaseTestCase;

final class GisPublicationTest extends GisDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared("ALTER TABLE gis_locations ADD COLUMN location_name varchar(255), ADD COLUMN latitude numeric, ADD COLUMN longitude numeric, ADD COLUMN created_at timestamp;
            UPDATE gis_locations SET location_name='Landing site', latitude=10, longitude=123, updated_at=now(), created_at=now(), is_published=false;");
        $this->withSession($this->sessionFor(1, 'System Administrator'));
    }

    private function revision(): string
    {
        return app(GisIndexService::class)->overview()['records']->firstWhere('id', 1)['revision'];
    }

    private function createFields(): array
    {
        return ['association_id' => 1, 'location_name' => 'Another site', 'latitude' => '0', 'longitude' => '0', 'submission_token' => (string) Str::uuid()];
    }

    public function test_publication_is_explicit_atomic_and_repeated_action_does_not_toggle(): void
    {
        $revision = $this->revision();
        // Confirm publication while retaining the fields used to test input protection.
        $payload = [
            'revision' => $revision,
            'confirmed' => true,
            'is_published' => false,
            'user_id' => 3,
            'performed_at' => '2000-01-01',
        ];
        $this->patchJson('/admin/gis/1/publish', $payload)->assertOk();
        $this->patchJson('/admin/gis/1/publish', $payload)->assertOk();
        $this->assertTrue((bool) DB::table('gis_locations')->value('is_published'));
        $this->assertSame(1, DB::table('audit_logs')->where('module', 'GIS')->count());
        $event = DB::table('audit_logs')->where('module', 'GIS')->first();
        $this->assertSame(1, $event->user_id);
        $this->assertSame('PUBLISH', $event->action_type);
        $this->assertTrue(json_decode($event->details, true)['after']['is_published']);
        $this->assertStringNotContainsString('2000-01-01', $event->performed_at);
        $this->patchJson('/admin/gis/1/unpublish', ['revision' => $revision, 'confirmed' => true])->assertConflict();
        $latest = $this->revision();
        $this->patchJson('/admin/gis/1/unpublish', ['revision' => $latest, 'confirmed' => true])->assertOk();
        $this->patchJson('/admin/gis/1/unpublish', ['revision' => $latest, 'confirmed' => true])->assertOk();
        $this->assertFalse((bool) DB::table('gis_locations')->value('is_published'));
        $this->assertSame(2, DB::table('audit_logs')->where('module', 'GIS')->count());
    }

    public function test_failed_audit_rolls_back_publication_and_unpublication(): void
    {
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_publication CHECK (module <> 'GIS')");
        $this->patchJson('/admin/gis/1/publish', ['revision' => $this->revision(), 'confirmed' => true])->assertStatus(503)->assertDontSee('SQLSTATE');
        $this->assertFalse((bool) DB::table('gis_locations')->value('is_published'));
        DB::table('gis_locations')->update(['is_published' => true]);
        $this->patchJson('/admin/gis/1/unpublish', ['revision' => $this->revision(), 'confirmed' => true])->assertStatus(503);
        $this->assertTrue((bool) DB::table('gis_locations')->value('is_published'));
    }

    public function test_publication_checks_coordinates_archive_revision_and_missing_ids(): void
    {
        $this->patchJson('/admin/gis/1/publish', [])->assertUnprocessable();
        DB::table('gis_locations')->update(['latitude' => 91]);
        $this->patchJson('/admin/gis/1/publish', ['revision' => $this->revision(), 'confirmed' => true])->assertUnprocessable()->assertJsonValidationErrors('publication');
        DB::table('gis_locations')->update(['is_published' => true]);
        // Removing a bad published record from visibility remains possible.
        $this->patchJson('/admin/gis/1/unpublish', ['revision' => $this->revision(), 'confirmed' => true])->assertOk();
        DB::table('associations')->where('id', 1)->update(['is_archived' => true]);
        foreach (['publish', 'unpublish'] as $action) {
            $this->patchJson('/admin/gis/1/'.$action, ['revision' => $this->revision(), 'confirmed' => true])->assertUnprocessable();
            $this->patchJson('/admin/gis/99999/'.$action, ['revision' => $this->revision(), 'confirmed' => true])->assertNotFound();
        }
    }

    public function test_direct_publication_requests_block_nonadmins_and_require_csrf(): void
    {
        $data = ['revision' => $this->revision(), 'confirmed' => true];
        foreach ([2 => 'dashboard.officer', 3 => 'dashboard.member'] as $id => $route) {
            $this->withSession($this->sessionFor($id, 'System Administrator'));
            foreach (['publish', 'unpublish'] as $action) {
                $this->patchJson('/admin/gis/1/'.$action, $data)->assertRedirect(route($route));
            }
        }
        $this->flushSession();
        $this->patchJson('/admin/gis/1/publish', $data)->assertRedirect(route('login'));
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->patchJson('/admin/gis/1/unpublish', $data)->assertRedirect(route('login'));
        DB::table('users')->where('id', 1)->update(['is_active' => true]);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->app['env'] = 'local';
        foreach (['publish', 'unpublish'] as $action) {
            $this->patchJson('/admin/gis/1/'.$action, $data)->assertStatus(419);
        }
        $this->app['env'] = 'testing';
        $this->deleteJson('/admin/gis/1')->assertStatus(405);
        // Denied requests may create security audits.
        // They must never create a GIS business-change audit.
        $this->assertSame(
            0,
            DB::table('audit_logs')->where('module', 'GIS')->count()
        );
    }

    public function test_one_submission_creates_one_record_and_one_audit_and_changed_payload_is_rejected(): void
    {
        $data = $this->createFields();
        $id = $this->postJson('/admin/gis', $data)->assertCreated()->json('id');
        $this->postJson('/admin/gis', $data)->assertCreated()->assertJsonPath('id', $id);
        $this->postJson('/admin/gis', array_replace($data, ['location_name' => 'Changed']))->assertConflict();
        $this->assertSame(2, DB::table('gis_locations')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'CREATE')->count());
        $this->postJson('/admin/gis', array_replace($data, ['submission_token' => (string) Str::uuid()]))->assertCreated();
        $this->assertSame(3, DB::table('gis_locations')->count());
    }

    public function test_failed_creation_leaves_no_receipt_and_can_be_corrected(): void
    {
        $data = $this->createFields();
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_gis CHECK (module <> 'GIS')");
        $this->postJson('/admin/gis', $data)->assertStatus(503);
        $this->assertSame(0, DB::table('gis_submissions')->count());
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT reject_gis');
        $this->postJson('/admin/gis', $data)->assertCreated();
    }

    public function test_edit_preserves_exact_decimal_text_and_publication_rejects_old_edit(): void
    {
        $latitude = '10.123456789123456789';
        DB::table('gis_locations')->update(['latitude' => $latitude]);
        $record = app(GisIndexService::class)->overview()['records']->firstWhere('id', 1);
        $this->assertSame($latitude, $record['latitude_text']);
        $data = ['location_name' => 'Renamed', 'latitude' => $record['latitude_text'], 'longitude' => $record['longitude_text'], 'revision' => $record['revision']];
        $this->putJson('/admin/gis/1', $data)->assertOk();
        $this->assertSame($latitude, DB::table('gis_locations')->value('latitude'));
        $this->patchJson('/admin/gis/1/publish', ['revision' => $record['revision'], 'confirmed' => true])->assertConflict();
        $this->patchJson('/admin/gis/1/publish', ['revision' => $this->revision(), 'confirmed' => true])->assertOk();
        $this->putJson('/admin/gis/1', $data)->assertConflict();
    }

    public function test_public_query_and_association_archival_preserve_visibility_and_audit_rules(): void
    {
        $this->assertSame(0, GisLocation::publiclyVisible()->count());
        $this->patchJson('/admin/gis/1/publish', ['revision' => $this->revision(), 'confirmed' => true])->assertOk();
        $this->assertSame(1, GisLocation::publiclyVisible()->count());
        app(AssociationManagementService::class)->archive(Association::findOrFail(1), 1);
        $this->assertSame(0, GisLocation::publiclyVisible()->count());
        $this->assertDatabaseHas('audit_logs', ['module' => 'GIS', 'record_id' => 1, 'action_type' => 'UNPUBLISH']);
        // Even an inconsistent flag must not expose an archived association.
        DB::table('gis_locations')->update(['is_published' => true]);
        $this->assertSame(0, GisLocation::publiclyVisible()->count());
    }
}
