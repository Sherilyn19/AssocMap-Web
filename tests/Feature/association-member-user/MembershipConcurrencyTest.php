<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Association;
use App\Models\Member;
use App\Models\User;
use App\Services\AdminUserManagementService;
use App\Services\AssociationManagementService;
use App\Services\FoundingMemberService;
use App\Services\MembershipWorkflowService;
use App\Services\FieldOfficerMembershipService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\MembershipDatabaseTestCase;

final class MembershipConcurrencyTest extends MembershipDatabaseTestCase
{
    public static function scenarios(): array
    {
        return array_map(fn ($operation) => [$operation], [
            'duplicate-submit', 'duplicate-review', 'deactivate-submit', 'reassign-submit',
            'deactivate-review', 'reassign-review', 'password-credential', 'duplicate-founding',
        ]);
    }

    #[DataProvider('scenarios')]
    public function test_competing_workflow_waits_then_revalidates(string $operation): void
    {
        config(['association.operation_timeout_ms' => 20000]);
        $service = app(MembershipWorkflowService::class);
        $actor = User::with('role')->findOrFail(3);
        $profile = ['first_name' => 'Concurrent', 'last_name' => 'Applicant', 'birthday' => '1990-02-03', 'sex_id' => 1];
        $application = null;

        // Registration and review scenarios need a configured representative.
        // The password/credential scenario deliberately starts without that secret.
        if (in_array($operation, [
            'duplicate-submit',
            'deactivate-submit',
            'reassign-submit',
            'duplicate-review',
            'deactivate-review',
            'reassign-review',
        ], true)) {
            $service->setReviewPassphrase(
                User::findOrFail(1),
                Member::findOrFail(1),
                'Race-private-secret'
            );
        }

        if (in_array($operation, [
            'duplicate-review',
            'deactivate-review',
            'reassign-review',
        ], true)) {
            // Review races must start with a Pending FO application.
            $officer = User::findOrFail(2);
            $draftWorkflow = app(FieldOfficerMembershipService::class);

            $draft = $draftWorkflow->create($officer, 1, $profile);

            $application = $draftWorkflow->submit(
                $officer,
                $draft,
                $draft->revision
            );
        }
        if ($operation === 'duplicate-founding') {
            DB::table('associations')->insert(['name' => 'Empty Association']);
        }
        // Commit only the random test schema so the competing connection can see it.
        DB::commit();
        $worker = null;
        try {
            DB::beginTransaction();
            $accounts = app(AdminUserManagementService::class);
            $workerOperation = 'submit';
            if ($operation === 'duplicate-submit') {
            // The first verified registration succeeds; the competing one must fail.
            $service->submit($actor, $profile + [
                'review_passphrase' => 'Race-private-secret',
            ]);
            } elseif ($operation === 'duplicate-review') {
                $service->review(User::findOrFail(2), $application, ['decision' => 'Approved', 'review_passphrase' => 'Race-private-secret']);
                $workerOperation = 'review';
            } elseif (in_array($operation, ['deactivate-submit', 'deactivate-review'], true)) {
                $accounts->setActive($operation === 'deactivate-review' ? 2 : 3, false, 1);
                $workerOperation = $operation === 'deactivate-review' ? 'review' : 'submit';
            } elseif ($operation === 'reassign-submit') {
                $accounts->update(3, ['name' => $actor->name, 'email' => $actor->email, 'role_id' => 3, 'association_id' => 2], 1);
            } elseif ($operation === 'reassign-review') {
                DB::table('associations')->where('id', 1)->update(['field_officer_id' => null]);
                $workerOperation = 'review';
            } elseif ($operation === 'password-credential') {
                $accounts->update(3, ['name' => $actor->name, 'email' => $actor->email, 'role_id' => 3, 'association_id' => 1, 'password' => 'Race-private-secret'], 1);
                $workerOperation = 'credential';
            } else {
                app(FoundingMemberService::class)->create(User::findOrFail(1), Association::findOrFail(3), $profile + ['justification' => 'Verified founding record', 'profile_verified' => true]);
                $workerOperation = 'founding';
            }
            $worker = new Process([PHP_BINARY, base_path('tests/Support/membership-race-worker.php'), $workerOperation], base_path(), [
                'ASSOCMAP_MEMBERSHIP_RACE_SCHEMA' => $this->schema,
            ]);
            $worker->setTimeout(35);
            $worker->start();
            $blocked = false;
            $deadline = microtime(true) + 12;
            while ($worker->isRunning() && microtime(true) < $deadline) {
                if (preg_match('/PID:(\d+)/', $worker->getOutput(), $match)) {
                    $blocked = (bool) DB::selectOne('SELECT cardinality(pg_blocking_pids(?)) > 0 AS blocked', [(int) $match[1]])->blocked;
                    if ($blocked) {
                        break;
                    }
                }
                usleep(100000);
            }
            $this->assertTrue($blocked, 'The competing transaction must actually wait. '.$worker->getOutput());
            DB::commit();
            $worker->wait();
            $this->assertSame(0, $worker->getExitCode(), $worker->getOutput().$worker->getErrorOutput());
            $this->assertStringContainsString('RESULT:rejected', $worker->getOutput());
            $this->assertSame(in_array($operation, ['duplicate-submit', 'duplicate-review', 'deactivate-review', 'reassign-review'], true) ? 1 : 0, DB::table('member_applications')->count());
            // Duplicate submission must leave one pending application and no official member.
            // Only the successful approval scenario creates a member.
            $this->assertSame(
                $operation === 'duplicate-review' ? 1 : 0,
                Member::whereNotNull('application_id')->count()
            );
            // The competing request must never create a second member.
            $this->assertSame(
                $operation === 'duplicate-review'
                    ? 1
                    : 0,
                Member::whereNotNull('application_id')->count()
            );
            if ($operation === 'duplicate-founding') {
                $this->assertSame(1, Member::where('association_id', 3)->count());
                $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'CREATE_FOUNDING_MEMBER')->count());
            }
        } finally {
            $worker?->stop();
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            // Cleanup is restricted to the exact generated fixture schema.
            if (preg_match('/^assocmap_test_membership_[a-f0-9]{16}$/D', $this->schema)) {
                DB::statement('DROP SCHEMA "'.$this->schema.'" CASCADE');
            }
        }
    }
}
