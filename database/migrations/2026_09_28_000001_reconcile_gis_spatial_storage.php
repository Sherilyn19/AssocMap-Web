<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('GIS storage requires PostgreSQL with PostGIS enabled.');
        }

        // Keep checks and changes together, including when this migration is called by a test.
        DB::transaction(function (): void {
            DB::statement("SET LOCAL lock_timeout = '5s'");
            DB::statement("SET LOCAL statement_timeout = '60s'");
            $extension = DB::selectOne("SELECT n.nspname AS schema FROM pg_extension e JOIN pg_namespace n ON n.oid = e.extnamespace WHERE e.extname = 'postgis'");
            if (! $extension) {
                throw new RuntimeException('Enable PostGIS in this database before running the GIS migration.');
            }
            $schema = DB::selectOne('SELECT current_schema() AS name')->name;
            $quote = fn (string $name): string => '"'.str_replace('"', '""', $name).'"';
            $table = $quote($schema).'."gis_locations"';
            $spatial = $quote($extension->schema);
            $function = $quote($schema).'."update_gis_geom"';
            // Prevent a save from changing a row between validation and constraint creation.
            DB::statement("LOCK TABLE $table IN ACCESS EXCLUSIVE MODE");
            $columns = collect(DB::select('SELECT a.attname AS name, t.typname AS type, format_type(a.atttypid,a.atttypmod) AS definition FROM pg_attribute a JOIN pg_type t ON t.oid=a.atttypid WHERE a.attrelid=CAST(? AS regclass) AND a.attnum>0 AND NOT a.attisdropped', [$table]))->keyBy('name');
            foreach (['latitude', 'longitude'] as $name) {
                if (($columns[$name]->type ?? null) !== 'numeric') {
                    throw new RuntimeException('GIS coordinates must use numeric columns. Review the existing schema first.');
                }
            }
            if (isset($columns['geom']) && ($columns['geom']->type !== 'geography'
                || ! preg_match('/(?:^|\.)geography\(point,4326\)$/i', $columns['geom']->definition))) {
                throw new RuntimeException('The existing GIS geometry type needs review. Expected geography(Point,4326).');
            }
            $invalid = DB::selectOne("SELECT count(*) AS total FROM $table WHERE latitude IS NULL OR longitude IS NULL OR NOT (latitude BETWEEN -90 AND 90) OR NOT (longitude BETWEEN -180 AND 180) OR association_id IS NULL OR is_published IS NULL OR created_at IS NULL OR updated_at IS NULL");
            if ((int) $invalid->total > 0) {
                throw new RuntimeException('GIS migration stopped: existing rows have invalid coordinates or missing required values. Review them first; no values were guessed.');
            }
            // Accept only the previously verified trigger. Do not overwrite unknown database logic.
            $triggers = DB::select('SELECT t.tgname, t.tgtype, t.tgenabled, t.tgattr::text AS columns, t.tgqual IS NULL AS unconditional, pg_get_triggerdef(t.oid) AS definition, p.prosrc FROM pg_trigger t JOIN pg_proc p ON p.oid=t.tgfoid WHERE t.tgrelid=CAST(? AS regclass) AND NOT t.tgisinternal', [$table]);
            $knownBody = 'beginnew.geom:=st_setsrid(st_makepoint(new.longitude,new.latitude),4326)::geography;returnnew;end;';
            $coordinateColumns = collect(DB::select("SELECT attnum FROM pg_attribute WHERE attrelid=CAST(? AS regclass) AND attname IN ('latitude','longitude') ORDER BY attnum", [$table]))
                ->pluck('attnum')->implode(' ');
            foreach ($triggers as $trigger) {
                $body = strtolower(preg_replace('/\s+/', '', $trigger->prosrc));
                if ($trigger->tgname !== 'trg_gis_geom' || (int) $trigger->tgtype !== 23 || $trigger->tgenabled !== 'O'
                    || ! in_array($trigger->columns, ['', $coordinateColumns], true) || ! $trigger->unconditional || $body !== $knownBody) {
                    throw new RuntimeException('An existing GIS trigger needs review before migration. It was not replaced.');
                }
            }
            if (! $triggers && DB::selectOne('SELECT to_regprocedure(?) AS name', [$function.'()'])->name !== null) {
                throw new RuntimeException('An existing update_gis_geom function needs review before migration.');
            }

            $point = "$spatial.ST_SetSRID($spatial.ST_MakePoint(longitude::double precision, latitude::double precision),4326)::$spatial.geography";
            if (isset($columns['geom']) && DB::selectOne("SELECT count(*) AS total FROM $table WHERE geom IS NOT NULL AND NOT $spatial.ST_Equals(geom::$spatial.geometry, ($point)::$spatial.geometry)")->total > 0) {
                throw new RuntimeException('Some GIS points disagree with their coordinates. Review them before migration.');
            }

            // Unrestricted numeric preserves existing precision and avoids silent rounding.
            // PostgreSQL requires removing column triggers during a type change. Restore the same
            // verified definition in this transaction; a failure restores the original trigger too.
            foreach ($triggers as $trigger) {
                if ($trigger->columns !== '') {
                    DB::statement("DROP TRIGGER trg_gis_geom ON $table");
                }
            }
            DB::statement("ALTER TABLE $table ALTER COLUMN latitude TYPE numeric, ALTER COLUMN longitude TYPE numeric");
            foreach ($triggers as $trigger) {
                if ($trigger->columns !== '') {
                    DB::statement($trigger->definition);
                }
            }
            if (! isset($columns['geom'])) {
                DB::statement("ALTER TABLE $table ADD COLUMN geom $spatial.geography(Point,4326)");
            }
            if (! $triggers) {
                DB::unprepared("CREATE FUNCTION $function() RETURNS trigger LANGUAGE plpgsql AS \$gis\$ BEGIN NEW.geom := $spatial.ST_SetSRID($spatial.ST_MakePoint(NEW.longitude, NEW.latitude),4326)::$spatial.geography; RETURN NEW; END; \$gis\$");
                DB::statement("CREATE TRIGGER trg_gis_geom BEFORE INSERT OR UPDATE ON $table FOR EACH ROW EXECUTE FUNCTION $function()");
            }
            // Only derived, missing points are filled. Names, coordinates, flags, and dates stay intact.
            DB::statement("UPDATE $table SET geom = $point WHERE geom IS NULL");
            DB::statement("ALTER TABLE $table
                ALTER COLUMN association_id SET NOT NULL,
                ALTER COLUMN latitude SET NOT NULL,
                ALTER COLUMN longitude SET NOT NULL,
                ALTER COLUMN geom SET NOT NULL,
                ALTER COLUMN is_published SET DEFAULT false,
                ALTER COLUMN is_published SET NOT NULL,
                ALTER COLUMN created_at SET DEFAULT now(), ALTER COLUMN created_at SET NOT NULL,
                ALTER COLUMN updated_at SET DEFAULT now(), ALTER COLUMN updated_at SET NOT NULL,
                ADD CONSTRAINT gis_storage_latitude_range CHECK (latitude BETWEEN -90 AND 90),
                ADD CONSTRAINT gis_storage_longitude_range CHECK (longitude BETWEEN -180 AND 180),
                ADD CONSTRAINT gis_storage_point_matches CHECK ($spatial.ST_Equals(geom::$spatial.geometry, ($point)::$spatial.geometry))");
            // Keep the existing parent key when present. Never add a unique association restriction.
            $parent = $quote($schema).'."associations"';
            $foreignKey = DB::selectOne("SELECT count(*) AS total FROM pg_constraint c WHERE c.conrelid=CAST(? AS regclass) AND c.confrelid=CAST(? AS regclass) AND c.contype='f' AND c.convalidated AND c.conkey=ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid=CAST(? AS regclass) AND attname='association_id')]::smallint[] AND c.confkey=ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid=CAST(? AS regclass) AND attname='id')]::smallint[]", [$table, $parent, $table, $parent]);
            if ((int) $foreignKey->total === 0) {
                DB::statement("ALTER TABLE $table ADD CONSTRAINT gis_storage_association_fk FOREIGN KEY (association_id) REFERENCES $parent(id)");
            }
        }, 1);
    }

    public function down(): void
    {
        // This migration may adopt existing spatial objects. Removing them could lose live data.
        throw new RuntimeException('GIS storage reconciliation is forward-only. Use a reviewed forward migration to change it.');
    }
};
