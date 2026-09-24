<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Defense: database uniqueness closes the race between validation and INSERT.
        // Existing conflicting names deliberately stop deployment; never merge history silently.
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
