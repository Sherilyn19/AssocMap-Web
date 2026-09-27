<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep one receipt per administrator submission, without limiting legitimate sites.
        Schema::create('gis_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->uuid('token');
            $table->char('payload_hash', 64);
            $table->foreignId('location_id')->nullable()->constrained('gis_locations');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'token']);
        });
        // Receipts are internal. Supabase browser roles receive no row access.
        DB::statement('ALTER TABLE gis_submissions ENABLE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_submissions');
    }
};
