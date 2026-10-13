<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AdminDashboardService;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssociationDatabaseTestCase;

class AdminDashboardTest extends AssociationDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('ALTER TABLE projects ADD COLUMN status_id bigint REFERENCES statuses(id)');
        DB::table('statuses')->insert([
            ['id' => 20, 'status_name' => 'Planned'],
            ['id' => 21, 'status_name' => 'Ongoing'],
            ['id' => 22, 'status_name' => 'Completed'],
        ]);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
    }

    public function test_dashboard_counts_match_register_scopes_and_keep_unknown_project_statuses(): void
    {
        DB::table('associations')->where('id', 2)->update(['is_archived' => true]);
        DB::table('members')->where('id', 1)->update(['is_archived' => true]);
        DB::table('projects')->insert([
            ['association_id' => 1, 'status_id' => 20, 'is_archived' => false],
            ['association_id' => 1, 'status_id' => 21, 'is_archived' => false],
            ['association_id' => 2, 'status_id' => 22, 'is_archived' => false],
            ['association_id' => 1, 'status_id' => null, 'is_archived' => false],
            ['association_id' => 1, 'status_id' => 21, 'is_archived' => true],
        ]);
        $this->application(1, 1, '2026-01-01');
        $this->application(2, 1, '2026-01-02');
        $this->application(1, 2, '2026-01-03');

        $data = app(AdminDashboardService::class)->overview();
        $this->assertSame(['associations' => 1, 'members' => 1, 'projects' => 4, 'pending' => 2], $data['counts']);
        $this->assertSame(['Planned' => 1, 'Ongoing' => 1, 'Completed' => 1, 'Other / unspecified' => 1], $data['projectStatuses']);
        $this->assertCount(1, $data['recentAssociations']);
        $this->assertSame(1, (int) $data['coverage']->first()->total);
        $this->get(route('dashboard.admin'))->assertOk()
            ->assertSee('data-admin-dashboard', false)->assertSee('Archived association')
            ->assertSee(route('members.applications.index', ['status_id' => 1, 'sort' => 'submitted_asc']))
            ->assertSee(route('projects.create'))->assertDontSee('Available in Capstone 2');
    }

    public function test_lists_are_bounded_sorted_and_escape_record_names(): void
    {
        DB::table('associations')->where('id', 1)->update(['name' => '<script>alert(1)</script>']);
        for ($i = 1; $i <= 7; $i++) {
            $this->application(1, 1, '2026-01-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }
        $data = app(AdminDashboardService::class)->overview();
        $this->assertCount(5, $data['pendingApplications']);
        $this->assertSame([1, 2, 3, 4, 5], $data['pendingApplications']->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(7, $data['counts']['pending']);
        $this->get(route('dashboard.admin'))->assertOk()->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_empty_registers_render_without_division_by_zero(): void
    {
        DB::table('associations')->update(['is_archived' => true]);
        DB::table('members')->update(['is_archived' => true]);
        $this->get(route('dashboard.admin'))->assertOk()->assertSee('No current associations yet.')
            ->assertSee('No pending applications to display.')
            ->assertSee('No current projects to display.')
            ->assertSee('No association coverage to display yet.');
    }

    public function test_database_failure_shows_retry_state_instead_of_false_zero_counts(): void
    {
        DB::statement('ALTER TABLE projects RENAME TO unavailable_projects');
        $this->get(route('dashboard.admin'))->assertStatus(503)
            ->assertSee('Dashboard data is temporarily unavailable')->assertSee('Refresh overview')
            ->assertDontSee('Current associations')->assertDontSee('SQLSTATE');
    }

    public function test_non_admin_and_deactivated_accounts_cannot_read_dashboard_data(): void
    {
        $this->withSession($this->sessionFor(2, 'System Administrator'))
            ->get(route('dashboard.admin'))->assertRedirect(route('dashboard.officer'));
        $this->withSession($this->sessionFor(3, 'System Administrator'))
            ->get(route('dashboard.admin'))->assertRedirect(route('dashboard.member'));
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->withSession($this->sessionFor(1, 'System Administrator'))
            ->get(route('dashboard.admin'))->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->flushSession();
        $this->get(route('dashboard.admin'))->assertRedirect(route('login'));
    }

    private function application(int $associationId, int $statusId, string $createdAt): void
    {
        DB::table('member_applications')->insert([
            'association_id' => $associationId, 'first_name' => 'Applicant '.$createdAt, 'last_name' => 'Example',
            'birthday' => '1990-01-01', 'status_id' => $statusId, 'created_at' => $createdAt,
        ]);
    }
}
