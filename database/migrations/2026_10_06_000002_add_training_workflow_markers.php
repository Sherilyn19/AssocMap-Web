<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table): void {
            // Records when external approval was confirmed in AssocMap.
            // This is not the date of the external approval decision.
            $table->timestamp('external_approval_recorded_at')->nullable();

            // Once registration starts, removing participants must not unlock dates.
            $table->timestamp('schedule_locked_at')->nullable();
        });

        // Preserve schedule history for existing registrations, including registrations
        // recorded in the audit trail whose participant was subsequently removed.
        DB::table('trainings')
            ->where(function ($query): void {
                $query->whereExists(function ($participants): void {
                    $participants->selectRaw('1')
                        ->from('training_participants')
                        ->whereColumn('training_participants.training_id', 'trainings.id');
                })->orWhereExists(function ($audit): void {
                    $audit->selectRaw('1')
                        ->from('audit_logs')
                        ->whereColumn('audit_logs.record_id', 'trainings.id')
                        ->where('module', 'Training Management')
                        ->where('action_type', 'CREATE')
                        ->where('details', 'like', 'Registered participant member #%');
                });
            })
            ->update(['schedule_locked_at' => now()]);

        // Existing records deliberately retain a null approval marker.
        // Their external approval must not be invented during migration.
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table): void {
            $table->dropColumn([
                'external_approval_recorded_at',
                'schedule_locked_at',
            ]);
        });
    }
};