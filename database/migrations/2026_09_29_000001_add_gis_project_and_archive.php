<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->unique(['id', 'association_id'], 'gis_project_association_unique'));
        Schema::table('gis_locations', function (Blueprint $table): void {
            // Existing locations remain active and unlinked; no coordinates or history are changed.
            $table->unsignedBigInteger('project_id')->nullable();
            $table->timestampTz('archived_at')->nullable()->index();
            $table->foreign(['project_id', 'association_id'], 'gis_project_same_association_fk')
                ->references(['id', 'association_id'])->on('projects')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE gis_locations ADD CONSTRAINT gis_archive_unpublished CHECK (archived_at IS NULL OR is_published = false)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE gis_locations DROP CONSTRAINT gis_archive_unpublished');
        Schema::table('gis_locations', function (Blueprint $table): void {
            $table->dropForeign('gis_project_same_association_fk');
            $table->dropColumn(['project_id', 'archived_at']);
        });
        Schema::table('projects', fn (Blueprint $table) => $table->dropUnique('gis_project_association_unique'));
    }
};
