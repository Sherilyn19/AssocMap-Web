<?php

namespace Tests\Feature;

use App\Models\Association;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssociationTerminationDatabaseTestCase;

final class AssociationAssistanceTest extends AssociationTerminationDatabaseTestCase
{
    private function change(array $extra = [])
    {
        return $this->patchJson('/associations/1/assistance-status', array_replace([
            'assistance_status' => 'accepted', 'previous_status' => null,
            'reason' => 'Assessment approved.',
            'assessment_date' => now('Asia/Manila')->toDateString(),
            'assistance_confirmation' => 'TERMINATE ASSOC-000001',
        ], $extra));
    }

    public function test_admin_and_assigned_officer_share_status_and_audit(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->change()->assertOk();
        $this->withSession($this->sessionFor(2, 'Field Officer'));
        $this->change(['previous_status' => 'accepted', 'assistance_status' => 'terminated'])->assertOk();
        $this->assertSame('terminated', Association::findOrFail(1)->assistance_status);
        $audit = DB::table('audit_logs')->where('module', 'Association')->orderByDesc('id')->first();
        $details = json_decode($audit->details, true);
        $this->assertSame('accepted', $details['before']);
        $this->assertSame('terminated', $details['after']);
        $this->assertSame('Assessment approved.', $details['reason']);
        $this->assertSame(2, (int) $audit->user_id);
        $this->assertNotNull($audit->performed_at);
        $this->change(['previous_status' => 'terminated'])->assertUnprocessable();
    }

    public function test_member_and_reassigned_officer_cannot_write(): void
    {
        $this->withSession($this->sessionFor(3, 'Association Member'));
        $this->change()->assertForbidden();
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => null]);
        $this->withSession($this->sessionFor(2, 'Field Officer'));
        $this->change()->assertNotFound();
        $this->assertNull(Association::findOrFail(1)->assistance_status);
    }

    public function test_validation_and_stale_form_do_not_change_status(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->change(['reason' => '   '])->assertUnprocessable();
        $this->change(['assistance_status' => 'completed'])->assertUnprocessable();
        $this->change(['previous_status' => 'proposal'])->assertUnprocessable();
        $this->assertNull(Association::findOrFail(1)->assistance_status);
    }

    public function test_audit_failure_rolls_back_status(): void
    {
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_assistance CHECK (module <> 'Association')");
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->change()->assertStatus(500);
        $this->assertNull(Association::findOrFail(1)->assistance_status);
    }

    public function test_database_rejects_reactivation(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->change(['assistance_status' => 'terminated'])->assertOk();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::transaction(fn () => DB::table('associations')->where('id', 1)->update(['assistance_status' => null]));
    }
}
