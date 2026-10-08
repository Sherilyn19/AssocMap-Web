<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MembershipRuleException;
use App\Models\Association;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\MemberDraft;
use App\Models\User;
use App\Support\MemberProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class FieldOfficerMembershipService
{
    public function __construct(
        private readonly MemberIdentity $identity,
        private readonly MemberManagementService $members,
    ) {
    }

    public function create(User $actor, int $associationId, array $input): MemberDraft
    {
        return DB::transaction(function () use ($actor, $associationId, $input) {
            [$association, $current] = $this->lockScope($actor, $associationId);

            $draft = new MemberDraft();

            $draft->forceFill([
                'association_id' => $association->id,
                'created_by_user_id' => $current->id,
                'profile' => $this->profile($input, false),
                'state' => 'draft',
                'revision' => 1,
            ])->save();

            $this->audit($current, 'CREATE', $draft, 'Created prospective-member draft.');

            return $draft;
        }, 3);
    }

    public function update(
        User $actor,
        MemberDraft $draft,
        array $input,
        int $revision
    ): MemberDraft {
        return DB::transaction(function () use ($actor, $draft, $input, $revision) {
            [$association, $current] = $this->lockScope(
                $actor,
                (int) $draft->association_id
            );

            $locked = $this->lockDraft(
                $current,
                $association,
                $draft,
                $revision,
                'update'
            );

            $profile = $this->profile($input, false);

            if ($profile !== $locked->profile) {
                $changed = [];

                foreach (MemberProfile::FIELDS as $field) {
                    if (($profile[$field] ?? null) !== ($locked->profile[$field] ?? null)) {
                        $changed[] = $field;
                    }
                }

                $locked->forceFill([
                    'profile' => $profile,
                    'revision' => $locked->revision + 1,
                ])->save();

                // Store field names, not copies of personal information.
                $this->audit(
                    $current,
                    'UPDATE',
                    $locked,
                    'Updated draft fields: '.implode(', ', $changed).'.'
                );
            }

            return $locked;
        }, 3);
    }

    public function cancel(
        User $actor,
        MemberDraft $draft,
        int $revision
    ): MemberDraft {
        return DB::transaction(function () use ($actor, $draft, $revision) {
            [$association, $current] = $this->lockScope(
                $actor,
                (int) $draft->association_id
            );

            $locked = $this->lockDraft(
                $current,
                $association,
                $draft,
                $revision,
                'cancel'
            );

            $locked->forceFill([
                'state' => 'cancelled',
                'cancelled_at' => now(),
                'revision' => $locked->revision + 1,
            ])->save();

            $this->audit(
                $current,
                'CANCEL',
                $locked,
                'Cancelled unsubmitted draft. History retained.'
            );

            return $locked;
        }, 3);
    }

    public function submit(
        User $actor,
        MemberDraft $draft,
        int $revision
    ): MemberApplication {
        return DB::transaction(function () use ($actor, $draft, $revision) {
            [$association, $current] = $this->lockScope(
                $actor,
                (int) $draft->association_id
            );

            $locked = $this->lockDraft(
                $current,
                $association,
                $draft,
                $revision,
                'submit'
            );

            // The FO never provides or verifies the representative's private secret.
            // Submission only checks that an eligible reviewer is configured.
            $representative = Member::query()
                ->whereKey($association->representative_member_id)
                ->where('association_id', $association->id)
                ->where('is_archived', false)
                ->lockForUpdate()
                ->first();

            if (!$representative || !$representative->review_passphrase_hash) {
                throw new MembershipRuleException(
                    'Submission is blocked. This association needs a current '
                    .'representative with a configured review passphrase. '
                    .'Your draft remains saved.'
                );
            }

            // Saved drafts can be incomplete; submitted applications cannot.
            $profile = $this->profile($locked->profile, true);

            foreach (['members', 'member_applications'] as $table) {
                if ($this->identity->exists($table, $association->id, $profile)) {
                    throw new MembershipRuleException(
                        'This person already has a member record or application '
                        .'in this association. Your draft remains saved.'
                    );
                }
            }

            $pendingId = DB::table('statuses')
                ->where('status_name', 'Pending')
                ->value('id');

            if (!$pendingId) {
                throw new MembershipRuleException('The Pending status is not configured.');
            }

            $application = new MemberApplication();

            $application->forceFill($profile + [
                'association_id' => $association->id,
                'status_id' => $pendingId,
                'submitted_by_user_id' => $current->id,
                'submission_source' => 'field_officer',
            ])->save();

            $locked->forceFill([
                'state' => 'submitted',
                'application_id' => $application->id,
                'submitted_at' => now(),
                'revision' => $locked->revision + 1,
            ])->save();

            $this->audit(
                $current,
                'SUBMIT',
                $locked,
                "Submitted as application #{$application->id}."
            );

            DB::table('audit_logs')->insert([
                'user_id' => $current->id,
                'action_type' => 'SUBMIT',
                'module' => 'Member Application',
                'record_id' => $application->id,
                'details' => "Submitted from draft #{$locked->id}; pending representative review.",
                'performed_at' => now(),
            ]);

            return $application;
        }, 3);
    }

    public function updateMember(User $actor, Member $member, array $input): Member
    {
        return DB::transaction(function () use ($actor, $member, $input) {
            [$association, $current] = $this->lockScope(
                $actor,
                (int) $member->association_id
            );

            $locked = Member::query()->lockForUpdate()->findOrFail($member->id);

            abort_unless(
                (int) $locked->association_id === (int) $association->id,
                403
            );

            $locked->setRelation('association', $association);
            Gate::forUser($current)->authorize('update', $locked);

            // Reuse existing contact-only validation, history protection, and audit.
            return $this->members->update(
                $locked,
                Arr::only($input, ['contact_number']),
                $current->id
            );
        }, 3);
    }

    public function archiveMember(User $actor, Member $member): Member
    {
        return DB::transaction(function () use ($actor, $member) {
            [$association, $current] = $this->lockScope(
                $actor,
                (int) $member->association_id
            );

            $locked = Member::query()->lockForUpdate()->findOrFail($member->id);

            abort_unless(
                (int) $locked->association_id === (int) $association->id,
                403
            );

            $locked->setRelation('association', $association);
            Gate::forUser($current)->authorize('archive', $locked);

            // Existing service also blocks archiving the designated representative.
            return $this->members->archive($locked, $current->id);
        }, 3);
    }

    private function lockScope(User $actor, int $associationId): array
    {
        // Lock the association before the account, matching membership transactions.
        $association = Association::query()->lockForUpdate()->findOrFail($associationId);
        $current = User::with('role')->lockForUpdate()->findOrFail($actor->id);

        abort_unless(
            $current->is_active
            && $current->role?->role_name === 'Field Officer'
            && $current->password === $actor->password
            && (int) $association->field_officer_id === (int) $current->id,
            403
        );

        if ($association->is_archived) {
            throw new MembershipRuleException('Archived associations cannot receive changes.');
        }

        return [$association, $current];
    }

    private function lockDraft(
        User $actor,
        Association $association,
        MemberDraft $draft,
        int $revision,
        string $ability
    ): MemberDraft {
        $locked = MemberDraft::query()->lockForUpdate()->findOrFail($draft->id);

        abort_unless(
            (int) $locked->association_id === (int) $association->id,
            403
        );

        $locked->setRelation('association', $association);
        Gate::forUser($actor)->authorize($ability, $locked);

        if ($locked->revision !== $revision) {
            throw new MembershipRuleException(
                'This draft changed in another request. Reload it before continuing.'
            );
        }

        return $locked;
    }

    private function profile(array $input, bool $complete): array
    {
        $data = MemberProfile::normalize(Arr::only($input, MemberProfile::FIELDS));
        $rules = MemberProfile::rules();

        if (!$complete) {
            foreach ($rules as $field => $fieldRules) {
                // Incomplete values are allowed, but supplied values must be valid.
                $rules[$field] = array_values(array_filter(
                    $fieldRules,
                    fn ($rule) => $rule !== 'required'
                ));

                if (!in_array('nullable', $rules[$field], true)) {
                    array_unshift($rules[$field], 'nullable');
                }

                if (($data[$field] ?? null) === '') {
                    $data[$field] = null;
                }
            }
        }

        return validator($data, $rules)->validate();
    }

    private function audit(
        User $actor,
        string $action,
        MemberDraft $draft,
        string $details
    ): void {
        DB::table('audit_logs')->insert([
            'user_id' => $actor->id,
            'action_type' => $action,
            'module' => 'Member Draft',
            'record_id' => $draft->id,
            'details' => $details,
            'performed_at' => now(),
        ]);
    }
}