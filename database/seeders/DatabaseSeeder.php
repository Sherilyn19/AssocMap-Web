<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PDOException;
use RuntimeException;

/** Standard seeding adds reference values only; it never creates or resets accounts. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        try {
            DB::transaction(function (): void {
                $lookups = [
                    'roles' => ['role_name', ['System Administrator', 'Field Officer', 'Association Member']],
                    'statuses' => ['status_name', ['Active', 'Inactive', 'Archived', 'Pending', 'Approved', 'Rejected', 'Ongoing', 'Completed', 'Planned', 'Good', 'Damaged', 'For Repair', 'Present', 'Absent']],
                    'sex' => ['sex_name', ['Male', 'Female']],
                    'program_components' => ['name', ['Aquaculture', 'Capture Fisheries', 'Post-Harvest']],
                    'quarters' => ['quarter_name', ['Q1', 'Q2', 'Q3', 'Q4']],
                ];
                foreach ($lookups as $table => [$column, $values]) {
                    foreach ($values as $value) {
                        // Preserve existing IDs and timestamps; reseeding is not a data reset.
                        if (! DB::table($table)->where($column, $value)->exists()) {
                            DB::table($table)->insertOrIgnore([$column => $value, 'created_at' => now(), 'updated_at' => now()]);
                        }
                    }
                }
            });
        } catch (PDOException $error) {
            // The transaction rolls back before safe feedback replaces database diagnostics.
            throw new RuntimeException('Lookup seeding failed. Check the database connection and migration status.');
        }
        $this->command?->info('Reference values are ready. Existing accounts were not changed.');
    }
}
