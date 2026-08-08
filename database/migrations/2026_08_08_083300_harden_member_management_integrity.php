<?php

// database/migrations/2026_08_08_083300_harden_member_management_integrity.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * PostgreSQL DDL should roll back as one unit if a defensive precheck fails.
     */
    public $withinTransaction = true;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->assertNoNormalizedDuplicateMembers();
        $this->assertNoNormalizedDuplicateApplications();
        $this->assertOneMemberPerApplication();

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS members_normalized_identity_unique
            ON members (
                association_id,
                LOWER(REGEXP_REPLACE(BTRIM(first_name), '\s+', ' ', 'g')),
                COALESCE(
                    NULLIF(
                        LOWER(REGEXP_REPLACE(BTRIM(middle_name), '\s+', ' ', 'g')),
                        ''
                    ),
                    ''
                ),
                LOWER(REGEXP_REPLACE(BTRIM(last_name), '\s+', ' ', 'g')),
                birthday
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS member_applications_normalized_identity_unique
            ON member_applications (
                association_id,
                LOWER(REGEXP_REPLACE(BTRIM(first_name), '\s+', ' ', 'g')),
                COALESCE(
                    NULLIF(
                        LOWER(REGEXP_REPLACE(BTRIM(middle_name), '\s+', ' ', 'g')),
                        ''
                    ),
                    ''
                ),
                LOWER(REGEXP_REPLACE(BTRIM(last_name), '\s+', ' ', 'g')),
                birthday
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS members_application_id_unique
            ON members (application_id)
            WHERE application_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS members_application_id_unique');
        DB::statement('DROP INDEX IF EXISTS member_applications_normalized_identity_unique');
        DB::statement('DROP INDEX IF EXISTS members_normalized_identity_unique');
    }

    private function assertNoNormalizedDuplicateMembers(): void
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM members
                GROUP BY
                    association_id,
                    LOWER(REGEXP_REPLACE(BTRIM(first_name), '\s+', ' ', 'g')),
                    COALESCE(
                        NULLIF(
                            LOWER(REGEXP_REPLACE(BTRIM(middle_name), '\s+', ' ', 'g')),
                            ''
                        ),
                        ''
                    ),
                    LOWER(REGEXP_REPLACE(BTRIM(last_name), '\s+', ' ', 'g')),
                    birthday
                HAVING COUNT(*) > 1
            ) AS has_duplicates
        SQL);

        if ((bool) ($row->has_duplicates ?? false)) {
            throw new \RuntimeException(
                'Cannot harden member identity uniqueness because normalized duplicate members exist.'
            );
        }
    }

    private function assertNoNormalizedDuplicateApplications(): void
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM member_applications
                GROUP BY
                    association_id,
                    LOWER(REGEXP_REPLACE(BTRIM(first_name), '\s+', ' ', 'g')),
                    COALESCE(
                        NULLIF(
                            LOWER(REGEXP_REPLACE(BTRIM(middle_name), '\s+', ' ', 'g')),
                            ''
                        ),
                        ''
                    ),
                    LOWER(REGEXP_REPLACE(BTRIM(last_name), '\s+', ' ', 'g')),
                    birthday
                HAVING COUNT(*) > 1
            ) AS has_duplicates
        SQL);

        if ((bool) ($row->has_duplicates ?? false)) {
            throw new \RuntimeException(
                'Cannot harden application identity uniqueness because normalized duplicate applications exist.'
            );
        }
    }

    private function assertOneMemberPerApplication(): void
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM members
                WHERE application_id IS NOT NULL
                GROUP BY application_id
                HAVING COUNT(*) > 1
            ) AS has_duplicates
        SQL);

        if ((bool) ($row->has_duplicates ?? false)) {
            throw new \RuntimeException(
                'Cannot enforce one-member-per-application because duplicate application links exist.'
            );
        }
    }
};