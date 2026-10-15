<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associations', function (Blueprint $table) {
            // Historical terminations remain unknown; do not invent assessment evidence.
            $table->date('assistance_assessed_on')->nullable();
            $table->text('assistance_termination_reason')->nullable();
            $table->timestampTz('assistance_terminated_at')->nullable();
            $table->foreignId('assistance_terminated_by')->nullable()->constrained('users');
        });
        DB::unprepared(<<<'SQL'
-- Follow only business-record parents. Accounts, sessions, notifications and audit
-- entries remain usable; they are not editable association programme records.
CREATE FUNCTION am_assistance_owners(s text, t text, r jsonb)
RETURNS bigint[] LANGUAGE plpgsql VOLATILE AS $$
DECLARE ids bigint[] := ARRAY[]::bigint[]; spec text[]; parent jsonb;
BEGIN
    IF r IS NULL THEN RETURN ids; END IF;
    IF t = 'associations' THEN RETURN ARRAY[(r->>'id')::bigint]; END IF;
    IF r->>'association_id' IS NOT NULL THEN
        ids := array_append(ids, (r->>'association_id')::bigint);
    END IF;
    FOREACH spec SLICE 1 IN ARRAY ARRAY[
        ['project_id','projects'], ['training_id','trainings'],
        ['member_id','members'], ['application_id','member_applications'], ['project_material_id','project_materials'],
        ['monitoring_production_id','monitoring_production'], ['location_id','gis_locations']
    ] LOOP
        IF r->>spec[1] IS NOT NULL THEN
            EXECUTE format('SELECT to_jsonb(p) FROM %I.%I p WHERE id = $1 FOR SHARE', s, spec[2])
                INTO parent USING (r->>spec[1])::bigint;
            ids := ids || am_assistance_owners(s, spec[2], parent);
        END IF;
    END LOOP;
    RETURN ids;
END;
$$;

CREATE FUNCTION am_assistance_pending(a bigint)
RETURNS TABLE(applications bigint, drafts bigint, attendance bigint)
LANGUAGE sql VOLATILE AS $$
    SELECT
        (SELECT count(*) FROM member_applications m JOIN statuses s ON s.id=m.status_id
         WHERE m.association_id=a AND s.status_name='Pending'),
        (SELECT count(*) FROM member_drafts d WHERE d.association_id=a AND d.state='draft'),
        (SELECT count(*) FROM training_participants p JOIN trainings t ON t.id=p.training_id
         JOIN statuses s ON s.id=p.attendance_status_id
         WHERE t.association_id=a AND NOT t.is_archived AND s.status_name='Pending');
$$;

CREATE FUNCTION am_assistance_write_guard()
RETURNS trigger LANGUAGE plpgsql VOLATILE AS $$
DECLARE ids bigint[] := ARRAY[]::bigint[]; a bigint; state text; outstanding record;
BEGIN
    IF TG_TABLE_NAME = 'associations' THEN
        IF TG_OP <> 'INSERT' AND OLD.assistance_status = 'terminated' THEN
            RAISE EXCEPTION USING ERRCODE='P7501', MESSAGE='Association records are read-only after termination.';
        END IF;
        IF TG_OP <> 'DELETE' AND NEW.assistance_status = 'terminated' THEN
            -- Only an existing association may pass through the audited termination flow.
            IF TG_OP = 'INSERT' OR NEW.assistance_assessed_on IS NULL
                OR NEW.assistance_assessed_on > (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date
                OR NULLIF(btrim(NEW.assistance_termination_reason), '') IS NULL
                OR NEW.assistance_terminated_at IS NULL OR NEW.assistance_terminated_by IS NULL THEN
                RAISE EXCEPTION USING ERRCODE='P7503', MESSAGE='Termination assessment is incomplete.';
            END IF;
            SELECT * INTO outstanding FROM am_assistance_pending(NEW.id);
            IF outstanding.applications + outstanding.drafts + outstanding.attendance > 0 THEN
                RAISE EXCEPTION USING ERRCODE='P7502', MESSAGE='Resolve pending work before termination.';
            END IF;
        END IF;
    ELSE
        IF TG_OP <> 'INSERT' THEN ids := ids || am_assistance_owners(TG_TABLE_SCHEMA, TG_TABLE_NAME, to_jsonb(OLD)); END IF;
        IF TG_OP <> 'DELETE' THEN ids := ids || am_assistance_owners(TG_TABLE_SCHEMA, TG_TABLE_NAME, to_jsonb(NEW)); END IF;
        -- Lock both previous and new owners, including indirect parent records.
        -- These locks serialize writes with termination and prevent transfer bypasses.
        FOR a IN SELECT DISTINCT unnest(ids) ORDER BY 1 LOOP
            SELECT assistance_status INTO state FROM associations WHERE id=a FOR SHARE;
            IF state = 'terminated' THEN
                RAISE EXCEPTION USING ERRCODE='P7501', MESSAGE='Association records are read-only after termination.';
            END IF;
        END LOOP;
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END;
$$;
SQL);
        // Keep helper functions private; they are not Supabase browser RPC endpoints.
        DB::statement('REVOKE ALL ON FUNCTION am_assistance_owners(text,text,jsonb) FROM PUBLIC');
        DB::statement('REVOKE ALL ON FUNCTION am_assistance_pending(bigint) FROM PUBLIC');
        DB::statement('REVOKE ALL ON FUNCTION am_assistance_write_guard() FROM PUBLIC');
        // Install on current domain tables. New domain tables must be added in their migration.
        $tables = ['associations', 'member_applications', 'member_drafts', 'members',
            'projects', 'project_materials', 'trainings', 'training_participants',
            'monitoring_production', 'monitoring_income', 'monitoring_materials',
            'production_progress_entries', 'gis_locations', 'gis_submissions'];
        foreach ($tables as $table) {
            DB::statement('CREATE TRIGGER am_assistance_read_only BEFORE INSERT OR UPDATE OR DELETE ON "'.$table.'" FOR EACH ROW EXECUTE FUNCTION am_assistance_write_guard()');
        }
    }

    public function down(): void
    {
        foreach (['associations', 'member_applications', 'member_drafts', 'members',
            'projects', 'project_materials', 'trainings', 'training_participants',
            'monitoring_production', 'monitoring_income', 'monitoring_materials',
            'production_progress_entries', 'gis_locations', 'gis_submissions'] as $table) {
            DB::statement('DROP TRIGGER IF EXISTS am_assistance_read_only ON "'.$table.'"');
        }
        DB::statement('DROP FUNCTION am_assistance_write_guard()');
        DB::statement('DROP FUNCTION am_assistance_pending(bigint)');
        DB::statement('DROP FUNCTION am_assistance_owners(text,text,jsonb)');
        Schema::table('associations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assistance_terminated_by');
            $table->dropColumn(['assistance_assessed_on', 'assistance_termination_reason', 'assistance_terminated_at']);
        });
    }
};
