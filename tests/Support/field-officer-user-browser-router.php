<?php

// Read-only visual fixtures use real Blade views without database access or login bypasses.
// Run only with PHP's loopback development server; never include this in application routes.
if (PHP_SAPI !== 'cli-server' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$asset = realpath($root.'/public/'.$path);
if ($asset && str_starts_with($asset, realpath($root.'/public').DIRECTORY_SEPARATOR) && is_file($asset) && pathinfo($asset, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Visual preview only. No records were changed.');
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8129');
app(Illuminate\Foundation\Vite::class)->useHotFile(storage_path('app/officer-preview-no-hot'));
$state = $_GET['fixture'] ?? 'normal';
$long = $state === 'long';
$empty = $state === 'empty';
$name = $long ? str_repeat('Coastal Livelihood Association With A Long Name ', 6) : 'Synthetic Coastal Association';
$officerName = $long ? str_repeat('Field Officer Long Name ', 6) : 'Sample Field Officer';
$role = match ($_GET['role'] ?? '') { 'admin' => 'System Administrator', 'member' => 'Association Member', default => 'Field Officer' };
session(['auth_user' => ['id' => 1, 'name' => $officerName, 'role_name' => $role]]);
$errors = new Illuminate\Support\ViewErrorBag;
if ($state === 'errors') {
    $errors->put('default', new Illuminate\Support\MessageBag(['venue' => 'Enter a venue. '.str_repeat('Review the submitted information. ', 6)]));
}
view()->share('errors', $errors);
try {
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
} catch (Throwable) {
    http_response_code(404);
    exit('Choose an existing Field Officer page.');
}
$make = function (string $class, array $attributes) {
    $model = new $class;
    $model->forceFill($attributes);
    return $model;
};
$status = $make(App\Models\Status::class, ['id' => 1, 'status_name' => 'Active']);
$component = $make(App\Models\ProgramComponent::class, ['id' => 1, 'name' => 'Aquaculture']);
$area = $make(App\Models\AreaUnit::class, ['id' => 1, 'name' => 'Sample Municipality']);
$officer = $make(App\Models\User::class, ['id' => 1, 'name' => $officerName, 'email' => 'officer@example.test']);
$association = $make(App\Models\Association::class, ['id' => 1, 'name' => $name, 'is_archived' => false, 'members_count' => 1, 'address' => $long ? str_repeat('A long coastal address ', 12) : 'Sample address']);
$association->setRelations(['areaUnit' => $area, 'subUnit' => null, 'programComponent' => $component, 'status' => $status, 'fieldOfficer' => $officer]);
$material = $make(App\Models\ProjectMaterial::class, ['id' => 1, 'item_name' => 'Fishing net', 'quantity' => 2, 'unit' => 'sets', 'unit_cost' => 1500, 'delivery_date' => '2025-01-01']);
$material->setRelation('status', $make(App\Models\Status::class, ['status_name' => 'Good']));
$project = $make(App\Models\Project::class, ['id' => 1, 'association_id' => 1, 'title' => $long ? str_repeat('Long livelihood project title ', 8) : 'Sample livelihood project', 'commodity_type' => 'Milkfish', 'is_archived' => false]);
$project->setRelations(['association' => $association, 'status' => $status, 'programComponent' => $component, 'materials' => collect($empty ? [] : [$material])]);
$training = $make(App\Models\Training::class, ['id' => 1, 'association_id' => 1, 'title' => $long ? str_repeat('Training title ', 12) : 'Sample skills training', 'venue' => 'Sample hall', 'conducted_by' => 'BFAR', 'date_conducted' => now()->addDay(), 'end_date' => now()->addDays(2), 'stage' => 'accepted', 'is_archived' => false]);
$training->setRelations(['association' => $association, 'programComponent' => $component]);
$training->forceFill(['registered_count' => 1, 'recorded_count' => 0, 'present_count' => 0]);
$trainings = collect($empty ? [] : [$training]);
$member = $make(App\Models\Member::class, ['id' => 1, 'first_name' => 'Sample', 'last_name' => 'Participant', 'is_archived' => false]);
$participant = $make(App\Models\TrainingParticipant::class, ['id' => 1, 'attendance_status_id' => 1]);
$participant->setRelations(['member' => $member, 'attendanceStatus' => $make(App\Models\Status::class, ['status_name' => 'Pending'])]);
$paginate = fn ($rows) => new Illuminate\Pagination\LengthAwarePaginator($empty ? [] : $rows, $empty ? 0 : count($rows), 10, 1, ['path' => $path]);
$reportData = [
    'reportRoutes' => 'officer.reports', 'unavailable' => $state === 'unavailable', 'filters' => ['year' => 2025],
    'areas' => collect([$area]), 'associations' => collect($empty ? [] : [$association]),
    'counts' => ['associations' => $empty ? 0 : 1, 'members' => $empty ? 0 : 1, 'projects' => $empty ? 0 : 1, 'trainings' => 0],
    'incomeTotal' => 0, 'incomeRecords' => 0, 'months' => collect(range(1, 12))->map(fn ($month) => ['label' => date('F', mktime(0, 0, 0, $month, 1)), 'total' => 0, 'records' => 0]),
    'projectStatuses' => collect(), 'rows' => collect($empty ? [] : [(object) ['id' => 1, 'name' => $name, 'municipality' => 'Sample Municipality', 'members' => 1, 'projects' => 1, 'trainings' => 0, 'income' => 0]]),
    'production' => collect(), 'trainedMembers' => collect(), 'generatedAt' => now(),
];
$gisRecord = [
    'id' => 1, 'association_id' => 1, 'project_id' => null, 'project_title' => null, 'commodity' => null, 'project_archived' => false,
    'name' => 'Synthetic location', 'association' => $name, 'association_url' => null,
    'municipality_id' => 1, 'municipality' => 'Sample Municipality', 'barangay_id' => 1, 'barangay' => 'Sample Barangay',
    'component_id' => 1, 'component' => 'Aquaculture', 'status' => 'Active', 'archived' => false,
    'latitude' => 11.27, 'longitude' => 124.05, 'latitude_text' => '11.27', 'longitude_text' => '124.05', 'valid' => true, 'published' => false,
    'editable' => true, 'revision' => str_repeat('a', 32).':1', 'created_at' => '', 'updated_at' => '',
    'update_url' => '/officer/gis/1', 'archive_url' => '/officer/gis/1/archive', 'publication_url' => '/officer/gis/1/publish',
];
$data = match ($path) {
    '/officer/dashboard' => ['field-officer-user.dashboard', ['actor' => $officer, 'counts' => ['My Associations' => $empty ? 0 : 1, 'My Members' => $empty ? 0 : 1, 'Monitoring Records' => 0, 'Training Records' => 0], 'associations' => collect($empty ? [] : [$association]), 'recent' => collect()]],
    '/officer/associations' => ['field-officer-user.associations.index', ['associations' => $paginate([$association]), 'filters' => [], 'areas' => collect([$area]), 'components' => collect([$component]), 'statuses' => collect([$status])]],
    '/officer/associations/1' => ['field-officer-user.associations.show', compact('association')],
    '/officer/projects' => ['field-officer-user.projects.index', ['projects' => $paginate([$project]), 'association' => $association]],
    '/officer/projects/1' => [$request->boolean('details') ? 'shared.projects.details' : 'field-officer-user.projects.show', compact('project', 'trainings')],
    '/officer/trainings' => ['field-officer-user.trainings.index', ['trainings' => $paginate([$training]), 'association' => $association]],
    '/officer/trainings/1' => [$request->boolean('details') ? 'field-officer-user.trainings.details' : 'field-officer-user.trainings.show', ['attendance' => collect($empty ? [] : ['Pending' => 1]), 'projects' => collect($empty ? [] : [$project]), 'training' => $training, 'participants' => $paginate([$participant]), 'members' => collect(), 'statuses' => collect(['Pending', 'Present', 'Absent'])->map(fn ($name, $id) => $make(App\Models\Status::class, ['id' => $id + 1, 'status_name' => $name]))]],
    '/monitoring' => ['shared.monitoring.index', ['type' => 'production', 'filters' => [], 'types' => App\Services\MonitoringService::TYPES, 'records' => $paginate([]), 'projects' => collect()]],
    '/monitoring/production/create' => ['shared.monitoring.form', ['record' => null, 'type' => 'production', 'label' => 'Production', 'projects' => collect([(object) ['id' => 1, 'title' => $project->title, 'association_name' => $name]]), 'materials' => collect(), 'quarters' => collect([1 => 'Q1']), 'conditions' => collect()]],
    '/officer/reports' => ['shared.reports.index', $reportData],
    '/officer/gis' => ['shared.gis.index', ['records' => collect($empty ? [] : [$gisRecord]), 'unmapped' => collect(), 'associations' => collect($empty ? [] : [$association]), 'projects' => collect()]],
    default => null,
};
if ($data === null) {
    http_response_code(404);
    exit('This visual fixture has no page at this path.');
}
echo view($data[0], $data[1])->render();
