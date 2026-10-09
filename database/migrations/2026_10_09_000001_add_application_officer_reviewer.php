<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Keep the member reviewer column for historical representative decisions.
        Schema::table('member_applications', function (Blueprint $table): void {
            $table->foreignId('reviewed_by_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('member_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
        });
    }
};
