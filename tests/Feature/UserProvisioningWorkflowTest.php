<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\User;
use App\Services\AdminUserManagementService;
use App\Services\MembershipWorkflowService;
use Illuminate\Support\Facades\DB;
use Tests\Support\MembershipDatabaseTestCase;

class UserProvisioningWorkflowTest extends MembershipDatabaseTestCase
{
    public function test_created_shared_account_can_log_in_submit_and_review_only_its_association(): void
    {
        $password = 'New-Shared-Login-2026';
        $this->withSession($this->sessionFor(1, 'System Administrator'))->post('/admin/users', [
            'name' => 'New Association Account', 'email' => 'provisioned@example.test', 'password' => $password, 'role_id' => 3, 'association_id' => 1,
        ])->assertSessionHas('success');
        $id = DB::table('users')->where('email', 'provisioned@example.test')->value('id');
        $this->post('/logout');
        $this->post('/login', ['email' => 'provisioned@example.test', 'password' => $password])->assertRedirect('/member/dashboard');
        $this->get('/membership')->assertOk()->assertSee('Representative One')->assertDontSee('Other Person');
        $this->get('/membership/members/2')->assertForbidden();
        $this->post('/membership/applications', ['first_name' => 'New', 'last_name' => 'Applicant', 'birthday' => '1990-01-01', 'sex_id' => 1])->assertSessionHas('success');
        $application = MemberApplication::firstOrFail();
        $this->assertSame(1, (int) $application->association_id);
        app(MembershipWorkflowService::class)->setReviewPassphrase(User::findOrFail(1), Member::findOrFail(1), 'Separate-Review-Secret');
        $this->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Approved', 'review_passphrase' => $password])->assertSessionHas('error');
        $this->assertSame('Pending', $application->fresh()->status->status_name);
        $this->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Approved', 'review_passphrase' => 'Separate-Review-Secret'])->assertSessionHas('success');
        $this->assertSame(1, (int) $application->fresh()->reviewed_by_member_id);

        // Relinking changes record scope on the next request, including an already signed-in session.
        app(AdminUserManagementService::class)->update($id, ['name' => 'Moved Account', 'email' => 'provisioned@example.test', 'role_id' => 3, 'association_id' => 2], 1);
        $this->get('/membership')->assertOk()->assertSee('Other Person')->assertDontSee('Representative One');
        $this->get('/membership/applications/'.$application->id)->assertForbidden();
        $this->patch('/membership/applications/'.$application->id.'/review', ['decision' => 'Rejected', 'review_passphrase' => 'Separate-Review-Secret', 'rejection_reason' => 'Forbidden'])->assertForbidden();
        $this->assertSame('Approved', $application->fresh()->status->status_name);
    }
}
