<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Resolve tables through the configured schema, including isolated installation tests.
        // Hard-coding public here would modify another schema during a migration check.
        $nullBarangays = (int) DB::table('associations')
            ->whereNull('sub_unit_id')
            ->count();

        if ($nullBarangays > 0) {
            throw new RuntimeException(
                "Migration stopped: {$nullBarangays} association record(s) have no barangay."
            );
        }

        $mismatches = (int) DB::table('associations as a')
            ->join('sub_units as su', 'su.id', '=', 'a.sub_unit_id')
            ->whereColumn('a.area_unit_id', '<>', 'su.area_unit_id')
            ->count();

        if ($mismatches > 0) {
            throw new RuntimeException(
                "Migration stopped: {$mismatches} association record(s) use a barangay from another municipality."
            );
        }

        if (! $this->constraintExists('sub_units', 'uq_sub_units_id_area_unit')) {
            DB::statement('
                ALTER TABLE sub_units
                ADD CONSTRAINT uq_sub_units_id_area_unit
                UNIQUE (id, area_unit_id)
            ');
        }

        if (! $this->constraintExists('associations', 'fk_associations_sub_unit_area')) {
            DB::statement('
                ALTER TABLE associations
                ADD CONSTRAINT fk_associations_sub_unit_area
                FOREIGN KEY (sub_unit_id, area_unit_id)
                REFERENCES sub_units (id, area_unit_id)
                ON UPDATE RESTRICT
                ON DELETE RESTRICT
            ');
        }

        DB::statement('
            ALTER TABLE associations
            ALTER COLUMN sub_unit_id SET NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE associations
            ALTER COLUMN sub_unit_id DROP NOT NULL
        ');

        if ($this->constraintExists('associations', 'fk_associations_sub_unit_area')) {
            DB::statement('
                ALTER TABLE associations
                DROP CONSTRAINT fk_associations_sub_unit_area
            ');
        }

        if ($this->constraintExists('sub_units', 'uq_sub_units_id_area_unit')) {
            DB::statement('
                ALTER TABLE sub_units
                DROP CONSTRAINT uq_sub_units_id_area_unit
            ');
        }
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return DB::table('pg_constraint as c')
            ->join('pg_class as t', 't.oid', '=', 'c.conrelid')
            ->join('pg_namespace as n', 'n.oid', '=', 't.relnamespace')
            ->whereRaw('n.nspname = current_schema()')
            ->where('t.relname', $table)
            ->where('c.conname', $constraint)
            ->exists();
    }
};
