<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Fail clearly before applying NOT NULL when legacy data is incomplete.
        $invalidRows = DB::table('associations')
            ->whereNull('sub_unit_id')
            ->orWhereNull('program_component_id')
            ->orWhereNull('address')
            ->orWhereNull('date_joined')
            ->count();

        if ($invalidRows > 0) {
            throw new RuntimeException(
                "Association constraint migration stopped: {$invalidRows} record(s) contain required NULL values."
            );
        }

        DB::statement('ALTER TABLE associations ALTER COLUMN sub_unit_id SET NOT NULL');
        DB::statement('ALTER TABLE associations ALTER COLUMN program_component_id SET NOT NULL');
        DB::statement('ALTER TABLE associations ALTER COLUMN address SET NOT NULL');
        DB::statement('ALTER TABLE associations ALTER COLUMN date_joined SET NOT NULL');

        // A composite FK makes an invalid municipality/barangay pair impossible.
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'sub_units_id_area_unit_id_unique'
    ) THEN
        ALTER TABLE sub_units
            ADD CONSTRAINT sub_units_id_area_unit_id_unique
            UNIQUE (id, area_unit_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'fk_associations_sub_unit_area'
    ) THEN
        ALTER TABLE associations
            ADD CONSTRAINT fk_associations_sub_unit_area
            FOREIGN KEY (sub_unit_id, area_unit_id)
            REFERENCES sub_units (id, area_unit_id)
            ON UPDATE RESTRICT
            ON DELETE RESTRICT;
    END IF;
END
$$;
SQL);

        // Enforce duplicate checking after trimming, collapsing spaces, and lowercasing.
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS associations_normalized_name_area_unique
ON associations (
    area_unit_id,
    LOWER(REGEXP_REPLACE(BTRIM(name), '\s+', ' ', 'g'))
)
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS associations_normalized_name_area_unique');
        DB::statement('ALTER TABLE associations DROP CONSTRAINT IF EXISTS fk_associations_sub_unit_area');

        // Required columns intentionally remain NOT NULL during rollback because
        // loosening production data integrity is unsafe and not needed by the module.
    }
};