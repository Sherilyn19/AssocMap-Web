<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\MembershipRuleException;
use App\Models\Association;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\User;
use App\Services\AssociationManagementService;
use App\Services\MembershipWorkflowService;
use App\Services\FieldOfficerMembershipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\MembershipDatabaseTestCase;

/** Tests assert business outcomes, including failures, rather than method implementation. */
final class MembershipWorkflowTest extends MembershipDatabaseTestCase
{
    private const SECRET = 'private representative phrase';

    private function profile(): array
    {
        return ['first_name' => 'Applicant', 'middle_name' => null, 'last_name' => 'Example', 'birthday' => '1990-02-03', 'sex_id' => 1];
    }

    private function pending(): MemberApplication
    {
        // FO submission requires a configured representative.
        // Avoid provisioning again when the test already configured the secret.
        if (!Member::findOrFail(1)->review_passphrase_hash) {
            $this->provision();
        }

        $officer = User::findOrFail(2);
        $workflow = app(FieldOfficerMembershipService::class);

        // A Pending application must now come from a saved FO draft.
        $draft = $workflow->create($officer, 1, $this->profile());

        return $workflow->submit(
            $officer,
            $draft,
            $draft->revision
        );
    }

    private function provision(): void
    {
        app(MembershipWorkflowService::class)->setReviewPassphrase(User::findOrFail(1), Member::findOrFail(1), self::SECRET);
    }

    public function test_submission_derives_pending_status_and_association_and_does_not_create_member(): void
    {
        $application = $this->pending();
        $this->assertSame(1, (int) $application->association_id);
        $this->assertSame('Pending', $application->status->status_name);
        $this->assertSame(2, Member::count());
        // Submission records one audit for the application and one for its draft.
        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('module', 'Member Application')
                ->where('action_type', 'SUBMIT')
                ->count()
        );

        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('module', 'Member Draft')
                ->where('action_type', 'SUBMIT')
                ->count()
        );
    }

    public function test_association_submission_waits_for_officer_approval(): void
    {
        $membersBefore = Member::count();
        $accountsBefore = User::count();

        $application = app(MembershipWorkflowService::class)->submit(
            User::findOrFail(3),
            $this->profile()
        );

        $this->assertSame('Pending', $application->fresh()->status->status_name);
        $this->assertSame($membersBefore, Member::count());
        $this->assertSame($accountsBefore, User::count());
        $this->assertNull($application->reviewed_by_member_id);
        $this->assertNull($application->reviewed_at);

        $this->assertDatabaseHas('member_applications', [
            'id' => $application->id,
            'association_id' => 1,
            'submitted_by_user_id' => 3,
            'submission_source' => 'association_account',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Member Application',
            'record_id' => $application->id,
            'action_type' => 'SUBMIT',
        ]);
    }

    public function test_submission_cannot_include_a_review_decision_or_secret(): void
    {
        $this->withSession($this->sessionFor(3, 'Association Member'))
            ->post('/membership/applications', $this->profile() + [
                'decision' => 'Approved',
                'review_passphrase' => 'private secret',
            ])
            ->assertSessionHasErrors(['decision', 'review_passphrase']);

        $this->assertSame(0, MemberApplication::count());
        $this->assertSame(2, Member::count());
    }

    public function test_submission_rejects_browser_controlled_ownership_and_status(): void
    {
        $this->withSession($this->sessionFor(3, 'Association Member'))->post('/membership/applications', $this->profile() + ['association_id' => 2, 'status_id' => 2])
            ->assertSessionHasErrors(['association_id', 'status_id']);
        $this->assertSame(0, MemberApplication::count());
    }

    public function test_normalized_duplicate_submission_is_rejected(): void
    {
        $this->pending();

        $this->expectException(MembershipRuleException::class);

        // Use valid representative credentials so this tests duplicate identity,
        // rather than failing because the passphrase was omitted.
        app(MembershipWorkflowService::class)->submit(
            User::findOrFail(3),
            array_replace($this->profile(), [
                'first_name' => '  APPLICANT  ',
                'middle_name' => '',
                'review_passphrase' => self::SECRET,
            ])
        );
    }

    public function test_approval_creates_one_member_and_records_reviewer_and_audit(): void
    {
        $this->provision();
        $application = $this->pending();
        $this->withSession($this->sessionFor(2, 'Field Officer'))
            ->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Approved', 'decision_confirmed' => '1'])
            ->assertRedirect(route('membership.applications.show', $application))->assertSessionHas('success');
        $application->refresh();
        $this->assertSame('Approved', $application->status->status_name);
        $this->assertSame(2, (int) $application->reviewed_by_user_id);
        $this->assertNotNull($application->reviewed_at);
        $this->assertSame(1, Member::where('application_id', $application->id)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'APPROVE')->count());
        try {
            app(MembershipWorkflowService::class)->review(User::findOrFail(2), $application, ['decision' => 'Approved', 'decision_confirmed' => '1']);
            $this->fail('Repeated approval must fail.');
        } catch (MembershipRuleException $exception) {
            $this->assertStringContainsString('already been reviewed', $exception->getMessage());
        }
        $this->assertSame(1, Member::where('application_id', $application->id)->count());
    }

    public function test_rejection_requires_reason_and_never_flashes_secret(): void
    {
        $this->provision();
        $application = $this->pending();
        $this->withSession($this->sessionFor(2, 'Field Officer'))
            ->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Rejected', 'decision_confirmed' => '1'])
            ->assertSessionHasErrors('rejection_reason')->assertSessionMissing('_old_input.review_passphrase');
        app(MembershipWorkflowService::class)->review(User::findOrFail(2), $application, [
            'decision' => 'Rejected', 'rejection_reason' => 'Eligibility documents are incomplete.', 'decision_confirmed' => '1',
        ]);
        $this->assertSame('Rejected', $application->fresh()->status->status_name);
        $this->assertSame('Eligibility documents are incomplete.', $application->fresh()->rejection_reason);
        $this->assertSame(0, Member::where('application_id', $application->id)->count());
    }

    public function test_incorrect_secret_leaves_pending_and_is_not_flushed_into_old_input(): void
    {
        $this->provision();
        $application = $this->pending();
        $this->withSession($this->sessionFor(2, 'Field Officer'))->from('/membership/applications/'.$application->id)
            ->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Approved', 'review_passphrase' => 'wrong secret'])
            ->assertSessionHasErrors('review_passphrase')->assertSessionMissing('_old_input.review_passphrase');
        $this->assertSame('Pending', $application->fresh()->status->status_name);
    }

    public function test_audit_failure_rolls_back_approval_and_created_member(): void
    {
        $this->provision();
        $application = $this->pending();
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_review_audit CHECK (action_type <> 'APPROVE')");
        try {
            app(MembershipWorkflowService::class)->review(User::findOrFail(2), $application, ['decision' => 'Approved', 'decision_confirmed' => '1']);
            $this->fail('Review audit should fail.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame('Pending', $application->fresh()->status->status_name);
            $this->assertSame(0, Member::where('application_id', $application->id)->count());
            // The earlier draft creation audit remains valid.
            // Failed approval must not leave a member-creation audit.
            $this->assertSame(
                0,
                DB::table('audit_logs')
                    ->where('module', 'Member')
                    ->where('action_type', 'CREATE')
                    ->count()
            );
        }
    }

    public function test_admin_and_shared_account_cannot_review(): void
    {
        $this->provision();
        $application = $this->pending();
        foreach ([1 => 'System Administrator', 3 => 'Association Member'] as $id => $role) {
            $this->withSession($this->sessionFor($id, $role))->patch('/membership/applications/'.$application->id.'/review', [
                'decision' => 'Approved', 'decision_confirmed' => '1',
            ])->assertForbidden();
        }
    }

    public function test_scoped_lists_and_detail_reject_other_association(): void
    {
        $this->pending();
        foreach ([2 => 'Field Officer', 3 => 'Association Member'] as $id => $role) {
            $this->withSession($this->sessionFor($id, $role))->get('/membership')
                ->assertOk()->assertSee('Representative')->assertDontSee('Other Association');
            $this->get('/membership/members/2')->assertForbidden();
        }
    }

    public function test_credential_is_hashed_hidden_and_invalidated_by_new_appointment(): void
    {
        $this->provision();
        $member = Member::findOrFail(1);
        $this->assertTrue(Hash::check(self::SECRET, $member->review_passphrase_hash));
        $this->assertArrayNotHasKey('review_passphrase_hash', $member->toArray());
        $service = app(AssociationManagementService::class);
        $service->assignRepresentative(Association::findOrFail(1), null, 1);
        $service->assignRepresentative(Association::findOrFail(1), 1, 1);
        $this->assertNull($member->fresh()->review_passphrase_hash);
        $this->assertStringNotContainsString(self::SECRET, json_encode(DB::table('audit_logs')->get()));
    }

    public function test_review_attempts_are_rate_limited_across_applications(): void
    {
        $this->provision();
        $application = $this->pending();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withSession($this->sessionFor(2, 'Field Officer'))->patch('/membership/applications/'.$application->id.'/review', [
                'decision' => 'Approved', 'review_passphrase' => 'incorrect',
            ])->assertRedirect();
        }
        $this->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Approved', 'decision_confirmed' => '1'])->assertStatus(429);
    }
}
