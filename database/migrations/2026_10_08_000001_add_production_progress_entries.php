<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_production', function (Blueprint $table): void {
            // Existing records remain historical summaries.
            $table->string('tracking_mode', 20)->default('summary');

            // Reject saves made from an outdated open form.
            $table->unsignedInteger('revision')->default(1);

            // Prevent accidental repeat creation of the same quarter.
            $table->uuid('submission_token')->nullable()->unique();
        });

        Schema::create('production_progress_entries', function (Blueprint $table): void {
            $table->id();

            // Preserve entries when their parent is archived.
            $table->foreignId('monitoring_production_id')
                ->constrained('monitoring_production')
                ->restrictOnDelete();

            $table->date('produced_on');
            $table->decimal('quantity', 14, 2);
            $table->string('description', 255);
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Voiding removes output from the total without deleting history.
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(
                ['monitoring_production_id', 'produced_on'],
                'production_entries_period_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_progress_entries');

        Schema::table('monitoring_production', function (Blueprint $table): void {
            $table->dropUnique(['submission_token']);
            $table->dropColumn([
                'tracking_mode',
                'revision',
                'submission_token',
            ]);
        });
    }
};