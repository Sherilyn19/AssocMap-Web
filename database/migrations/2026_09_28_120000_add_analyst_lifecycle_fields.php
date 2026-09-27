<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep existing dates and financial history. Unknown historical end dates
        // and stages stay null rather than inventing an event that was not recorded.
        Schema::table('trainings', function (Blueprint $table): void {
            $table->date('end_date')->nullable();
            $table->string('stage', 20)->nullable();
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->date('terminated_on')->nullable();
        });
        DB::statement("ALTER TABLE trainings ADD CONSTRAINT trainings_stage_check CHECK (stage IS NULL OR stage IN ('proposal', 'accepted', 'terminated'))");
        DB::statement('ALTER TABLE trainings ADD CONSTRAINT trainings_date_range_check CHECK (end_date IS NULL OR (date_conducted IS NOT NULL AND end_date >= date_conducted))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE trainings DROP CONSTRAINT trainings_date_range_check');
        DB::statement('ALTER TABLE trainings DROP CONSTRAINT trainings_stage_check');
        Schema::table('trainings', fn (Blueprint $table) => $table->dropColumn(['end_date', 'stage']));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('terminated_on'));
    }
};
