<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_materials', function (Blueprint $table): void {
            // A timestamp preserves the material and records when it was archived.
            // Existing materials remain current because this value defaults to null.
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('project_materials', function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};