<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\AssociationDatabaseTestCase;

final class GisDatabaseAccessTest extends AssociationDatabaseTestCase
{
    public function test_api_access_is_removed_and_laravel_can_still_write(): void
    {
        $roles = DB::select("SELECT rolname FROM pg_roles WHERE rolname IN ('anon','authenticated')");
        foreach (['gis_locations', 'gis_submissions', 'audit_logs'] as $table) {
            DB::statement("GRANT ALL PRIVILEGES ON TABLE $table TO PUBLIC");
        }
        (require database_path('migrations/2026_09_28_000003_protect_gis_server_tables.php'))->up();
        foreach (['gis_locations', 'gis_submissions', 'audit_logs'] as $table) {
            $this->assertTrue(DB::selectOne('SELECT relrowsecurity AS enabled FROM pg_class WHERE oid=CAST(? AS regclass)', [$table])->enabled);
            foreach ($roles as $role) {
                foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE'] as $privilege) {
                    $this->assertFalse(DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$role->rolname, $table, $privilege])->allowed);
                }
            }
            $this->assertSame(0, (int) DB::selectOne('SELECT count(*) AS total FROM pg_class c CROSS JOIN LATERAL aclexplode(c.relacl) a WHERE c.oid=CAST(? AS regclass) AND a.grantee=0', [$table])->total);
        }
        DB::table('gis_locations')->where('id', 1)->update(['is_published' => false]);
        $this->assertFalse((bool) DB::table('gis_locations')->where('id', 1)->value('is_published'));
        DB::table('audit_logs')->insert(['user_id' => 1, 'action_type' => 'UNPUBLISH', 'module' => 'GIS', 'record_id' => 1, 'performed_at' => now()]);
        $this->assertSame(1, DB::table('audit_logs')->where('module', 'GIS')->count());
    }
}
