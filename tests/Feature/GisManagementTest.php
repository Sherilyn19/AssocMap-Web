<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GisIndexService;
use App\Services\GisManagementService;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssociationDatabaseTestCase;

final class GisManagementTest extends AssociationDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(<<<'SQL'
            ALTER TABLE gis_locations ADD COLUMN location_name varchar(255), ADD COLUMN latitude numeric(10,8), ADD COLUMN longitude numeric(11,8), ADD COLUMN created_at timestamp;
            UPDATE gis_locations SET location_name='Landing site', latitude=10, longitude=123, updated_at=now();
        SQL);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
    }

    private function fields(array $extra = []): array
    {
        return array_replace(['association_id' => 1, 'location_name' => '  New site  ', 'latitude' => 0, 'longitude' => 0], $extra);
    }

    private function revision(int $id = 1): string
    {
        return app(GisIndexService::class)->overview()['records']->firstWhere('id', $id)['revision'];
    }

    public function test_create_is_unpublished_and_audited_and_survives_a_fresh_read(): void
    {
        $response = $this->postJson('/admin/gis', $this->fields(['is_published' => true, 'geom' => 'tampered']))->assertCreated();
        $id = $response->json('id');
        $this->assertDatabaseHas('gis_locations', ['id' => $id, 'location_name' => 'New site', 'latitude' => 0, 'longitude' => 0, 'is_published' => false]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'GIS', 'action_type' => 'CREATE', 'record_id' => $id, 'user_id' => 1]);
        $this->get('/admin/gis')->assertOk()->assertSee('New site');
        $this->assertSame(2, DB::table('gis_locations')->where('association_id', 1)->count());
    }

    public function test_edit_preserves_ownership_and_publication_and_rejects_stale_forms(): void
    {
        $revision = $this->revision();
        $this->putJson('/admin/gis/1', $this->fields(['revision' => $revision, 'association_id' => 2, 'is_published' => false, 'geom' => 'bad']))->assertOk();
        $this->assertDatabaseHas('gis_locations', ['id' => 1, 'association_id' => 1, 'is_published' => true, 'location_name' => 'New site']);
        $this->assertNotSame($revision, $this->revision());
        $this->putJson('/admin/gis/1', $this->fields(['revision' => $revision, 'location_name' => 'Stale overwrite']))->assertStatus(409);
        $this->assertDatabaseMissing('gis_locations', ['location_name' => 'Stale overwrite']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'GIS', 'action_type' => 'UPDATE', 'record_id' => 1]);
        $this->get('/admin/gis')->assertOk()->assertSee('New site');
    }

    public function test_external_publication_change_invalidates_old_revision(): void
    {
        $revision = $this->revision();
        DB::table('gis_locations')->where('id', 1)->update(['is_published' => false]);
        $this->putJson('/admin/gis/1', $this->fields(['revision' => $revision]))->assertStatus(409);
        $this->putJson('/admin/gis/1', $this->fields(['revision' => $this->revision(), 'is_published' => true]))->assertOk();
        $this->assertDatabaseHas('gis_locations', ['id' => 1, 'is_published' => false]);
    }

    public function test_validation_handles_missing_invalid_zero_and_boundary_values(): void
    {
        foreach ([null, '', [], 'NaN', 'INF', '1e999', -91, 91] as $latitude) {
            $this->postJson('/admin/gis', $this->fields(['latitude' => $latitude]))->assertUnprocessable()->assertJsonValidationErrors('latitude');
        }
        foreach ([-181, 181, 'invalid'] as $longitude) {
            $this->postJson('/admin/gis', $this->fields(['longitude' => $longitude]))->assertUnprocessable()->assertJsonValidationErrors('longitude');
        }
        foreach (['', '   ', str_repeat('a', 256)] as $name) {
            $this->postJson('/admin/gis', $this->fields(['location_name' => $name]))->assertUnprocessable()->assertJsonValidationErrors('location_name');
        }
        $this->postJson('/admin/gis', $this->fields(['association_id' => 99999]))->assertUnprocessable();
        $this->putJson('/admin/gis/1', $this->fields())->assertUnprocessable()->assertJsonValidationErrors('revision');
        foreach ([[-90, -180], [90, 180], [0, 0]] as [$latitude, $longitude]) {
            $this->postJson('/admin/gis', $this->fields(compact('latitude', 'longitude')))->assertCreated();
        }
    }

    public function test_archived_and_missing_records_cannot_be_changed(): void
    {
        $revision = $this->revision();
        DB::table('associations')->where('id', 1)->update(['is_archived' => true]);
        $this->postJson('/admin/gis', $this->fields())->assertUnprocessable();
        $this->putJson('/admin/gis/1', $this->fields(['revision' => $revision]))->assertUnprocessable();
        $this->putJson('/admin/gis/99999', $this->fields(['revision' => $revision]))->assertNotFound();
        // Service repeats this check even if archival occurred after request validation.
        try {
            app(GisManagementService::class)->create($this->fields(), 1);
            $this->fail('An archived association must reject writes.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('association_id', $error->errors());
        }
        $this->assertSame(1, DB::table('gis_locations')->count());
    }

    public function test_permission_checks_apply_to_both_write_routes(): void
    {
        $data = $this->fields(['revision' => $this->revision()]);
        foreach ([2 => 'dashboard.officer', 3 => 'dashboard.member'] as $id => $route) {
            $this->withSession($this->sessionFor($id, 'System Administrator'));
            $this->postJson('/admin/gis', $data)->assertRedirect(route($route));
            $this->putJson('/admin/gis/1', $data)->assertRedirect(route($route));
        }
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->postJson('/admin/gis', $data)->assertRedirect(route('login'));
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->putJson('/admin/gis/1', $data)->assertRedirect(route('login'));
        $this->flushSession();
        $this->postJson('/admin/gis', $data)->assertRedirect(route('login'));
        $this->putJson('/admin/gis/1', $data)->assertRedirect(route('login'));
        $this->assertSame(1, DB::table('gis_locations')->count());
    }

    public function test_audit_failure_rolls_back_create_and_edit(): void
    {
        $revision = $this->revision();
        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT reject_gis_audit CHECK (module <> \'GIS\')');
        $this->postJson('/admin/gis', $this->fields())->assertStatus(503)->assertJsonPath('uncertain', true)->assertDontSee('SQLSTATE');
        $this->assertSame(1, DB::table('gis_locations')->count());
        $this->putJson('/admin/gis/1', $this->fields(['revision' => $revision]))->assertStatus(503);
        $this->assertDatabaseHas('gis_locations', ['id' => 1, 'location_name' => 'Landing site', 'latitude' => 10]);
    }

    public function test_csrf_protection_is_required_outside_test_bypass(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/admin/gis', $this->fields())->assertStatus(419);
        $this->putJson('/admin/gis/1', $this->fields(['revision' => $this->revision()]))->assertStatus(419);
        $this->app['env'] = 'testing';
    }

    public function test_saved_coordinates_match_the_geographic_point_after_create_and_edit(): void
    {
        // Use the verified trigger formula on isolated test records only.
        // Qualify PostGIS functions because the fixture excludes public tables from its search path.
        DB::unprepared(<<<'SQL'
            ALTER TABLE gis_locations ADD COLUMN geom public.geography(Point,4326);
            CREATE FUNCTION gis_test_geom() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                NEW.geom := public.ST_SetSRID(public.ST_MakePoint(NEW.longitude, NEW.latitude),4326)::public.geography;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER gis_test_geom BEFORE INSERT OR UPDATE OF latitude, longitude ON gis_locations FOR EACH ROW EXECUTE FUNCTION gis_test_geom();
        SQL);
        $id = $this->postJson('/admin/gis', $this->fields(['latitude' => 11.2745, 'longitude' => 124.0524]))->assertCreated()->json('id');
        $this->assertPoint($id, 11.2745, 124.0524);
        $this->putJson('/admin/gis/'.$id, $this->fields(['latitude' => -12.345, 'longitude' => 0, 'revision' => $this->revision($id)]))->assertOk();
        $this->assertPoint($id, -12.345, 0);
    }

    private function assertPoint(int $id, float $latitude, float $longitude): void
    {
        $point = DB::selectOne('SELECT public.ST_X(geom::public.geometry) AS longitude, public.ST_Y(geom::public.geometry) AS latitude, public.ST_SRID(geom::public.geometry) AS srid FROM gis_locations WHERE id = ?', [$id]);
        $this->assertEqualsWithDelta($latitude, (float) $point->latitude, 0.00000001);
        $this->assertEqualsWithDelta($longitude, (float) $point->longitude, 0.00000001);
        $this->assertSame(4326, $point->srid);
    }
}
