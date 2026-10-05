<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\MembershipRuleException;
use App\Models\Member;
use App\Models\User;
use App\Services\AdminUserManagementService;
use App\Services\MembershipWorkflowService;
use App\Services\FieldOfficerMembershipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Support\MembershipDatabaseTestCase;

final class MembershipAccountSecurityTest extends MembershipDatabaseTestCase
{
    private const SECRET = 'Private-representative-2026';

    public function test_shared_password_cannot_be_reset_to_review_secret(): void
    {
        $workflow = app(MembershipWorkflowService::class);
        $workflow->setReviewPassphrase(User::findOrFail(1), Member::findOrFail(1), self::SECRET);
        $before = User::findOrFail(3)->password;
        try {
            app(AdminUserManagementService::class)->update(3, $this->account(self::SECRET), 1);
            $this->fail('The shared password must remain separate.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('password', $error->errors());
        }
        $this->assertSame($before, User::findOrFail(3)->password);
        $this->assertSame(0, DB::table('audit_logs')->where('action_type', 'UPDATE')->count());
        app(AdminUserManagementService::class)->update(3, $this->account('Different-shared-password'), 1);
        $this->assertTrue(Hash::check('Different-shared-password', User::findOrFail(3)->password));
        $this->assertTrue(Hash::check(self::SECRET, Member::findOrFail(1)->review_passphrase_hash));
    }

    public function test_review_secret_cannot_match_shared_password(): void
    {
        DB::table('users')->where('id', 3)->update(['password' => Hash::make(self::SECRET)]);
        $this->expectException(MembershipRuleException::class);
        app(MembershipWorkflowService::class)->setReviewPassphrase(User::findOrFail(1), Member::findOrFail(1), self::SECRET);
    }

    public function test_reassignment_requires_password_when_target_has_representative(): void
    {
        DB::table('associations')->where('id', 2)->update(['representative_member_id' => 2]);
        try {
            app(AdminUserManagementService::class)->update(3, array_replace($this->account(null), ['association_id' => 2]), 1);
            $this->fail('A retained hash cannot establish separation from the new representative secret.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('password', $error->errors());
        }
        $this->assertSame(1, (int) User::findOrFail(3)->association_id);
    }

    public function test_stale_account_cannot_submit_after_revocation_reassignment_or_password_change(): void
    {
        foreach ([['is_active' => false], ['role_id' => 2], ['association_id' => 2], ['password' => Hash::make('Changed-password')]] as $change) {
            DB::table('users')->where('id', 3)->update(['is_active' => true, 'role_id' => 3, 'association_id' => 1, 'password' => null]);
            $actor = User::with('role')->findOrFail(3);
            DB::table('users')->where('id', 3)->update($change);
            try {
                app(MembershipWorkflowService::class)->submit($actor, $this->profile());
                $this->fail('The stale account must not submit.');
            } catch (MembershipRuleException $error) {
                $this->assertStringContainsString('account', $error->getMessage());
            }
        }
        $this->assertSame(0, DB::table('member_applications')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_stale_account_cannot_approve_or_reject_after_deactivation(): void
    {
        $service = app(MembershipWorkflowService::class);
        $service->setReviewPassphrase(User::findOrFail(1), Member::findOrFail(1), self::SECRET);
        $actor = User::with('role')->findOrFail(3);
        // Prepare a Pending application through the FO workflow.
        // The association account is still the actor whose review access is tested.
        $officer = User::findOrFail(2);
        $draftWorkflow = app(FieldOfficerMembershipService::class);

        $draft = $draftWorkflow->create(
            $officer,
            1,
            $this->profile()
        );

        $application = $draftWorkflow->submit(
            $officer,
            $draft,
            $draft->revision
        );
        DB::table('users')->where('id', 3)->update(['is_active' => false]);
        foreach (['Approved', 'Rejected'] as $decision) {
            try {
                $service->review($actor, $application, ['decision' => $decision, 'review_passphrase' => self::SECRET, 'rejection_reason' => 'Incomplete documents']);
                $this->fail('A revoked account must not review.');
            } catch (MembershipRuleException $error) {
                $this->assertSame('Pending', $application->fresh()->status->status_name);
            }
        }
        $this->assertSame(0, Member::where('application_id', $application->id)->count());
    }

    private function account(?string $password): array
    {
        return ['name' => 'Association', 'email' => 'association@example.test', 'role_id' => 3, 'association_id' => 1, 'password' => $password];
    }

    private function profile(): array
    {
        return ['first_name' => 'Applicant', 'last_name' => 'Example', 'birthday' => '1990-02-03', 'sex_id' => 1];
    }
}
