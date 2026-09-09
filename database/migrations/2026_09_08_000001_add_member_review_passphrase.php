<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Existing members remain intact; NULL means review is not yet provisioned. */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('review_passphrase_hash')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('review_passphrase_hash');
        });
    }
};
