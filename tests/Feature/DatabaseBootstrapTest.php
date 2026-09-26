<?php

namespace Tests\Feature;

use App\Services\BootstrapAdministratorService;
use Database\Seeders\AssocMapDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/** Run the actual migration chain in a random schema, never in public. */
class DatabaseBootstrapTest extends TestCase
{
    private bool $isolated = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('ASSOCMAP_BOOTSTRAP_TESTS') !== '1') {
            $this->markTestSkipped('Set ASSOCMAP_BOOTSTRAP_TESTS=1 to test isolated PostgreSQL installation.');
        }
        $schema = 'assocmap_test_bootstrap_'.bin2hex(random_bytes(8));
        config(['database.default' => 'pgsql', 'database.connections.pgsql.search_path' => $schema]);
        DB::purge('pgsql');
        DB::beginTransaction();
        $this->isolated = true;
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        DB::statement('SET LOCAL search_path TO "'.$schema.'"');
        $this->assertSame($schema, DB::selectOne('SELECT current_schema() AS schema')->schema);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->isolated) {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                DB::disconnect('pgsql');
            }
        } finally {
            parent::tearDown();
        }
    }

    private function migrate(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());
    }

    public function test_fresh_install_bootstrap_login_and_reseeding_preserve_account_security(): void
    {
        $this->migrate();
        $migrationCount = DB::table('migrations')->count();
        $this->migrate();
        $this->assertSame($migrationCount, DB::table('migrations')->count());
        $this->assertTrue(Schema::hasColumn('members', 'review_passphrase_hash'));
        $this->assertTrue(Schema::hasColumn('users', 'association_id'));
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('associations')->count());
        $password = 'Fresh-Install-Secret-2026';
        $this->artisan('assocmap:bootstrap-admin', ['--name' => 'Install Admin', '--email' => 'install@example.test'])
            ->expectsQuestion('Password (at least 12 characters)', $password)
            ->expectsQuestion('Confirm password', $password)->assertSuccessful();
        $admin = DB::table('users')->sole();
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'CREATE')->count());
        $this->post('/login', ['email' => $admin->email, 'password' => $password])->assertRedirect('/admin/dashboard');
        $this->get('/admin/users')->assertOk()->assertSee('install@example.test');
        $this->post('/logout');

        // Repeating standard seed must preserve changed passwords, roles, links and activation.
        DB::table('users')->where('id', $admin->id)->update(['is_active' => false]);
        $before = (array) DB::table('users')->sole();
        $lookupsBefore = DB::table('roles')->orderBy('id')->get()->toJson();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, (array) DB::table('users')->sole());
        $this->assertSame($lookupsBefore, DB::table('roles')->orderBy('id')->get()->toJson());
        try {
            app(BootstrapAdministratorService::class)->create(['name' => 'Another', 'email' => 'another@example.test', 'password' => $password, 'password_confirmation' => $password]);
            $this->fail('Even an inactive administrator must block another bootstrap.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('account', $error->errors());
        }
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_bootstrap_audit_failure_rolls_back_account_and_command_does_not_expose_sql(): void
    {
        $this->migrate();
        $this->seed(DatabaseSeeder::class);
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_bootstrap CHECK (action_type <> 'CREATE')");
        $this->artisan('assocmap:bootstrap-admin', ['--name' => 'Install Admin', '--email' => 'install@example.test'])
            ->expectsQuestion('Password (at least 12 characters)', 'Fresh-Install-Secret-2026')
            ->expectsQuestion('Confirm password', 'Fresh-Install-Secret-2026')
            ->expectsOutput('Administrator creation could not be confirmed. Check the database and existing accounts before retrying.')->assertFailed();
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
        $this->artisan('assocmap:bootstrap-admin', ['--no-interaction' => true])->assertFailed();
    }

    public function test_all_supported_accounts_can_be_added_and_log_in_on_the_real_schema(): void
    {
        $this->migrate();
        $this->seed(DatabaseSeeder::class);
        $password = 'Fresh-Account-Secret-2026';
        $admin = app(BootstrapAdministratorService::class)->create([
            'name' => 'Install Admin', 'email' => 'install@example.test',
            'password' => $password, 'password_confirmation' => $password,
        ]);
        // Minimal geography satisfies the real foreign keys rather than a simplified fixture.
        $area = DB::table('area_units')->insertGetId(['name' => 'Test Municipality']);
        $barangay = DB::table('sub_units')->insertGetId(['name' => 'Test Barangay', 'area_unit_id' => $area]);
        $association = DB::table('associations')->insertGetId([
            'name' => 'Long Association Name for Account Layout Verification', 'area_unit_id' => $area, 'sub_unit_id' => $barangay,
            'program_component_id' => DB::table('program_components')->value('id'),
            'address' => 'Test Barangay, Test Municipality', 'date_joined' => '2026-01-01',
        ]);
        $this->post('/login', ['email' => $admin->email, 'password' => $password])->assertRedirect('/admin/dashboard');
        $accounts = [];
        foreach (['System Administrator' => '/admin/dashboard', 'Field Officer' => '/officer/dashboard', 'Association Member' => '/member/dashboard'] as $role => $dashboard) {
            $id = DB::table('roles')->where('role_name', $role)->value('id');
            $email = 'created'.$id.'@example.test';
            $this->post('/admin/users', ['name' => 'Created '.$role, 'email' => $email, 'password' => $password,
                'role_id' => $id, 'association_id' => $association])->assertSessionHasNoErrors()->assertSessionHas('success');
            $user = DB::table('users')->where('email', $email)->first();
            $this->assertNotNull($user);
            $this->assertTrue(Hash::check($password, $user->password));
            $this->assertSame($role === 'Association Member' ? $association : null, $user->association_id);
            $accounts[$email] = $dashboard;
        }
        $this->assertSame(4, DB::table('users')->count());
        $this->assertSame(4, DB::table('audit_logs')->where('action_type', 'CREATE')->count());
        $this->get('/admin/users')->assertOk()->assertSee('data-user-table-scroll', false)->assertSee('created3@example.test');
        $this->post('/logout');
        foreach ($accounts as $email => $dashboard) {
            $this->post('/login', ['email' => $email, 'password' => $password])->assertRedirect($dashboard);
            $this->get($dashboard)->assertOk();
            $this->post('/logout');
        }
    }

    public function test_demo_requires_opt_in_and_empty_tables_and_does_not_reset_accounts(): void
    {
        $this->migrate();
        foreach (['testing', 'production'] as $environment) {
            $this->app->instance('env', $environment);
            config(['seeding.allow_demo' => $environment === 'production']);
            try {
                app(AssocMapDemoSeeder::class)->run();
                $this->fail('Demo seeding must reject a disabled flag or production environment.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('Demo seeding requires', $error->getMessage());
            }
            $this->assertSame(0, DB::table('users')->count());
        }
        $this->app->instance('env', 'testing');
        config(['seeding.allow_demo' => true]);
        app(AssocMapDemoSeeder::class)->run();
        $this->assertSame(8, DB::table('associations')->count());
        $this->assertSame(13, DB::table('users')->count());
        $shared = DB::table('users')->where('email', 'maya@assocmap.test')->first();
        $this->assertNotNull($shared->association_id);
        $this->post('/login', ['email' => $shared->email, 'password' => 'Demo@12345'])->assertRedirect('/member/dashboard');
        $this->get('/membership')->assertOk()->assertSee('Jose')->assertDontSee('Nestor');
        $this->post('/logout');
        DB::table('users')->where('id', $shared->id)->update(['password' => Hash::make('Changed-Private-Secret'), 'is_active' => false]);
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        try {
            app(AssocMapDemoSeeder::class)->run();
            $this->fail('Demo reruns must not reset records.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('empty account and domain tables', $error->getMessage());
        }
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
    }

    public function test_migration_rollback_and_rebuild_work_in_the_isolated_schema(): void
    {
        $this->migrate();
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--force' => true]), Artisan::output());
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('associations'));
        $this->migrate();
        $this->assertTrue(Schema::hasTable('audit_logs'));
    }

    public function test_baseline_refuses_existing_domain_tables_without_modification(): void
    {
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        DB::statement('CREATE TABLE roles (id bigint PRIMARY KEY, role_name text)');
        DB::table('roles')->insert(['id' => 17, 'role_name' => 'Existing role']);
        try {
            (require database_path('migrations/2026_01_01_000000_create_assocmap_domain_baseline.php'))->up();
            $this->fail('A legacy database must not be silently adopted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('reconciliation', $error->getMessage());
        }
        $this->assertSame('Existing role', DB::table('roles')->where('id', 17)->value('role_name'));
        $this->assertFalse(Schema::hasColumn('users', 'role_id'));
    }
}
