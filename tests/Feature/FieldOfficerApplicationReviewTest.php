<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\MembershipRuleException;
use App\Models\{Member, MemberApplication, User};
use App\Services\MembershipWorkflowService;
use Illuminate\Support\Facades\DB;
use Tests\Support\MembershipDatabaseTestCase;

final class FieldOfficerApplicationReviewTest extends MembershipDatabaseTestCase
{
    public function test_officer_dialog_receives_json_and_blocks_a_second_decision(): void
    {
        $application = $this->pending();
        $this->withSession($this->sessionFor(2, 'Field Officer'))
            ->patchJson(route('membership.applications.review', $application), [
                'decision' => 'Approved', 'decision_confirmed' => '1',
            ])->assertOk()->assertJsonPath('url', route('membership.applications.show', $application));
        $this->patchJson(route('membership.applications.review', $application), [
            'decision' => 'Rejected', 'decision_confirmed' => '1', 'rejection_reason' => 'Second decision',
        ])->assertStatus(409);
        $this->assertSame('Approved', $application->fresh()->status->status_name);
        $this->assertSame(1, Member::where('application_id', $application->id)->count());
    }

    private function pending(): MemberApplication
    {
        return app(MembershipWorkflowService::class)->submit(User::findOrFail(3), [
            'first_name' => 'New', 'last_name' => 'Applicant',
            'birthday' => '1990-02-03', 'sex_id' => 1,
        ]);
    }

    public function test_assigned_officer_can_view_form_and_approve_without_a_passphrase(): void
    {
        $application = $this->pending();
        $this->withSession($this->sessionFor(2, 'Field Officer'))
            ->get(route('membership.applications.show', $application))
            ->assertOk()->assertSee('Field Officer review')->assertDontSee('name="review_passphrase"', false);
        $this->patch(route('membership.applications.review', $application), [
            'decision' => 'Approved', 'decision_confirmed' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $application->refresh();
        $this->assertSame('Approved', $application->status->status_name);
        $this->assertSame(2, (int) $application->reviewed_by_user_id);
        $this->assertNull($application->reviewed_by_member_id);
        $this->assertSame('Officer', $application->reviewer_name);
        $this->assertSame(1, Member::where('application_id', $application->id)->count());
        $this->assertDatabaseHas('audit_logs', ['user_id' => 2, 'action_type' => 'APPROVE', 'record_id' => $application->id]);
    }

    public function test_rejection_requires_reason_and_creates_no_member(): void
    {
        $application = $this->pending();
        $this->withSession($this->sessionFor(2, 'Field Officer'))
            ->patch(route('membership.applications.review', $application), [
                'decision' => 'Rejected', 'decision_confirmed' => '1',
            ])->assertSessionHasErrors('rejection_reason');
        $this->patch(route('membership.applications.review', $application), [
            'decision' => 'Rejected', 'decision_confirmed' => '1', 'rejection_reason' => 'Missing supporting information.',
        ])->assertSessionHas('success');
        $this->assertSame('Rejected', $application->fresh()->status->status_name);
        $this->assertSame(0, Member::where('application_id', $application->id)->count());
        $this->withSession($this->sessionFor(3, 'Association Member'))
            ->get(route('membership.applications.show', $application))
            ->assertOk()->assertSee('Missing supporting information.')->assertSee('Officer');
    }

    public function test_shared_account_and_admin_cannot_review_or_unlock(): void
    {
        $application = $this->pending();
        foreach ([3 => 'Association Member', 1 => 'System Administrator'] as $id => $role) {
            $this->withSession($this->sessionFor($id, $role))
                ->patch(route('membership.applications.review', $application), [
                    'decision' => 'Approved', 'decision_confirmed' => '1',
                ])->assertForbidden();
        }
        $this->withSession($this->sessionFor(3, 'Association Member'))
            ->post('/membership/applications/'.$application->id.'/review-access', ['review_passphrase' => 'anything'])
            ->assertNotFound();
        $this->assertSame('Pending', $application->fresh()->status->status_name);
    }

    public function test_reassignment_and_deactivation_block_stale_service_callers(): void
    {
        $application = $this->pending();
        $actor = User::findOrFail(2);
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => null]);
        try {
            app(MembershipWorkflowService::class)->review($actor, $application, ['decision' => 'Approved']);
            $this->fail('Reassigned officer must be denied.');
        } catch (MembershipRuleException) {
            $this->assertSame('Pending', $application->fresh()->status->status_name);
        }
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 2]);
        DB::table('users')->where('id', 2)->update(['is_active' => false]);
        $this->expectException(MembershipRuleException::class);
        app(MembershipWorkflowService::class)->review($actor, $application, ['decision' => 'Approved']);
    }

    public function test_repeat_decision_is_blocked_and_historical_reviewer_is_preserved(): void
    {
        $application = $this->pending();
        $application->forceFill(['reviewed_by_member_id' => 1, 'reviewed_at' => now(), 'status_id' => 3])->save();
        $this->assertSame('Representative One', $application->fresh()->reviewer_name);
        $this->expectException(MembershipRuleException::class);
        app(MembershipWorkflowService::class)->review(User::findOrFail(2), $application, ['decision' => 'Approved']);
    }

    public function test_review_rejects_forged_reviewer_and_missing_confirmation(): void
    {
        $application = $this->pending();
        $this->withSession($this->sessionFor(2, 'Field Officer'))
            ->patch(route('membership.applications.review', $application), [
                'decision' => 'Approved', 'reviewed_by_user_id' => 1,
            ])->assertSessionHasErrors(['reviewed_by_user_id', 'decision_confirmed']);
        $this->assertSame('Pending', $application->fresh()->status->status_name);
    }
}
