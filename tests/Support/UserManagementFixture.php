<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/** Rebuild only the account test tables, inside a separately named PostgreSQL schema. */
final class UserManagementFixture
{
    public const PASSWORD = 'Synthetic-Test-Password-2026';

    public static function connect(string $schema): void
    {
        self::validate($schema);
        config(['database.default' => 'pgsql', 'database.connections.pgsql.search_path' => $schema]);
        DB::purge('pgsql');
    }

    public static function validate(string $schema): void
    {
        if (! preg_match('/^assocmap_test_users_[a-f0-9]{16}$/D', $schema)) {
            throw new InvalidArgumentException('A randomly named user-management test schema is required.');
        }
    }

    public static function create(string $schema): void
    {
        self::validate($schema);
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        DB::statement('SET search_path TO "'.$schema.'"');
        DB::unprepared(<<<'SQL'
            CREATE TABLE roles (id bigserial PRIMARY KEY, role_name varchar NOT NULL UNIQUE);
            CREATE TABLE associations (id bigserial PRIMARY KEY, name varchar NOT NULL, field_officer_id bigint, is_archived boolean NOT NULL DEFAULT false);
            CREATE TABLE users (id bigserial PRIMARY KEY, name varchar NOT NULL, email varchar NOT NULL UNIQUE, password varchar NOT NULL, role_id bigint NOT NULL REFERENCES roles(id), association_id bigint REFERENCES associations(id), is_active boolean NOT NULL DEFAULT true, created_at timestamp, updated_at timestamp);
            ALTER TABLE associations ADD FOREIGN KEY (field_officer_id) REFERENCES users(id);
            ALTER TABLE users ADD CONSTRAINT uq_users_association UNIQUE (association_id);
            CREATE TABLE audit_logs (id bigserial PRIMARY KEY, user_id bigint REFERENCES users(id), action_type varchar NOT NULL, module varchar NOT NULL, record_id bigint, details text, performed_at timestamp NOT NULL);
            CREATE TABLE sessions (id varchar PRIMARY KEY, user_id bigint, ip_address varchar(45), user_agent text, payload text NOT NULL, last_activity integer NOT NULL);
            CREATE INDEX sessions_last_activity_index ON sessions(last_activity);
            CREATE TABLE cache (key varchar PRIMARY KEY, value text NOT NULL, expiration integer NOT NULL);
            CREATE TABLE cache_locks (key varchar PRIMARY KEY, owner varchar NOT NULL, expiration integer NOT NULL);
            INSERT INTO roles (role_name) VALUES ('System Administrator'), ('Field Officer'), ('Association Member');
            INSERT INTO associations (name) VALUES ('Synthetic Association');
        SQL);
        $password = Hash::make(self::PASSWORD);
        foreach ([['Admin One', 'admin1@example.test', 1, null], ['Admin Two', 'admin2@example.test', 1, null], ['Officer', 'officer@example.test', 2, null], ['Association', 'association@example.test', 3, 1]] as [$name, $email, $role, $association]) {
            DB::table('users')->insert(['name' => $name, 'email' => $email, 'password' => $password, 'role_id' => $role, 'association_id' => $association, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 3]);
    }

    public static function drop(string $schema): void
    {
        self::validate($schema);
        // Cleanup accepts only the exact test namespace, never public or a supplied application table.
        DB::statement('DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE');
    }
}
