<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Association;
use App\Services\AssociationManagementService;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssociationDatabaseTestCase;

/** Real card queries run only inside the existing rollback-only PostgreSQL fixture. */
final class AssociationCardsTest extends AssociationDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(<<<'SQL'
            ALTER TABLE projects ADD COLUMN title varchar, ADD COLUMN implementation_date date;
            ALTER TABLE trainings ADD COLUMN title varchar, ADD COLUMN venue varchar, ADD COLUMN date_conducted date;
            ALTER TABLE gis_locations ADD COLUMN location_name varchar, ADD COLUMN latitude numeric, ADD COLUMN longitude numeric;
            UPDATE gis_locations SET location_name='Published pond', latitude=10.1, longitude=122.1;
            INSERT INTO projects (association_id,title,is_archived) VALUES (1,'Current livelihood',false),(1,'Archived livelihood',true),(2,'Other project',false);
            INSERT INTO trainings (association_id,title,venue,is_archived) VALUES (1,'Current training','Municipal hall',false),(1,'Archived training','Old hall',true),(2,'Other training','Other hall',false);
            INSERT INTO gis_locations (association_id,location_name,is_published) VALUES (1,'Unpublished pond',false),(2,'Other pond',true);
            INSERT INTO member_applications (association_id,first_name,last_name,birthday,status_id) VALUES (1,'Pending','Applicant','1990-01-01',1),(1,'Approved','Applicant','1990-01-01',2),(2,'Other','Applicant','1990-01-01',1);
            INSERT INTO members (association_id,first_name,last_name,birthday,date_registered,is_archived) VALUES (1,'Archived','Member','1990-01-01','2020-01-01',true);
            UPDATE members SET contact_number='PRIVATE-CONTACT', review_passphrase_hash='PRIVATE-HASH' WHERE id=1;
        SQL);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
    }

    public function test_register_cards_match_global_counts_and_ignore_list_filters(): void
    {
        DB::table('associations')->where('id', 2)->update(['status_id' => 5]);
        app(AssociationManagementService::class)->create($this->payload(['name' => 'Archived Active']), 1);
        DB::table('associations')->where('name', 'Archived Active')->update(['is_archived' => true]);
        $service = app(AssociationManagementService::class);
        $this->assertSame(['total' => 3, 'active' => 1, 'inactive' => 1, 'archived' => 1], $service->summary());
        foreach ($service->summary() as $key => $count) {
            $this->assertSame($count, $service->summaryRecords($key)->total());
        }
        $response = $this->get('/admin/associations?search=NoMatch&summary=active&per_page=15')->assertOk();
        $response->assertSee('No associations match these filters.')->assertSee('Assigned Association');
        $response->assertDontSee('Archived Active')->assertDontSee('Other Association');
        foreach (array_keys(AssociationManagementService::REGISTER_CARDS) as $key) {
            $response->assertSee('id="association-card-'.$key.'"', false);
        }
        $response->assertSee('search=NoMatch&amp;per_page=15', false);
    }

    public function test_all_six_detail_cards_match_counts_and_never_leak_other_records_or_private_fields(): void
    {
        $service = app(AssociationManagementService::class);
        $association = $service->findDetailed(Association::findOrFail(1));
        $counts = ['members' => 'members_count', 'applications' => 'pending_applications_count', 'projects' => 'projects_count', 'trainings' => 'trainings_count', 'gis' => 'gis_locations_count', 'published_gis' => 'published_gis_locations_count'];
        foreach ($counts as $key => $attribute) {
            $records = $service->relatedRecords($association, $key);
            $this->assertSame((int) $association->$attribute, $records->total(), $key);
            $json = $records->toJson();
            foreach (['PRIVATE-CONTACT', 'PRIVATE-HASH', 'Other project', 'Other training', 'Other pond', 'Archived livelihood', 'Archived training', 'Archived Member'] as $private) {
                $this->assertStringNotContainsString($private, $json);
            }
            $response = $this->get('/admin/associations/1?related='.$key)->assertOk();
            $response->assertSee('data-association-card-details="'.$key.'"', false)->assertDontSee('PRIVATE-CONTACT')->assertDontSee('PRIVATE-HASH');
        }
        $this->get('/admin/associations/1?related=published_gis')->assertOk()->assertSee('Published pond')->assertDontSee('Unpublished pond');
        $this->get('/admin/associations/1?related=applications')->assertOk()->assertSee('Pending Applicant')->assertDontSee('Approved Applicant');
    }

    public function test_card_pagination_preserves_main_list_position_and_detail_scope(): void
    {
        DB::unprepared(<<<'SQL'
            INSERT INTO associations (name,area_unit_id,sub_unit_id,program_component_id,field_officer_id,status_id,address,date_joined)
            SELECT 'Record ' || n,1,1,1,2,4,'Fixture address','2020-01-01' FROM generate_series(1,22) n;
            INSERT INTO members (association_id,first_name,last_name,birthday,date_registered)
            SELECT 1,'Fixture', 'Member ' || n,'1990-01-01','2020-01-01' FROM generate_series(1,12) n;
        SQL);
        $this->get('/admin/associations?search=Record&per_page=10&page=2&summary=total&summary_page=2')
            ->assertOk()->assertSee('Showing 11–20 of 22 records')->assertSee('Showing 11–20 of 24 records')
            ->assertSee('summary_page=3', false)->assertSee('page=2', false);
        $this->get('/admin/associations/1?search=Record&page=2&related=members&related_page=2')
            ->assertOk()->assertSee('Showing 11–13 of 13 records')->assertSee('related_page=1', false)
            ->assertSee('search=Record&amp;page=2', false)->assertDontSee('Other Person');
    }

    public function test_card_inputs_and_authorization_are_checked_before_loading_records(): void
    {
        $this->getJson('/admin/associations?summary=users')->assertUnprocessable();
        $this->getJson('/admin/associations?per_page=100000')->assertUnprocessable();
        $this->get('/admin/associations/1?related[]=members')->assertRedirect(route('admin.associations.show', 1));
        $this->getJson('/admin/associations/1?related=members&related_page=-1')->assertUnprocessable();
        $this->withSession($this->sessionFor(2, 'System Administrator'))
            ->get('/admin/associations/1?related=members')->assertRedirect(route('dashboard.officer'));
        $this->withSession($this->sessionFor(3, 'Association Member'))
            ->get('/admin/associations?summary=total')->assertRedirect(route('dashboard.member'));
    }

    public function test_card_database_failure_is_a_safe_error_not_a_successful_empty_list(): void
    {
        $armed = true;
        DB::connection()->beforeExecuting(function ($query) use (&$armed) {
            if ($armed && str_contains($query, 'from "trainings"') && ! str_contains($query, '"associations"')) {
                $armed = false;
                throw new \PDOException('PRIVATE database endpoint');
            }
        });
        $this->getJson('/admin/associations/1?related=trainings')->assertStatus(503)
            ->assertDontSee('PRIVATE database endpoint')->assertDontSee('No records match');
    }

    public function test_mobile_actions_and_optional_browser_fixtures_use_only_synthetic_data(): void
    {
        DB::table('associations')->where('id', 2)->update(['is_archived' => true]);
        $pages = [
            'index' => '/admin/associations?archive_state=all',
            'summary' => '/admin/associations?archive_state=all&summary=total',
            'detail' => '/admin/associations/1',
            'related' => '/admin/associations/1?related=gis',
        ];
        foreach ($pages as $name => $url) {
            $response = $this->get($url)->assertOk();
            if ($name === 'index') {
                $this->assertSame(2, substr_count($response->getContent(), 'data-edit-association='));
                $this->assertSame(2, substr_count($response->getContent(), 'data-confirm-action="archive"'));
                $this->assertSame(2, substr_count($response->getContent(), 'data-confirm-action="restore"'));
                $response->assertSee('data-association-confirm', false);
            }
            if (getenv('ASSOCMAP_EXPORT_CARD_FIXTURES') === '1') {
                // Opt-in local browser fixtures contain synthetic records only.
                // No credentials, sessions, or real application database rows are exported.
                $directory = storage_path('framework/testing/association-cards');
                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
                $assets = '<script type="module" src="/build/'.$manifest['resources/js/app.js']['file'].'"></script>';
                $assets .= '<link rel="stylesheet" href="/build/'.$manifest['resources/css/app.css']['file'].'">';
                $html = str_replace('</head>', $assets.'</head>', $response->getContent());
                // Edit payload URLs are JSON-escaped, unlike normal href/action URLs.
                // Rewrite both so no preview control can reach the running app.
                $html = str_replace([url('/'), str_replace('/', '\\/', url('/'))], ['http://127.0.0.1:8097', 'http:\\/\\/127.0.0.1:8097'], $html);
                $html = preg_replace('/(name="_token" value=")[^"]+/', '$1synthetic-preview-token', $html);
                file_put_contents($directory.'/'.$name.'.html', $html);
            }
        }
    }
}
