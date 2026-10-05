<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_applications', function (Blueprint $table): void {
            // Historical applications remain unknown rather than receiving invented attribution.
            $table->foreignId('submitted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('submission_source', 40)->nullable();
        });

        Schema::create('member_drafts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('association_id')
                ->constrained('associations')
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // A draft may be incomplete. Full member validation happens on submission.
            $table->json('profile');

            $table->enum('state', ['draft', 'submitted', 'cancelled'])
                ->default('draft');

            // Prevent one application from being linked to multiple drafts.
            $table->foreignId('application_id')
                ->nullable()
                ->unique()
                ->constrained('member_applications')
                ->restrictOnDelete();

            // Prevent an older browser tab from overwriting a newer saved version.
            $table->unsignedInteger('revision')->default(1);

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(
                ['created_by_user_id', 'association_id', 'state'],
                'member_drafts_owner_scope_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_drafts');

        Schema::table('member_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropColumn('submission_source');
        });
    }
};