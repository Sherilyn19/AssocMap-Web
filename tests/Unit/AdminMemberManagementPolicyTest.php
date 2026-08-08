<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Association;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\Role;
use App\Models\User;
use App\Policies\MemberApplicationPolicy;
use App\Policies\MemberPolicy;
use Tests\TestCase;

final class AdminMemberManagementPolicyTest extends TestCase
{
    public function test_system_administrator_can_view_update_and_archive_members(): void
    {
        $admin = $this->userWithRole('System Administrator', 1);
        $member = (new Member())->forceFill([
            'id' => 20,
            'association_id' => 5,
            'is_archived' => false,
        ]);

        $policy = new MemberPolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $member));
        $this->assertTrue($policy->update($admin, $member));
        $this->assertTrue($policy->archive($admin, $member));
    }

    public function test_field_officer_can_only_view_members_from_assigned_association(): void
    {
        $officer = $this->userWithRole('Field Officer', 9);

        $assignedAssociation = (new Association())->forceFill([
            'id' => 5,
            'field_officer_id' => 9,
        ]);

        $otherAssociation = (new Association())->forceFill([
            'id' => 6,
            'field_officer_id' => 10,
        ]);

        $assignedMember = (new Member())->forceFill(['association_id' => 5]);
        $assignedMember->setRelation('association', $assignedAssociation);

        $otherMember = (new Member())->forceFill(['association_id' => 6]);
        $otherMember->setRelation('association', $otherAssociation);

        $policy = new MemberPolicy();

        $this->assertTrue($policy->view($officer, $assignedMember));
        $this->assertFalse($policy->view($officer, $otherMember));
        $this->assertFalse($policy->update($officer, $assignedMember));
        $this->assertFalse($policy->archive($officer, $assignedMember));
    }

    public function test_admin_can_inspect_applications_but_policy_defines_no_approval_action(): void
    {
        $admin = $this->userWithRole('System Administrator', 1);
        $application = (new MemberApplication())->forceFill([
            'id' => 50,
            'association_id' => 5,
        ]);

        $policy = new MemberApplicationPolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $application));
        $this->assertFalse(method_exists($policy, 'approve'));
        $this->assertFalse(method_exists($policy, 'reject'));
    }

    private function userWithRole(string $roleName, int $id): User
    {
        $role = (new Role())->forceFill([
            'id' => 1,
            'role_name' => $roleName,
        ]);

        $user = (new User())->forceFill([
            'id' => $id,
            'is_active' => true,
        ]);
        $user->setRelation('role', $role);

        return $user;
    }
}