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

    public function test_field_officer_member_permissions_follow_assignment_and_record_state(): void
    {
        $officer = $this->userWithRole('Field Officer', 9);
        $policy = new MemberPolicy();

        $association = (new Association())->forceFill([
            'id' => 5,
            'field_officer_id' => 9,
            'representative_member_id' => 21,
            'is_archived' => false,
        ]);

        $member = (new Member())->forceFill([
            'id' => 20,
            'association_id' => 5,
            'is_archived' => false,
        ]);

        $member->setRelation('association', $association);

        // A current, assigned ordinary member can be viewed and managed.
        // The service separately restricts profile editing to contact number.
        $this->assertTrue($policy->view($officer, $member));
        $this->assertTrue($policy->update($officer, $member));
        $this->assertTrue($policy->archive($officer, $member));

        // Losing the assignment removes all officer access.
        $association->field_officer_id = 10;

        $this->assertFalse($policy->view($officer, $member));
        $this->assertFalse($policy->update($officer, $member));
        $this->assertFalse($policy->archive($officer, $member));

        // Archived members remain viewable within the current assignment.
        $association->field_officer_id = 9;
        $member->is_archived = true;

        $this->assertTrue($policy->view($officer, $member));
        $this->assertFalse($policy->update($officer, $member));
        $this->assertFalse($policy->archive($officer, $member));

        // The designated representative may receive contact corrections,
        // but cannot be archived while holding that designation.
        $member->is_archived = false;
        $association->representative_member_id = 20;

        $this->assertTrue($policy->update($officer, $member));
        $this->assertFalse($policy->archive($officer, $member));

        // An archived association prevents normal member changes.
        $association->representative_member_id = 21;
        $association->is_archived = true;

        $this->assertFalse($policy->update($officer, $member));
        $this->assertFalse($policy->archive($officer, $member));
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