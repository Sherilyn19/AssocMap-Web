<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================
 * DatabaseSeeder
 * ============================================================
 * Seeds all lookup tables and the three initial user accounts.
 * Uses raw PDO via DB::statement and DB::select for full
 * control (consistent with the project's PDO requirement).
 *
 * Run with: php artisan db:seed
 *
 * Idempotent: uses INSERT ... ON CONFLICT DO NOTHING so it is
 * safe to run multiple times without duplicating data.
 * ============================================================
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding AssocMAP database...');

        $this->seedRoles();
        $this->seedStatuses();
        $this->seedSex();
        $this->seedProgramComponents();
        $this->seedQuarters();
        $this->seedUsers();

        $this->command->info('✅ AssocMAP seed complete.');
    }

    // ── 1. Roles ──────────────────────────────────────────────
    private function seedRoles(): void
    {
        $roles = [
            'System Administrator',
            'Field Officer',
            'Association Member',
        ];

        foreach ($roles as $role) {
            DB::statement("
                INSERT INTO roles (role_name, created_at, updated_at)
                VALUES (?, NOW(), NOW())
                ON CONFLICT (role_name) DO NOTHING
            ", [$role]);
        }

        $this->command->line('  → roles seeded');
    }

    // ── 2. Statuses ───────────────────────────────────────────
    private function seedStatuses(): void
    {
        $statuses = [
            'Active', 'Inactive', 'Archived',
            'Pending', 'Approved', 'Rejected',
            'Ongoing', 'Completed', 'Planned',
            'Good', 'Damaged', 'For Repair',
            'Present', 'Absent',
        ];

        foreach ($statuses as $status) {
            DB::statement("
                INSERT INTO statuses (status_name, created_at, updated_at)
                VALUES (?, NOW(), NOW())
                ON CONFLICT (status_name) DO NOTHING
            ", [$status]);
        }

        $this->command->line('  → statuses seeded');
    }

    // ── 3. Sex ────────────────────────────────────────────────
    private function seedSex(): void
    {
        foreach (['Male', 'Female'] as $sex) {
            DB::statement("
                INSERT INTO sex (sex_name, created_at, updated_at)
                VALUES (?, NOW(), NOW())
                ON CONFLICT (sex_name) DO NOTHING
            ", [$sex]);
        }

        $this->command->line('  → sex seeded');
    }

    // ── 4. Program Components ─────────────────────────────────
    private function seedProgramComponents(): void
    {
        $components = ['Aquaculture', 'Capture Fisheries', 'Post-Harvest'];

        foreach ($components as $component) {
            DB::statement("
                INSERT INTO program_components (name, created_at, updated_at)
                VALUES (?, NOW(), NOW())
                ON CONFLICT (name) DO NOTHING
            ", [$component]);
        }

        $this->command->line('  → program_components seeded');
    }

    // ── 5. Quarters ───────────────────────────────────────────
    private function seedQuarters(): void
    {
        foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $quarter) {
            DB::statement("
                INSERT INTO quarters (quarter_name, created_at, updated_at)
                VALUES (?, NOW(), NOW())
                ON CONFLICT (quarter_name) DO NOTHING
            ", [$quarter]);
        }

        $this->command->line('  → quarters seeded');
    }

    // ── 6. Users ──────────────────────────────────────────────
    // Three accounts with bcrypt-hashed passwords (cost 12).
    // password_hash() is called in PHP so the hash is valid and
    // fresh — never store plain-text passwords.
    private function seedUsers(): void
    {
        /*
         * Fetch role IDs using a JOIN so we reference by name,
         * not by a hardcoded integer that may differ per install.
         */
        $roles = DB::select("SELECT id, role_name FROM roles");
        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[$role->role_name] = $role->id;
        }

        $users = [
            [
                'name'      => 'System Administrator',
                'email'     => 'admin@bfar.gov.ph',
                'password'  => 'Admin@123',
                'role_name' => 'System Administrator',
            ],
            [
                'name'      => 'Gennie Field Officer',
                'email'     => 'gennie@bfar.gov.ph',
                'password'  => 'Field@123',
                'role_name' => 'Field Officer',
            ],
            [
                'name'      => 'Rosa Association Member',
                'email'     => 'rosa@assoc.gov.ph',
                'password'  => 'Member@123',
                'role_name' => 'Association Member',
            ],
        ];

        foreach ($users as $user) {
            // Hash password with PHP's bcrypt (cost 12)
            $hash    = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $roleId  = $roleMap[$user['role_name']] ?? null;

            if (! $roleId) {
                $this->command->warn("  ⚠ Role not found for: {$user['role_name']} — skipping");
                continue;
            }

            DB::statement("
                INSERT INTO users
                    (name, email, password, role_id, is_active, created_at, updated_at)
                VALUES
                    (?, ?, ?, ?, TRUE, NOW(), NOW())
                ON CONFLICT (email) DO UPDATE
                    SET
                        password   = EXCLUDED.password,
                        role_id    = EXCLUDED.role_id,
                        is_active  = TRUE,
                        updated_at = NOW()
            ", [
                $user['name'],
                $user['email'],
                $hash,
                $roleId,
            ]);

            $this->command->line("  → user seeded: {$user['email']} ({$user['role_name']})");
        }
    }
}
