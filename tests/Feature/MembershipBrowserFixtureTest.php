<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Services\MembershipWorkflowService;
use Tests\Support\MembershipDatabaseTestCase;

/** Export synthetic, authorized HTML for browser QA without creating real user records. */
final class MembershipBrowserFixtureTest extends MembershipDatabaseTestCase
{
    public function test_render_membership_screens_for_browser_review(): void
    {
        $workflow = app(MembershipWorkflowService::class);
        $workflow->setReviewPassphrase(User::findOrFail(1), Member::findOrFail(1), 'fixture private review phrase');
        $application = $workflow->submit(User::findOrFail(3), [
            'first_name' => 'Sample Applicant', 'last_name' => 'For Browser Review', 'birthday' => '1990-02-03', 'sex_id' => 1,
        ]);
        $screens = [
            'register' => '/membership', 'submission' => '/membership/applications/create',
            'review' => '/membership/applications/'.$application->id, 'member' => '/membership/members/1',
        ];
        foreach ($screens as $name => $url) {
            $response = $this->withSession($this->sessionFor(3, 'Association Member'))->get($url)->assertOk();
            $this->export($name, $response->getContent());
        }
        $response = $this->withSession($this->sessionFor(1, 'System Administrator'))->get('/admin/members/1')->assertOk();
        $this->export('credential', $response->getContent());
        $this->from('/admin/members')->put('/admin/members/1', [
            'first_name' => 'Preserved Correction', 'last_name' => 'One', 'birthday' => '1980-01-01',
            'sex_id' => 1, 'date_registered' => '2020-01-01', 'contact_number' => 'invalid',
        ])->assertSessionHasErrors('contact_number');
        $response = $this->get('/admin/members')->assertOk();
        $this->export('edit-recovery', $response->getContent());
    }

    private function export(string $name, string $html): void
    {
        if (getenv('ASSOCMAP_EXPORT_BROWSER_FIXTURES') !== '1') {
            return;
        }
        $directory = base_path('../Capstone-AssocMap/membership-qa');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($directory.'/'.$name.'.html', $html);
    }
}
