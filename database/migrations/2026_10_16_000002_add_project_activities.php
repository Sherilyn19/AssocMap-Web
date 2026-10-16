<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->date('proposal_accepted_on')->nullable();
            $table->boolean('budget_accepted')->default(false);
        });
        Schema::create('project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('state', 20)->default('Planned');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'id']);
        });
        Schema::table('project_materials', fn (Blueprint $table) => $table->unsignedBigInteger('activity_id')->nullable());
        // A composite key prevents a material from referencing another project's activity.
        DB::statement('ALTER TABLE project_materials ADD CONSTRAINT material_activity_project_fk FOREIGN KEY (project_id, activity_id) REFERENCES project_activities(project_id, id)');
        DB::statement("ALTER TABLE project_activities ADD CONSTRAINT project_activity_state_check CHECK (state IN ('Planned','Ongoing','Completed'))");
        DB::statement('ALTER TABLE project_activities ADD CONSTRAINT project_activity_dates_check CHECK (ends_on >= starts_on)');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT proposal_acceptance_order_check CHECK (proposal_accepted_on IS NULL OR implementation_date IS NULL OR proposal_accepted_on <= implementation_date)');
        // The database serializes competing Admin and FO writes. Fail on existing
        // conflicts instead of silently changing historical project statuses.
        DB::unprepared(<<<'SQL'
CREATE FUNCTION enforce_one_ongoing_project() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    PERFORM id FROM associations WHERE id IN (NEW.association_id, CASE WHEN TG_OP = 'UPDATE' THEN OLD.association_id ELSE NEW.association_id END) ORDER BY id FOR UPDATE;
    IF NOT NEW.is_archived AND EXISTS (SELECT 1 FROM statuses WHERE id = NEW.status_id AND status_name = 'Ongoing')
       AND EXISTS (SELECT 1 FROM projects p JOIN statuses s ON s.id=p.status_id WHERE p.association_id=NEW.association_id AND p.id<>NEW.id AND NOT p.is_archived AND s.status_name='Ongoing') THEN
        RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='Only one ongoing project is allowed per association.';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER project_one_ongoing BEFORE INSERT OR UPDATE ON projects FOR EACH ROW EXECUTE FUNCTION enforce_one_ongoing_project();
SQL);
        if (DB::table('projects as p')->select('p.association_id')->join('statuses as s', 's.id', '=', 'p.status_id')->where('p.is_archived', false)->where('s.status_name', 'Ongoing')->groupBy('p.association_id')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Resolve associations with multiple ongoing projects before migrating. No records were changed.');
        }
        // Reuse the existing termination guard so activity writes obey the same boundary.
        DB::unprepared("CREATE FUNCTION guard_project_activity() RETURNS trigger LANGUAGE plpgsql AS $$ DECLARE parent_id bigint; BEGIN parent_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.project_id ELSE NEW.project_id END; PERFORM a.id FROM associations a JOIN projects p ON p.association_id=a.id WHERE p.id=parent_id FOR UPDATE OF a; IF EXISTS (SELECT 1 FROM projects p JOIN associations a ON a.id=p.association_id WHERE p.id=parent_id AND (p.is_archived OR a.is_archived OR a.assistance_status='terminated')) THEN RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='This project is read-only.'; END IF; IF TG_OP='UPDATE' AND NEW.project_id<>OLD.project_id THEN RAISE EXCEPTION 'Activities cannot move between projects'; END IF; IF TG_OP='DELETE' THEN RETURN OLD; END IF; RETURN NEW; END $$; CREATE TRIGGER project_activity_writable BEFORE INSERT OR UPDATE OR DELETE ON project_activities FOR EACH ROW EXECUTE FUNCTION guard_project_activity();");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS project_one_ongoing ON projects; DROP FUNCTION IF EXISTS enforce_one_ongoing_project(); DROP TRIGGER IF EXISTS project_activity_writable ON project_activities; DROP FUNCTION IF EXISTS guard_project_activity();');
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS proposal_acceptance_order_check');
        DB::statement('ALTER TABLE project_materials DROP CONSTRAINT material_activity_project_fk');
        Schema::table('project_materials', fn (Blueprint $table) => $table->dropColumn('activity_id'));
        Schema::dropIfExists('project_activities');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['proposal_accepted_on', 'budget_accepted']));
    }
};
