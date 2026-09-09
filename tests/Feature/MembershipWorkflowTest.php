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
        return app(MembershipWorkflowService::class)->submit(User::findOrFail(3), $this->profile());
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
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'SUBMIT')->count());
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
        app(MembershipWorkflowService::class)->submit(User::findOrFail(3), array_replace($this->profile(), ['first_name' => '  APPLICANT  ', 'middle_name' => '']));
    }

    public function test_approval_creates_one_member_and_records_reviewer_and_audit(): void
    {
        $this->provision();
        $application = $this->pending();
        $this->withSession($this->sessionFor(3, 'Association Member'))
            ->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Approved', 'review_passphrase' => self::SECRET])
            ->assertRedirect(route('membership.applications.show', $application))->assertSessionHas('success');
        $application->refresh();
        $this->assertSame('Approved', $application->status->status_name);
        $this->assertSame(1, (int) $application->reviewed_by_member_id);
        $this->assertNotNull($application->reviewed_at);
        $this->assertSame(1, Member::where('application_id', $application->id)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'APPROVE')->count());
        try {
            app(MembershipWorkflowService::class)->review(User::findOrFail(3), $application, ['decision' => 'Approved', 'review_passphrase' => self::SECRET]);
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
        $this->withSession($this->sessionFor(3, 'Association Member'))
            ->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Rejected', 'review_passphrase' => self::SECRET])
            ->assertSessionHasErrors('rejection_reason')->assertSessionMissing('_old_input.review_passphrase');
        app(MembershipWorkflowService::class)->review(User::findOrFail(3), $application, [
            'decision' => 'Rejected', 'rejection_reason' => 'Eligibility documents are incomplete.', 'review_passphrase' => self::SECRET,
        ]);
        $this->assertSame('Rejected', $application->fresh()->status->status_name);
        $this->assertSame('Eligibility documents are incomplete.', $application->fresh()->rejection_reason);
        $this->assertSame(0, Member::where('application_id', $application->id)->count());
    }

    public function test_incorrect_secret_leaves_pending_and_is_not_flushed_into_old_input(): void
    {
        $this->provision();
        $application = $this->pending();
        $this->withSession($this->sessionFor(3, 'Association Member'))->from('/membership/applications/'.$application->id)
            ->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Approved', 'review_passphrase' => 'wrong secret'])
            ->assertSessionHas('error')->assertSessionMissing('_old_input.review_passphrase');
        $this->assertSame('Pending', $application->fresh()->status->status_name);
    }

    public function test_audit_failure_rolls_back_approval_and_created_member(): void
    {
        $this->provision();
        $application = $this->pending();
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_review_audit CHECK (action_type <> 'APPROVE')");
        try {
            app(MembershipWorkflowService::class)->review(User::findOrFail(3), $application, ['decision' => 'Approved', 'review_passphrase' => self::SECRET]);
            $this->fail('Review audit should fail.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame('Pending', $application->fresh()->status->status_name);
            $this->assertSame(0, Member::where('application_id', $application->id)->count());
            $this->assertSame(0, DB::table('audit_logs')->where('action_type', 'CREATE')->count());
        }
    }

    public function test_admin_and_officer_cannot_review_even_with_correct_passphrase(): void
    {
        $this->provision();
        $application = $this->pending();
        foreach ([1 => 'System Administrator', 2 => 'Field Officer'] as $id => $role) {
            $this->withSession($this->sessionFor($id, $role))->patch('/membership/applications/'.$application->id.'/review', [
                'decision' => 'Approved', 'review_passphrase' => self::SECRET,
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
            $this->withSession($this->sessionFor(3, 'Association Member'))->patch('/membership/applications/'.$application->id.'/review', [
                'decision' => 'Approved', 'review_passphrase' => 'incorrect',
            ])->assertRedirect();
        }
        $this->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Approved', 'review_passphrase' => self::SECRET])->assertStatus(429);
    }
}
