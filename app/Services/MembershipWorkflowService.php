<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MembershipRuleException;
use App\Models\Association;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\User;
use App\Support\MemberProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The service owns transitions; controllers own HTTP responses.
 * Lock order is association -> account -> representative/member -> application.
 * Credential provisioning first takes the account-management role lock.
 * Exceptions escape DB::transaction so Laravel rolls back every dependent write.
 */
final class MembershipWorkflowService
{
    public function __construct(private readonly MemberIdentity $identity)
    {
    }

    public function submit(User $actor, array $data): MemberApplication
    {
        return DB::transaction(function () use ($actor, $data): MemberApplication {
            if (!$actor?->is_active || $actor->role?->role_name !== 'Association Member' || !$actor->association_id) {
                throw new MembershipRuleException('Only the association account may submit its applications.');
            }
            $association = Association::query()->lockForUpdate()->findOrFail($actor->association_id);
            $actor = $this->lockAssociationAccount($actor, $association);
            $this->requireCurrentAssociation($association);
            foreach (['members', 'member_applications'] as $table) {
                if ($this->identity->exists($table, $association->id, $data)) {
                    throw new MembershipRuleException('This person already has a member record or application in your association.');
                }
            }

            // Whitelisting prevents mass assignment of status, reviewer or association IDs.
            $application = MemberApplication::create(Arr::only($data, MemberProfile::FIELDS) + [
                'association_id' => $association->id,
                'status_id' => $this->statusId('Pending'),
            ]);
            $this->audit($actor->id, 'SUBMIT', 'Member Application', $application->id, 'Submitted membership application for representative review.');
            return $application;
        }, 3);
    }

    public function review(User $actor, MemberApplication $application, array $data): MemberApplication
    {
        return DB::transaction(function () use ($actor, $application, $data): MemberApplication {
            $association = Association::query()->lockForUpdate()->findOrFail($application->association_id);
            $this->requireCurrentAssociation($association);
            $actor = $this->lockAssociationAccount($actor, $association);

            // Resolve reviewer from the locked association, never from a form selection.
            $representative = Member::query()->whereKey($association->representative_member_id)
                ->where('association_id', $association->id)->where('is_archived', false)->lockForUpdate()->first();
            if (!$representative || !$representative->review_passphrase_hash) {
                throw new MembershipRuleException('An administrator must provision the current representative’s review passphrase first.');
            }
            if (!Hash::check($data['review_passphrase'], $representative->review_passphrase_hash)) {
                throw new MembershipRuleException('The representative review passphrase is incorrect.');
            }

            $locked = MemberApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ((int) $locked->association_id !== (int) $association->id || (int) $locked->status_id !== $this->statusId('Pending')) {
                throw new MembershipRuleException('This application has already been reviewed. Refresh the record to see the decision.');
            }
            $decision = $data['decision'];
            if (!in_array($decision, ['Approved', 'Rejected'], true)) {
                throw new MembershipRuleException('Choose Approve or Reject.');
            }
            $reason = trim($data['rejection_reason'] ?? '');
            if ($decision === 'Rejected' && $reason === '') {
                throw new MembershipRuleException('A rejection reason is required.');
            }

            if ($decision === 'Approved') {
                if ($this->identity->exists('members', $association->id, $locked->toArray())) {
                    throw new MembershipRuleException('This person already exists in the official member register.');
                }
                // A failure in creation OR audit rolls the application back to Pending.
                $member = Member::create(Arr::only($locked->getAttributes(), MemberProfile::FIELDS) + [
                    'association_id' => $association->id, 'application_id' => $locked->id,
                    'date_registered' => now()->toDateString(), 'role_in_assoc' => 'Member', 'is_archived' => false,
                ]);
                $this->audit($actor->id, 'CREATE', 'Member', $member->id, "Created from approved application #{$locked->id}; representative member #{$representative->id}.");
            }

            $locked->forceFill([
                'status_id' => $this->statusId($decision), 'reviewed_by_member_id' => $representative->id,
                'reviewed_at' => now(), 'rejection_reason' => $decision === 'Rejected' ? $reason : null,
            ])->save();
            $this->audit($actor->id, $decision === 'Approved' ? 'APPROVE' : 'REJECT', 'Member Application', $locked->id,
                "{$decision} by representative member #{$representative->id}; private review passphrase verified.");
            return $locked->fresh();
        }, 3);
    }

    public function setReviewPassphrase(User $actor, Member $member, string $passphrase): void
    {
        DB::transaction(function () use ($actor, $member, $passphrase): void {
            // Account updates take this same lock before any association or user lock.
            // It prevents deactivation and password/reassignment changes during provisioning.
            DB::table('roles')->where('role_name', 'System Administrator')->lockForUpdate()->first();
            $association = Association::query()->lockForUpdate()->findOrFail($member->association_id);
            $this->requireCurrentAssociation($association);
            $actor = User::with('role')->lockForUpdate()->find($actor->id);
            if (!$actor?->is_active || $actor->role?->role_name !== 'System Administrator') {
                throw new MembershipRuleException('Only an administrator can provision a review passphrase.');
            }
            $sharedUsers = User::where('association_id', $association->id)->orderBy('id')->lockForUpdate()->get();
            $locked = Member::query()->lockForUpdate()->findOrFail($member->id);
            if ($locked->is_archived || (int) $locked->association_id !== (int) $association->id
                || (int) $association->representative_member_id !== (int) $locked->id) {
                throw new MembershipRuleException('Provision a passphrase only for the current designated representative.');
            }
            // The association login password must not double as its private review credential.
            foreach ($sharedUsers as $sharedUser) {
                if ($sharedUser->password && Hash::check($passphrase, $sharedUser->password)) {
                    throw new MembershipRuleException('Choose a review passphrase different from the association login password.');
                }
            }
            $locked->forceFill(['review_passphrase_hash' => Hash::make($passphrase)])->save();
            $this->audit($actor->id, 'RESET_REVIEW_CREDENTIAL', 'Member', $locked->id, 'Provisioned/reset the designated representative review credential. Secret not recorded.');
        }, 3);
    }

    private function lockAssociationAccount(User $actor, Association $association): User
    {
        // Keep the account locked until the workflow commits. A stale request must
        // fail after reassignment, role change, deactivation, or password reset.
        $current = User::with('role')->lockForUpdate()->find($actor->id);
        if (!$current?->is_active || $current->role?->role_name !== 'Association Member'
            || (int) $current->association_id !== (int) $association->id
            || (int) $actor->association_id !== (int) $association->id
            || $current->password !== $actor->password) {
            throw new MembershipRuleException('The association account changed or is unavailable. Sign in again before continuing.');
        }

        return $current;
    }

    private function requireCurrentAssociation(Association $association): void
    {
        if ($association->is_archived) {
            throw new MembershipRuleException('Archived associations cannot submit or review applications.');
        }
    }

    private function statusId(string $name): int
    {
        $id = DB::table('statuses')->where('status_name', $name)->value('id');
        if (!$id) {
            throw new MembershipRuleException('Membership statuses are not configured. Contact an administrator.');
        }
        return (int) $id;
    }

    private function audit(int $actorId, string $action, string $module, int $id, string $details): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $actorId, 'action_type' => $action, 'module' => $module,
            'record_id' => $id, 'details' => $details, 'performed_at' => now(),
        ]);
    }
}
