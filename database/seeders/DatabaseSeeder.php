<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
* DatabaseSeeder
* database/seeders/DatabaseSeeder.php
*
* This is the main database seeder for the AssocMap system.
* It inserts the required lookup data, including roles, statuses,
* sex options, program components, and quarters.
*
* It also creates or updates the initial System Administrator,
* Field Officer, and Association Member accounts.
*
* After the required records are prepared, it runs the
* AssocMapDemoSeeder to insert the Filipino sample data used
* for development, testing, and system demonstration.
*
* The seeder uses updateOrInsert so it can be safely executed
* more than once without intentionally creating duplicate records.
*
  */


class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding AssocMap database...');

        $this->seedRoles();
        $this->seedStatuses();
        $this->seedSex();
        $this->seedProgramComponents();
        $this->seedQuarters();
        $this->seedInitialUsers();

        $this->call(AssocMapDemoSeeder::class);

        $this->command?->info('AssocMap seed complete.');
    }

    private function seedRoles(): void
    {
        foreach (['System Administrator', 'Field Officer', 'Association Member'] as $role) {
            DB::table('roles')->updateOrInsert(
                ['role_name' => $role],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedStatuses(): void
    {
        $statuses = [
            'Active', 'Inactive', 'Archived', 'Pending', 'Approved', 'Rejected',
            'Ongoing', 'Completed', 'Planned', 'Good', 'Damaged', 'For Repair',
            'Present', 'Absent',
        ];

        foreach ($statuses as $status) {
            DB::table('statuses')->updateOrInsert(
                ['status_name' => $status],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedSex(): void
    {
        foreach (['Male', 'Female'] as $sex) {
            DB::table('sex')->updateOrInsert(
                ['sex_name' => $sex],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedProgramComponents(): void
    {
        foreach (['Aquaculture', 'Capture Fisheries', 'Post-Harvest'] as $component) {
            DB::table('program_components')->updateOrInsert(
                ['name' => $component],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedQuarters(): void
    {
        foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $quarter) {
            DB::table('quarters')->updateOrInsert(
                ['quarter_name' => $quarter],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedInitialUsers(): void
    {
        $roles = DB::table('roles')->pluck('id', 'role_name');

        $users = [
            [
                'name' => 'Maria Lourdes Dela Cruz',
                'email' => 'admin@bfar.gov.ph',
                'password' => 'Admin@123',
                'role' => 'System Administrator',
            ],
            [
                'name' => 'Genevieve Flores',
                'email' => 'gennie@bfar.gov.ph',
                'password' => 'Field@123',
                'role' => 'Field Officer',
            ],
            [
                'name' => 'Rosario Manalo',
                'email' => 'rosa@assoc.gov.ph',
                'password' => 'Member@123',
                'role' => 'Association Member',
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                    'role_id' => $roles[$user['role']],
                    'association_id' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
