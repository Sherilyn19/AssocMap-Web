<?php

use App\Exceptions\MembershipRuleException;
use App\Models\Association;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\User;
use App\Services\FoundingMemberService;
use App\Services\MembershipWorkflowService;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema = (string) getenv('ASSOCMAP_MEMBERSHIP_RACE_SCHEMA');
if (!preg_match('/^assocmap_test_membership_[a-f0-9]{16}$/D', $schema)) {
    exit(2);
}
config(['database.default' => 'pgsql', 'database.connections.pgsql.search_path' => $schema]);
DB::purge('pgsql');
DB::statement("SET statement_timeout = '20000ms'");
$actor = User::with('role')->findOrFail(3);
echo 'PID:'.DB::selectOne('SELECT pg_backend_pid() AS pid')->pid.PHP_EOL;
flush();
$profile = ['first_name' => 'Concurrent', 'last_name' => 'Applicant', 'birthday' => '1990-02-03', 'sex_id' => 1];
try {
    $service = app(MembershipWorkflowService::class);
    match ($argv[1] ?? '') {
        // Match the credentials used by the main concurrency test.
        'submit' => $service->submit($actor, $profile + [
            'review_passphrase' => 'Race-private-secret',
        ]),
        'review' => $service->review(User::findOrFail(2), MemberApplication::findOrFail(1), ['decision' => 'Approved', 'review_passphrase' => 'Race-private-secret']),
        'credential' => $service->setReviewPassphrase(User::findOrFail(1), Member::findOrFail(1), 'Race-private-secret'),
        'founding' => app(FoundingMemberService::class)->create(User::findOrFail(1), Association::findOrFail(3), $profile + ['justification' => 'Verified founding record', 'profile_verified' => true]),
        default => throw new RuntimeException('Unsupported test operation'),
    };
    echo 'RESULT:accepted'.PHP_EOL;
} catch (MembershipRuleException $error) {
    echo 'RESULT:rejected'.PHP_EOL;
} catch (Throwable $error) {
    echo 'RESULT:error:'.get_class($error).':'.$error->getMessage().PHP_EOL;
    exit(1);
}
