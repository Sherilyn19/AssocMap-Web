<?php

// Local browser preview with synthetic records. It does not connect to the database.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$asset = realpath($root.'/public/'.$path);
if ($asset && str_starts_with($asset, realpath($root.'/public').DIRECTORY_SEPARATOR) && is_file($asset) && pathinfo($asset, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8127');
session(['auth_user' => ['id' => 1, 'name' => 'Program Administrator', 'role_name' => 'System Administrator']]);
app(Illuminate\Foundation\Vite::class)->useHotFile(storage_path('app/gis-preview-no-hot'));
$base = [
    'association' => 'Coastal Association', 'association_url' => null,
    'municipality_id' => 1, 'municipality' => 'North', 'barangay_id' => 10, 'barangay' => 'Bay',
    'component_id' => 1, 'component' => 'Capture Fisheries', 'status' => 'Active', 'archived' => false,
    'latitude' => 11.2745, 'longitude' => 124.0524, 'valid' => true, 'published' => true,
];
$records = collect([
    array_replace($base, ['id' => 1, 'name' => 'Landing area']),
    array_replace($base, ['id' => 2, 'name' => 'Association office', 'published' => false]),
    array_replace($base, ['id' => 3, 'name' => 'Seaweed production site', 'latitude' => 10.9408, 'longitude' => 124.015, 'municipality_id' => 2, 'municipality' => 'South', 'barangay_id' => 20, 'barangay' => 'Shore', 'component_id' => 2, 'component' => 'Aquaculture']),
    array_replace($base, ['id' => 4, 'name' => 'Location needing review', 'valid' => false, 'latitude' => null, 'longitude' => null, 'archived' => true]),
]);
echo view('admin-pages.admin-gis-mapping.index', ['records' => $records, 'unmapped' => collect()])->render();
