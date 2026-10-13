<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\MembershipRuleException;
use App\Models\Association;
use App\Models\Member;
use App\Models\User;
use App\Services\FoundingMemberService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\MembershipDatabaseTestCase;

final class FoundingMemberTest extends MembershipDatabaseTestCase
{
    private function emptyAssociation(): Association
    {
        return Association::create(['name' => 'Founding Association', 'is_archived' => false]);
    }

    private function profile(): array
    {
        return ['first_name' => 'Founding', 'last_name' => 'Person', 'birthday' => '1980-01-01', 'sex_id' => 1,
            'justification' => 'Verified founding register from the association.', 'profile_verified' => '1'];
    }

    public function test_admin_can_register_one_verified_founder_without_approving_an_application(): void
    {
        $association = $this->emptyAssociation();
        $url = route('admin.founding-member.store', $association);
        $this->withSession($this->sessionFor(1, 'System Administrator'))
            ->get(route('admin.founding-member.create', $association))->assertOk()->assertSee('Register First Official Member');
        $this->post($url, $this->profile())->assertRedirect(route('admin.associations.show', $association))->assertSessionHas('success');
        $member = $association->members()->sole();
        $this->assertNull($member->application_id);
        $this->assertNull($member->user_id);
        $this->assertNull($member->review_passphrase_hash);
        $this->assertNull($association->fresh()->representative_member_id);
        $this->assertSame(0, DB::table('member_applications')->count());
        $audit = DB::table('audit_logs')->where('action_type', 'CREATE_FOUNDING_MEMBER')->sole();
        $this->assertSame((int) $member->id, (int) $audit->record_id);
        $this->assertStringContainsString('Verified founding register', $audit->details);
        $this->post($url, $this->profile())->assertSessionHas('error');
        $this->assertSame(1, $association->members()->count());
    }

    public function test_existing_or_archived_members_and_archived_associations_cannot_bootstrap(): void
    {
        $service = app(FoundingMemberService::class);
        $admin = User::findOrFail(1);
        foreach ([false, true] as $archived) {
            DB::table('members')->where('association_id', 1)->update(['is_archived' => $archived]);
            DB::table('associations')->where('id', 1)->update(['representative_member_id' => null]);
            try {
                $service->create($admin, Association::findOrFail(1), $this->profile());
                $this->fail('Existing membership must block the exception.');
            } catch (MembershipRuleException $error) {
                $this->assertSame(1, Member::where('association_id', 1)->count());
            }
        }
        $association = $this->emptyAssociation();
        $association->update(['is_archived' => true]);
        $this->expectException(MembershipRuleException::class);
        $service->create($admin, $association, $this->profile());
    }

    public function test_verified_profile_and_justification_are_required_and_forged_fields_rejected(): void
    {
        $association = $this->emptyAssociation();
        $this->withSession($this->sessionFor(1, 'System Administrator'))
            ->post(route('admin.founding-member.store', $association), array_replace($this->profile(), [
                'justification' => ' ', 'profile_verified' => false, 'association_id' => 2, 'application_id' => 9,
            ]))->assertSessionHasErrors(['justification', 'profile_verified', 'association_id', 'application_id']);
        $this->assertSame(0, $association->members()->count());
    }

    public function test_member_officer_and_revoked_administrator_cannot_register_founder(): void
    {
        $association = $this->emptyAssociation();
        foreach ([2 => 'Field Officer', 3 => 'Association Member'] as $id => $role) {
            $this->withSession($this->sessionFor($id, $role))->post(route('admin.founding-member.store', $association), $this->profile())
                ->assertRedirect($id === 2 ? '/officer/dashboard' : '/member/dashboard');
            try {
                app(FoundingMemberService::class)->create(User::findOrFail($id), $association, $this->profile());
                $this->fail('Only a current administrator may register the founder.');
            } catch (AuthorizationException $error) {
                $this->assertSame(0, $association->members()->count());
            }
        }
        $admin = User::findOrFail(1);
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->expectException(AuthorizationException::class);
        app(FoundingMemberService::class)->create($admin, $association, $this->profile());
    }

    public function test_audit_failure_rolls_back_founding_member(): void
    {
        $association = $this->emptyAssociation();
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_founder_audit CHECK (action_type <> 'CREATE_FOUNDING_MEMBER')");
        try {
            app(FoundingMemberService::class)->create(User::findOrFail(1), $association, $this->profile());
            $this->fail('An audit failure must roll back the founding record.');
        } catch (QueryException $error) {
            $this->assertSame(0, $association->members()->count());
        }
    }
}
