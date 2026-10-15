<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssociationTerminationDatabaseTestCase;

final class AssociationTerminationTest extends AssociationTerminationDatabaseTestCase
{
    private function data(array $extra = []): array
    {
        return array_replace([
            'assistance_status' => 'terminated', 'previous_status' => null,
            'reason' => 'Assessment confirms assistance can end.',
            'assessment_date' => now('Asia/Manila')->toDateString(),
            'assistance_confirmation' => 'TERMINATE ASSOC-000001',
        ], $extra);
    }

    private function admin(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
    }

    private function terminate(): void
    {
        $this->admin();
        $this->patchJson('/associations/1/assistance-status', $this->data())->assertOk();
    }

    private function assertLocked(callable $write): void
    {
        try {
            DB::transaction($write);
            $this->fail('A write to a terminated association was accepted.');
        } catch (QueryException $error) {
            $this->assertSame('P7501', $error->errorInfo[0]);
        }
    }

    public function test_pending_work_blocks_preflight_and_final_save(): void
    {
        $this->admin();
        DB::table('member_drafts')->insert(['association_id'=>1,'created_by_user_id'=>2,'profile'=>'{}','state'=>'draft']);
        $training = DB::table('trainings')->insertGetId(['association_id'=>1]);
        DB::table('training_participants')->insert(['training_id'=>$training,'member_id'=>1,'attendance_status_id'=>1]);
        DB::table('member_applications')->insert(['association_id'=>1,'first_name'=>'Pending','last_name'=>'Applicant','birthday'=>'1990-01-01','sex_id'=>1,'status_id'=>1]);
        $this->getJson('/associations/1/assistance-eligibility')->assertOk()
            ->assertJsonPath('eligible',false)->assertJsonPath('counts.applications',1)
            ->assertJsonPath('counts.drafts',1)->assertJsonPath('counts.attendance',1);
        $this->patchJson('/associations/1/assistance-status',$this->data())->assertUnprocessable();
        $this->assertNull(DB::table('associations')->where('id',1)->value('assistance_status'));
    }

    public function test_final_save_rechecks_work_added_after_preflight(): void
    {
        $this->admin();
        $this->getJson('/associations/1/assistance-eligibility')->assertJsonPath('eligible',true);
        DB::table('member_drafts')->insert(['association_id'=>1,'created_by_user_id'=>2,'profile'=>'{}','state'=>'draft']);
        $this->patchJson('/associations/1/assistance-status',$this->data())->assertUnprocessable();
    }

    public function test_date_and_exact_phrase_are_required(): void
    {
        $this->admin();
        foreach ([['assessment_date'=>null], ['assessment_date'=>now('Asia/Manila')->addDay()->toDateString()],
            ['assistance_confirmation'=>'TERMINATE ASSOC-000002'], ['assistance_confirmation'=>' TERMINATE ASSOC-000001 ']] as $extra) {
            $this->patchJson('/associations/1/assistance-status',$this->data($extra))->assertUnprocessable();
        }
    }

    public function test_termination_saves_assessment_and_audit_together(): void
    {
        $this->terminate();
        $row = DB::table('associations')->where('id',1)->first();
        $this->assertSame('terminated',$row->assistance_status);
        $this->assertSame(now('Asia/Manila')->toDateString(),$row->assistance_assessed_on);
        $this->assertSame(1,(int)$row->assistance_terminated_by);
        $this->assertNotNull($row->assistance_terminated_at);
        $audit = json_decode(DB::table('audit_logs')->where('module','Association')->orderByDesc('id')->value('details'),true);
        $this->assertSame('terminated',$audit['after']);
        $this->assertSame($row->assistance_assessed_on,$audit['assessment_date']);
    }

    public function test_all_domain_tables_reject_inserts_updates_and_deletes(): void
    {
        $project = DB::table('projects')->insertGetId(['association_id'=>1]);
        $material = DB::table('project_materials')->insertGetId(['project_id'=>$project]);
        $training = DB::table('trainings')->insertGetId(['association_id'=>1]);
        $production = DB::table('monitoring_production')->insertGetId(['association_id'=>1,'project_id'=>$project]);
        $rows = [
            'projects'=>['association_id'=>1], 'project_materials'=>['project_id'=>$project],
            'trainings'=>['association_id'=>1], 'training_participants'=>['training_id'=>$training,'member_id'=>1,'attendance_status_id'=>2],
            'monitoring_production'=>['association_id'=>1,'project_id'=>$project],
            'monitoring_income'=>['association_id'=>1,'project_id'=>$project],
            'monitoring_materials'=>['project_material_id'=>$material],
            'production_progress_entries'=>['monitoring_production_id'=>$production],
            'gis_locations'=>['association_id'=>1],
            'gis_submissions'=>['user_id'=>1,'location_id'=>1,'token'=>'123e4567-e89b-12d3-a456-426614174000','payload_hash'=>str_repeat('a',64)],
            'member_drafts'=>['association_id'=>1,'created_by_user_id'=>2,'profile'=>'{}','state'=>'cancelled'],
            'member_applications'=>['association_id'=>1,'first_name'=>'Closed','last_name'=>'Applicant','birthday'=>'1990-01-01','sex_id'=>1,'status_id'=>3],
        ];
        $ids=[];
        foreach ($rows as $table=>$row) $ids[$table]=DB::table($table)->insertGetId($row);
        $this->terminate();
        foreach ($rows as $table=>$row) {
            $this->assertLocked(fn()=>DB::table($table)->insert($row));
            $this->assertLocked(fn()=>DB::table($table)->where('id',$ids[$table])->update($row));
            $this->assertLocked(fn()=>DB::table($table)->where('id',$ids[$table])->delete());
        }
        $this->assertLocked(fn()=>DB::table('members')->insert(['association_id'=>1,'first_name'=>'New','last_name'=>'Member','birthday'=>'1990-01-01','sex_id'=>1,'date_registered'=>'2020-01-01']));
        $this->assertLocked(fn()=>DB::table('members')->where('id',1)->update(['first_name'=>'Changed']));
        $this->assertLocked(fn()=>DB::table('members')->where('id',1)->delete());
        $this->assertLocked(fn()=>DB::table('associations')->where('id',1)->update(['name'=>'Changed']));
        $this->assertLocked(fn()=>DB::table('associations')->where('id',1)->delete());
    }

    public function test_transfers_check_both_previous_and_new_association(): void
    {
        $locked = DB::table('projects')->insertGetId(['association_id'=>1]);
        $other = DB::table('projects')->insertGetId(['association_id'=>2]);
        $this->terminate();
        $this->assertLocked(fn()=>DB::table('projects')->where('id',$locked)->update(['association_id'=>2]));
        $this->assertLocked(fn()=>DB::table('projects')->where('id',$other)->update(['association_id'=>1]));
        DB::table('projects')->where('id',$other)->update(['is_archived'=>true]);
        $this->assertTrue((bool)DB::table('projects')->where('id',$other)->value('is_archived'));
    }

    public function test_members_cannot_preflight_or_terminate(): void
    {
        $this->withSession($this->sessionFor(3,'Association Member'));
        $this->getJson('/associations/1/assistance-eligibility')->assertForbidden();
        $this->patchJson('/associations/1/assistance-status',$this->data())->assertForbidden();
    }
}
