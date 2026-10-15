<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing records remain unknown; training stages are not reliable evidence.
        Schema::table('associations', function (Blueprint $table) {
            $table->string('assistance_status', 20)->nullable();
        });
        DB::statement("ALTER TABLE associations ADD CONSTRAINT associations_assistance_status_check CHECK (assistance_status IS NULL OR assistance_status IN ('proposal', 'accepted', 'terminated'))");
        DB::unprepared(<<<'SQL'
CREATE FUNCTION prevent_assistance_reactivation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.assistance_status = 'terminated' AND NEW.assistance_status IS DISTINCT FROM OLD.assistance_status THEN
        RAISE EXCEPTION 'Terminated assistance status is final.';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER associations_assistance_final BEFORE UPDATE OF assistance_status ON associations
FOR EACH ROW EXECUTE FUNCTION prevent_assistance_reactivation();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS associations_assistance_final ON associations');
        DB::statement('DROP FUNCTION IF EXISTS prevent_assistance_reactivation()');
        DB::statement('ALTER TABLE associations DROP CONSTRAINT IF EXISTS associations_assistance_status_check');
        Schema::table('associations', fn (Blueprint $table) => $table->dropColumn('assistance_status'));
    }
};
