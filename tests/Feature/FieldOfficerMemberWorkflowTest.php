<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\MembershipRuleException;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\MemberDraft;
use App\Models\User;
use App\Services\FieldOfficerMembershipService;
use App\Services\MembershipWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\MembershipDatabaseTestCase;

final class FieldOfficerMemberWorkflowTest extends MembershipDatabaseTestCase
{
    private const SECRET = 'private representative phrase';

    private function profile(): array
    {
        return [
            'first_name' => 'New',
            'last_name' => 'Applicant',
            'birthday' => '1995-01-02',
            'sex_id' => 1,
        ];
    }

    private function officer(): User
    {
        return User::findOrFail(2);
    }

    private function service(): FieldOfficerMembershipService
    {
        return app(FieldOfficerMembershipService::class);
    }

    private function provision(): void
    {
        app(MembershipWorkflowService::class)->setReviewPassphrase(
            User::findOrFail(1),
            Member::findOrFail(1),
            self::SECRET
        );
    }

    public function test_draft_does_not_create_application_member_or_account(): void
    {
        $draft = $this->service()->create($this->officer(), 1, []);

        $this->assertSame('draft', $draft->state);
        $this->assertSame(0, MemberApplication::count());
        $this->assertSame(2, Member::count());
        $this->assertSame(3, User::count());
    }

    public function test_missing_representative_credential_blocks_submission(): void
    {
        $draft = $this->service()->create($this->officer(), 1, $this->profile());

        try {
            $this->service()->submit($this->officer(), $draft, 1);
            $this->fail('Submission should be blocked.');
        } catch (MembershipRuleException) {
            $this->assertSame('draft', $draft->fresh()->state);
            $this->assertSame(0, MemberApplication::count());
        }
    }

    public function test_incomplete_draft_cannot_be_submitted(): void
    {
        $this->provision();
        $draft = $this->service()->create($this->officer(), 1, []);

        try {
            $this->service()->submit($this->officer(), $draft, 1);
            $this->fail('Incomplete profile should fail.');
        } catch (ValidationException) {
            $this->assertSame('draft', $draft->fresh()->state);
            $this->assertSame(0, MemberApplication::count());
        }
    }

    public function test_submission_is_pending_and_approval_creates_one_member(): void
    {
        $this->provision();
        $draft = $this->service()->create($this->officer(), 1, $this->profile());

        $application = $this->service()->submit($this->officer(), $draft, 1);

        $this->assertSame('Pending', $application->status->status_name);
        $this->assertSame('submitted', $draft->fresh()->state);
        $this->assertSame(2, Member::count());

        $approved = app(MembershipWorkflowService::class)->review(
            User::findOrFail(3),
            $application,
            ['decision' => 'Approved', 'review_passphrase' => self::SECRET]
        );

        $this->assertSame('Approved', $approved->status->status_name);
        $this->assertSame(1, Member::where('application_id', $application->id)->count());
        $this->assertSame(3, User::count());
    }

    public function test_cancelled_draft_cannot_be_submitted(): void
    {
        $draft = $this->service()->create($this->officer(), 1, $this->profile());
        $this->service()->cancel($this->officer(), $draft, 1);

        try {
            $this->service()->submit($this->officer(), $draft->fresh(), 2);
            $this->fail('Cancelled draft should not be submitted.');
        } catch (AuthorizationException) {
            $this->assertSame('cancelled', $draft->fresh()->state);
            $this->assertSame(0, MemberApplication::count());
        }
    }

    public function test_stale_revision_cannot_overwrite_a_saved_draft(): void
    {
        $draft = $this->service()->create($this->officer(), 1, []);
        $this->service()->update($this->officer(), $draft, $this->profile(), 1);

        $this->expectException(MembershipRuleException::class);
        $this->service()->update($this->officer(), $draft, [], 1);
    }

    public function test_reassignment_removes_creator_access_and_does_not_transfer_draft(): void
    {
        $draft = $this->service()->create($this->officer(), 1, []);

        $replacement = User::create([
            'name' => 'Replacement Officer',
            'email' => 'replacement@example.test',
            'role_id' => 2,
            'is_active' => true,
        ]);

        DB::table('associations')->where('id', 1)
            ->update(['field_officer_id' => $replacement->id]);

        $this->assertFalse(Gate::forUser($this->officer())->allows('view', $draft->fresh()));
        $this->assertFalse(Gate::forUser($replacement)->allows('view', $draft->fresh()));
        $this->assertTrue(Gate::forUser(User::findOrFail(1))->allows('view', $draft->fresh()));
    }

    public function test_foreign_association_creation_is_denied(): void
    {
        try {
            $this->service()->create($this->officer(), 2, []);
            $this->fail('Foreign association should be denied.');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame(403, $error->getStatusCode());
            $this->assertSame(0, MemberDraft::count());
        }
    }

    public function test_wrong_representative_secret_rolls_back_direct_registration(): void
    {
        $this->provision();

        try {
            app(MembershipWorkflowService::class)->submit(
                User::findOrFail(3),
                $this->profile() + ['review_passphrase' => 'wrong secret']
            );

            $this->fail('Wrong passphrase should fail.');
        } catch (MembershipRuleException) {
            $this->assertSame(0, MemberApplication::count());
            $this->assertSame(2, Member::count());
        }
    }

    public function test_direct_registration_does_not_require_an_assigned_officer(): void
    {
        $this->provision();

        DB::table('associations')->where('id', 1)->update(['field_officer_id' => null]);

        $application = app(MembershipWorkflowService::class)->submit(
            User::findOrFail(3),
            $this->profile() + ['review_passphrase' => self::SECRET]
        );

        $this->assertSame('Approved', $application->status->status_name);
        $this->assertSame(1, Member::where('application_id', $application->id)->count());
        $this->assertSame(3, User::count());
    }

    public function test_representative_cannot_be_archived_by_officer(): void
    {
        $this->assertFalse(
            Gate::forUser($this->officer())->allows('archive', Member::findOrFail(1))
        );

        $this->expectException(AuthorizationException::class);
        $this->service()->archiveMember($this->officer(), Member::findOrFail(1));
    }

    public function test_member_edit_changes_only_contact_number(): void
    {
        $member = Member::findOrFail(1);

        $this->service()->updateMember($this->officer(), $member, [
            'contact_number' => '09123456789',
            'first_name' => 'Forged replacement',
            'association_id' => 2,
        ]);

        $member->refresh();

        $this->assertSame('09123456789', $member->contact_number);
        $this->assertSame('Representative', $member->first_name);
        $this->assertSame(1, (int) $member->association_id);
    }

        /**
     * Create an ordinary official member through the approved workflow.
     * The existing representative remains unchanged.
     */
    private function createOrdinaryMemberForHttpTests(): Member
    {
        $this->provision();

        $draft = $this->service()->create(
            $this->officer(),
            1,
            $this->profile()
        );

        $application = $this->service()->submit(
            $this->officer(),
            $draft,
            1
        );

        app(MembershipWorkflowService::class)->review(
            User::findOrFail(3),
            $application,
            [
                'decision' => 'Approved',
                'review_passphrase' => self::SECRET,
            ]
        );

        return Member::query()
            ->where('application_id', $application->id)
            ->firstOrFail();
    }

    public function test_contact_save_returns_successful_json(): void
    {
        $member = $this->createOrdinaryMemberForHttpTests();

        // Use an HTTP request to verify the response expected by the drawer.
        $this->withSession($this->sessionFor(2, 'Field Officer'))
            ->putJson('/officer/members/'.$member->id, [
                'contact_number' => '09123456789',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Contact number updated.')
            ->assertJsonPath(
                'url',
                route('officer.members.edit', $member)
            );

        // Confirm that success feedback matches the saved database value.
        $this->assertSame(
            '09123456789',
            $member->fresh()->contact_number
        );
    }

    public function test_archive_returns_successful_json(): void
    {
        $member = $this->createOrdinaryMemberForHttpTests();

        $this->withSession($this->sessionFor(2, 'Field Officer'))
            ->patchJson('/officer/members/'.$member->id.'/archive', [
                'confirm' => '1',
            ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Member archived. Historical records were retained.'
            )
            ->assertJsonPath(
                'url',
                route('membership.index', ['tab' => 'members'])
            );

        // The member remains stored and becomes a historical record.
        $this->assertTrue((bool) $member->fresh()->is_archived);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => 2,
            'action_type' => 'ARCHIVE',
            'module' => 'Member',
            'record_id' => $member->id,
        ]);
    }

    public function test_member_record_filters_preserve_officer_scope(): void
    {
        $archived = $this->createOrdinaryMemberForHttpTests();

        $this->service()->archiveMember(
            $this->officer(),
            $archived
        );

        // Member 1 is current and assigned. The new member is archived.
        // Member 2 belongs to another association and must never be returned.
        $expectedByState = [
            'current' => [1],
            'archived' => [(int) $archived->id],
            'all' => [1, (int) $archived->id],
        ];

        foreach ($expectedByState as $state => $expectedIds) {
            sort($expectedIds);

            $response = $this
                ->withSession($this->sessionFor(2, 'Field Officer'))
                ->get('/membership?tab=members&record_state='.$state);

            $response->assertOk();

            // Inspect the actual paginated records, not incidental page text.
            $response->assertViewHas(
                'members',
                function ($members) use ($expectedIds): bool {
                    $actualIds = $members->getCollection()
                        ->map(fn ($member): int => (int) $member->id)
                        ->sort()
                        ->values()
                        ->all();

                    return $actualIds === $expectedIds;
                }
            );
        }

        // Without a filter, the register must default to current members.
        $this->get('/membership?tab=members')
            ->assertOk()
            ->assertViewHas('members', function ($members): bool {
                return $members->getCollection()
                    ->map(fn ($member): int => (int) $member->id)
                    ->values()
                    ->all() === [1];
            });
    }

    public function test_archived_member_is_viewable_but_not_editable(): void
    {
        $member = $this->createOrdinaryMemberForHttpTests();

        $this->service()->archiveMember(
            $this->officer(),
            $member
        );

        $originalContact = $member->fresh()->contact_number;

        // Historical details remain accessible within the officer's assignment.
        $this->withSession($this->sessionFor(2, 'Field Officer'))
            ->get('/membership/members/'.$member->id)
            ->assertOk()
            ->assertDontSee('Manage member');

        // Direct URLs and manually crafted requests must also be denied.
        $this->get('/officer/members/'.$member->id.'/edit')
            ->assertForbidden();

        $this->putJson('/officer/members/'.$member->id, [
            'contact_number' => '09999999999',
        ])->assertForbidden();

        $this->patchJson('/officer/members/'.$member->id.'/archive', [
            'confirm' => '1',
        ])->assertForbidden();

        $member->refresh();

        $this->assertTrue((bool) $member->is_archived);
        $this->assertSame($originalContact, $member->contact_number);
    }

    public function test_audit_failure_rolls_back_draft_creation(): void
    {
        DB::statement(
            "ALTER TABLE audit_logs ADD CONSTRAINT reject_draft_audit
             CHECK (module <> 'Member Draft')"
        );

        try {
            $this->service()->create($this->officer(), 1, []);
            $this->fail('Audit failure should prevent creation.');
        } catch (QueryException) {
            $this->assertSame(0, MemberDraft::count());
        }
    }
}