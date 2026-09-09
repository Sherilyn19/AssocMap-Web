<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\MembershipRuleException;
use App\Models\Association;
use App\Models\Member;
use App\Services\AssociationManagementService;
use App\Services\MemberManagementService;
use Illuminate\Support\Facades\DB;
use Tests\Support\MembershipDatabaseTestCase;

/** Regression scenarios from the full-stack assessment, using real PostgreSQL constraints. */
final class MemberSafetyTest extends MembershipDatabaseTestCase
{
    public function test_stale_admin_session_cannot_read_global_members_or_applications(): void
    {
        foreach (['/admin/members', '/admin/members/applications'] as $url) {
            $this->withSession($this->sessionFor(2, 'System Administrator'))
                ->get($url)->assertRedirect(route('dashboard.officer'));
        }
    }

    public function test_inactive_account_is_removed_from_session(): void
    {
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->withSession($this->sessionFor(1, 'System Administrator'))
            ->get('/admin/members')->assertRedirect(route('login'))->assertSessionMissing('auth_user');
    }

    public function test_invalid_date_and_array_name_return_validation_errors(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'))->from('/admin/members')
            ->put('/admin/members/1', [
                'first_name' => ['invalid'], 'last_name' => 'One', 'birthday' => 'not-a-date',
                'sex_id' => 1, 'date_registered' => '2020-01-01',
            ])->assertRedirect('/admin/members')->assertSessionHasErrors(['first_name', 'birthday'])
            ->assertSessionHas('edit_member_id', 1);
    }

    public function test_edit_errors_preserve_attempted_values_in_recovery_payload(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'))->from('/admin/members')
            ->put('/admin/members/1', [
                'first_name' => 'Corrected Name', 'last_name' => 'One', 'birthday' => '1980-01-01',
                'sex_id' => 1, 'date_registered' => '2020-01-01', 'contact_number' => 'bad contact',
            ])->assertSessionHasErrors('contact_number');
        $this->get('/admin/members')->assertOk()->assertSee('data-edit-recovery', false)->assertSee('Corrected Name');
    }

    public function test_representative_cannot_be_archived(): void
    {
        $this->expectException(MembershipRuleException::class);
        app(MemberManagementService::class)->archive(Member::findOrFail(1), 1);
    }

    public function test_assignment_rechecks_member_archived_after_request_validation(): void
    {
        // Reproduce the stale-validation ordering: eligibility changes before service entry.
        DB::table('associations')->where('id', 1)->update(['representative_member_id' => null]);
        $member = Member::findOrFail(1);
        app(MemberManagementService::class)->archive($member, 1);
        try {
            app(AssociationManagementService::class)->assignRepresentative(Association::findOrFail(1), 1, 1);
            $this->fail('Archived representative assignment should fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('current member', $exception->getMessage());
        }
        $this->assertNull(Association::findOrFail(1)->representative_member_id);
    }

    public function test_audit_failure_rolls_back_member_edit(): void
    {
        // A database-level failure proves save + audit are one atomic unit.
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_test_audit CHECK (action_type <> 'UPDATE')");
        $member = Member::findOrFail(1);
        try {
            app(MemberManagementService::class)->update($member, [
                'first_name' => 'Must Roll Back', 'last_name' => 'One', 'birthday' => '1980-01-01',
                'sex_id' => 1, 'date_registered' => '2020-01-01',
            ], 1);
            $this->fail('Audit insert should fail.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame('Representative', $member->fresh()->first_name);
        }
    }
}
