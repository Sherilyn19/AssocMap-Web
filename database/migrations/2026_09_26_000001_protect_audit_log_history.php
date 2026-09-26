<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL is the domain database. Reject changes even through raw or bulk SQL.
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION reject_audit_log_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Audit entries are append-only.' USING ERRCODE = '23514';
            END;
            $$;
            CREATE TRIGGER audit_logs_append_only
                BEFORE UPDATE OR DELETE OR TRUNCATE ON audit_logs
                FOR EACH STATEMENT EXECUTE FUNCTION reject_audit_log_mutation();
            CREATE INDEX audit_logs_time_id_index ON audit_logs (performed_at DESC, id DESC);
            CREATE INDEX audit_logs_module_time_index ON audit_logs (module, performed_at DESC, id DESC);
            CREATE INDEX audit_logs_action_time_index ON audit_logs (action_type, performed_at DESC, id DESC);
            SQL);
    }

    public function down(): void
    {
        // Rollback removes the protection and indexes; it never removes audit history.
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs;
            DROP FUNCTION IF EXISTS reject_audit_log_mutation();
            DROP INDEX IF EXISTS audit_logs_time_id_index;
            DROP INDEX IF EXISTS audit_logs_module_time_index;
            DROP INDEX IF EXISTS audit_logs_action_time_index;
            SQL);
    }
};
