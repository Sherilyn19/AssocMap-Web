<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
// Unique indexes prevent duplicate names even when users save at the same time.
        // Stop this migration if duplicates exist so they can be reviewed first.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX area_units_normalized_name_unique
            ON area_units (LOWER(REGEXP_REPLACE(BTRIM(name), '\s+', ' ', 'g')))
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX sub_units_normalized_name_area_unique
            ON sub_units (area_unit_id, LOWER(REGEXP_REPLACE(BTRIM(name), '\s+', ' ', 'g')))
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sub_units_normalized_name_area_unique');
        DB::statement('DROP INDEX IF EXISTS area_units_normalized_name_unique');
    }
};
