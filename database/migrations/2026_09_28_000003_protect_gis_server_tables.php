<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement("SET LOCAL lock_timeout = '5s'");
            $schema = DB::selectOne('SELECT current_schema() AS name')->name;
            $quote = fn (string $name): string => '"'.str_replace('"', '""', $name).'"';
            $roles = DB::select("SELECT rolname FROM pg_roles WHERE rolname IN ('anon', 'authenticated')");
            foreach (['gis_locations', 'gis_submissions', 'audit_logs'] as $name) {
                $table = $quote($schema).'.'.$quote($name);
                $access = DB::selectOne('SELECT (c.relowner = r.oid OR r.rolsuper OR r.rolbypassrls) AS allowed FROM pg_class c CROSS JOIN pg_roles r WHERE c.oid=CAST(? AS regclass) AND r.rolname=current_user', [$table]);
                if (! $access?->allowed) {
                    throw new RuntimeException('The Laravel connection must own the GIS tables or use a trusted server role before enabling row security.');
                }
                // These tables belong to Laravel. Browser API roles must not bypass its checks.
                DB::statement("ALTER TABLE $table ENABLE ROW LEVEL SECURITY");
                DB::statement("REVOKE ALL PRIVILEGES ON TABLE $table FROM PUBLIC");
                foreach ($roles as $role) {
                    DB::statement("REVOKE ALL PRIVILEGES ON TABLE $table FROM ".$quote($role->rolname));
                }
            }
        });
    }

    public function down(): void
    {
        // Do not restore unsafe API access through an automatic rollback.
        throw new RuntimeException('Use a reviewed forward migration to change GIS table access.');
    }
};
