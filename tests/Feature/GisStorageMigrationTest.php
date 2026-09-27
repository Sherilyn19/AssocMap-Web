<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class GisStorageMigrationTest extends TestCase
{
    private bool $started = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('ASSOCMAP_GIS_STORAGE_TESTS') !== '1') {
            $this->markTestSkipped('Set ASSOCMAP_GIS_STORAGE_TESTS=1 to run isolated GIS migration tests.');
        }
        // Use a random schema and roll back everything. Never migrate the application's tables.
        $schema = 'assocmap_test_gis_storage_'.bin2hex(random_bytes(8));
        config(['database.default' => 'pgsql', 'database.connections.pgsql.search_path' => $schema.',public']);
        DB::purge('pgsql');
        DB::beginTransaction();
        $this->started = true;
        DB::statement("SET LOCAL statement_timeout = '15s'");
        DB::statement("SET LOCAL lock_timeout = '5s'");
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        DB::statement('SET LOCAL search_path TO "'.$schema.'", public');
        // Remove public from table lookup while the real baseline checks for existing tables.
        DB::statement('SET LOCAL search_path TO "'.$schema.'"');
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_01_01_000000_create_assocmap_domain_baseline.php'))->up();
        DB::statement('SET LOCAL search_path TO "'.$schema.'", public');
        DB::table('area_units')->insert(['name' => 'Test area']);
        DB::table('associations')->insert(['name' => 'Test association', 'area_unit_id' => 1]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->started) {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                DB::disconnect('pgsql');
            }
        } finally {
            parent::tearDown();
        }
    }

    private function migrateStorage(): void
    {
        (require database_path('migrations/2026_09_28_000001_reconcile_gis_spatial_storage.php'))->up();
    }

    private function record(array $values = []): array
    {
        return array_replace(['association_id' => 1, 'location_name' => 'Site', 'latitude' => 10, 'longitude' => 123,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'], $values);
    }

    public function test_real_baseline_receives_spatial_storage_and_database_protection(): void
    {
        $this->migrateStorage();
        $id = DB::table('gis_locations')->insertGetId($this->record());
        $this->assertPoint($id, 10, 123);
        $this->assertFalse((bool) DB::table('gis_locations')->where('id', $id)->value('is_published'));
        DB::table('gis_locations')->where('id', $id)->update(['latitude' => 0, 'longitude' => -180]);
        $this->assertPoint($id, 0, -180);
        DB::table('gis_locations')->insert($this->record());
        $this->assertSame(2, DB::table('gis_locations')->count());
        foreach ([['latitude' => null], ['latitude' => 91], ['longitude' => 181], ['latitude' => 'NaN'], ['longitude' => 'Infinity'], ['association_id' => 99999]] as $invalid) {
            try {
                DB::transaction(fn () => DB::table('gis_locations')->insert($this->record($invalid)));
                $this->fail('Invalid data must be rejected by the database.');
            } catch (QueryException $error) {
                $this->assertContains($error->getCode(), ['23502', '23514', '23503']);
            }
        }
        $this->assertSame(2, DB::table('gis_locations')->count());
    }

    public function test_upgrade_keeps_saved_values_and_fills_missing_geometry(): void
    {
        DB::statement('ALTER TABLE gis_locations ALTER COLUMN latitude TYPE numeric, ALTER COLUMN longitude TYPE numeric');
        $id = DB::table('gis_locations')->insertGetId($this->record(['latitude' => '10.123456789123', 'is_published' => true]));
        $before = (array) DB::table('gis_locations')->where('id', $id)->first();
        $this->migrateStorage();
        $after = (array) DB::table('gis_locations')->where('id', $id)->first();
        unset($after['geom']);
        $this->assertEquals($before, $after);
        $this->assertPoint($id, 10.123456789123, 123);
    }

    public function test_known_existing_trigger_and_point_are_preserved(): void
    {
        DB::unprepared('ALTER TABLE gis_locations ADD COLUMN geom public.geography(Point,4326);
            CREATE FUNCTION update_gis_geom() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN NEW.geom := ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude),4326)::GEOGRAPHY; RETURN NEW; END;
            $$;
            CREATE TRIGGER trg_gis_geom BEFORE INSERT OR UPDATE ON gis_locations FOR EACH ROW EXECUTE FUNCTION update_gis_geom();');
        $id = DB::table('gis_locations')->insertGetId($this->record(['is_published' => true]));
        $before = DB::table('gis_locations')->where('id', $id)->first();
        $this->migrateStorage();
        $this->assertEquals($before, DB::table('gis_locations')->where('id', $id)->first());
        DB::table('gis_locations')->where('id', $id)->update(['longitude' => 0]);
        $this->assertPoint($id, 10, 0);
    }

    public function test_invalid_legacy_rows_stop_migration_without_partial_changes(): void
    {
        DB::table('gis_locations')->insert($this->record(['latitude' => null]));
        try {
            $this->migrateStorage();
            $this->fail('Invalid existing records must require review.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('existing rows', $error->getMessage());
        }
        $this->assertNull(DB::table('gis_locations')->value('latitude'));
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) AS total FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='gis_locations' AND column_name='geom'")->total);
    }

    public function test_known_coordinate_only_trigger_keeps_its_definition_and_behavior(): void
    {
        DB::unprepared('ALTER TABLE gis_locations ADD COLUMN geom public.geography(Point,4326);
            CREATE FUNCTION update_gis_geom() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN NEW.geom := ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude),4326)::GEOGRAPHY; RETURN NEW; END;
            $$;
            CREATE TRIGGER trg_gis_geom BEFORE INSERT OR UPDATE OF latitude, longitude ON gis_locations FOR EACH ROW EXECUTE FUNCTION update_gis_geom();');
        $definition = DB::selectOne("SELECT pg_get_triggerdef(oid) AS value FROM pg_trigger WHERE tgrelid='gis_locations'::regclass AND tgname='trg_gis_geom'")->value;
        $id = DB::table('gis_locations')->insertGetId($this->record());
        $this->migrateStorage();
        $this->assertSame($definition, DB::selectOne("SELECT pg_get_triggerdef(oid) AS value FROM pg_trigger WHERE tgrelid='gis_locations'::regclass AND tgname='trg_gis_geom'")->value);
        DB::table('gis_locations')->where('id', $id)->update(['longitude' => 0]);
        $this->assertPoint($id, 10, 0);
    }

    public function test_unknown_existing_geometry_type_is_not_replaced(): void
    {
        DB::statement('ALTER TABLE gis_locations ADD COLUMN geom public.geometry(Point,4326)');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('geometry type needs review');
        $this->migrateStorage();
    }

    public function test_unknown_trigger_is_preserved_and_requires_review(): void
    {
        DB::unprepared('CREATE FUNCTION custom_gis_hook() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RETURN NEW; END; $$;
            CREATE TRIGGER custom_gis_hook BEFORE INSERT ON gis_locations FOR EACH ROW EXECUTE FUNCTION custom_gis_hook();');
        try {
            $this->migrateStorage();
            $this->fail('Unknown triggers must require review.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('trigger needs review', $error->getMessage());
        }
        $this->assertSame(1, (int) DB::selectOne("SELECT count(*) AS total FROM pg_trigger WHERE tgrelid='gis_locations'::regclass AND tgname='custom_gis_hook'")->total);
    }

    public function test_existing_point_that_disagrees_with_coordinates_is_not_overwritten(): void
    {
        DB::statement('ALTER TABLE gis_locations ADD COLUMN geom public.geography(Point,4326)');
        $id = DB::table('gis_locations')->insertGetId($this->record());
        DB::statement('UPDATE gis_locations SET geom=ST_SetSRID(ST_MakePoint(1,2),4326)::geography WHERE id=?', [$id]);
        try {
            $this->migrateStorage();
            $this->fail('A conflicting point must require review.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('points disagree', $error->getMessage());
        }
        $this->assertPoint($id, 2, 1);
    }

    private function assertPoint(int $id, float $latitude, float $longitude): void
    {
        $point = DB::selectOne('SELECT ST_X(geom::geometry) AS longitude, ST_Y(geom::geometry) AS latitude, ST_SRID(geom::geometry) AS srid FROM gis_locations WHERE id=?', [$id]);
        $this->assertEqualsWithDelta($latitude, (float) $point->latitude, 0.000000000001);
        $this->assertEqualsWithDelta($longitude, (float) $point->longitude, 0.000000000001);
        $this->assertSame(4326, $point->srid);
    }
}
