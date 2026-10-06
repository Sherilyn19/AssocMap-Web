<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // The first confirmed production entry establishes the project's unit.
            $table->string('production_unit_code', 20)->nullable();
            $table->string('production_unit_spec', 100)->nullable();
        });

        Schema::table('monitoring_production', function (Blueprint $table): void {
            // Keep a unit snapshot for each confirmed entry.
            // Existing entries remain unknown until individually reviewed.
            $table->string('output_unit_code', 20)->nullable();
            $table->string('output_unit_spec', 100)->nullable();
        });

        /*
         * Separate material events require multiple rows per material.
         * Remove only uniqueness that applies to project_material_id alone.
         * Keep the foreign key, primary key, and any composite constraints.
         */
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                material_attribute smallint;
                item record;
            BEGIN
                SELECT attnum INTO material_attribute
                FROM pg_attribute
                WHERE attrelid = 'monitoring_materials'::regclass
                  AND attname = 'project_material_id';

                FOR item IN
                    SELECT conname
                    FROM pg_constraint
                    WHERE conrelid = 'monitoring_materials'::regclass
                      AND contype = 'u'
                      AND conkey = ARRAY[material_attribute]::smallint[]
                LOOP
                    EXECUTE format(
                        'ALTER TABLE monitoring_materials DROP CONSTRAINT %I',
                        item.conname
                    );
                END LOOP;

                FOR item IN
                    SELECT ns.nspname, idx.relname
                    FROM pg_index pi
                    JOIN pg_class idx ON idx.oid = pi.indexrelid
                    JOIN pg_namespace ns ON ns.oid = idx.relnamespace
                    WHERE pi.indrelid = 'monitoring_materials'::regclass
                      AND pi.indisunique
                      AND NOT pi.indisprimary
                      AND pi.indnkeyatts = 1
                      AND pi.indkey[0] = material_attribute
                      AND NOT EXISTS (
                          SELECT 1 FROM pg_constraint c
                          WHERE c.conindid = pi.indexrelid
                      )
                LOOP
                    EXECUTE format('DROP INDEX %I.%I', item.nspname, item.relname);
                END LOOP;
            END $$;
        SQL);

        Schema::table('monitoring_materials', function (Blueprint $table): void {
            // Never invent observation dates for existing records.
            $table->date('observed_on')->nullable()->index();

            // Prevent an accidental repeat submission of the same new form.
            // Separate events on the same date remain allowed.
            $table->uuid('submission_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_materials', function (Blueprint $table): void {
            $table->dropUnique(['submission_token']);
            $table->dropIndex(['observed_on']);
            $table->dropColumn(['observed_on', 'submission_token']);
        });

        Schema::table('monitoring_production', function (Blueprint $table): void {
            $table->dropColumn(['output_unit_code', 'output_unit_spec']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['production_unit_code', 'production_unit_spec']);
        });

        // Do not restore one-row-per-material uniqueness:
        // valid dated history may now contain multiple records.
    }
};