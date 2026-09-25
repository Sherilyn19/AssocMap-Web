<?php

namespace Tests\Support;

use App\Support\SessionCredentials;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class UserManagementDatabaseTestCase extends TestCase
{
    protected string $schema;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('ASSOCMAP_USER_TESTS') !== '1') {
            $this->markTestSkipped('Set ASSOCMAP_USER_TESTS=1 to run isolated PostgreSQL account tests.');
        }
        $this->schema = 'assocmap_test_users_'.bin2hex(random_bytes(8));
        UserManagementFixture::connect($this->schema);
        DB::beginTransaction();
        DB::statement("SET LOCAL statement_timeout = '15000ms'");
        UserManagementFixture::create($this->schema);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->app && isset($this->schema)) {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                DB::disconnect('pgsql');
            }
        } finally {
            parent::tearDown();
        }
    }

    protected function sessionFor(int $id): array
    {
        $user = DB::table('users')->where('id', $id)->first();

        return ['auth_user' => ['id' => $id, 'credential_fingerprint' => SessionCredentials::fingerprint($id, $user->password)]];
    }

    protected function payload(int $id, array $extra = []): array
    {
        $user = DB::table('users')->where('id', $id)->first();

        return array_replace(['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id], $extra);
    }
}
