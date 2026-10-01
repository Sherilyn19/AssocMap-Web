<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\URL;

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
$app->make(Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
URL::forceRootUrl('http://127.0.0.1:8127');
session(['auth_user' => ['id' => 1, 'name' => 'Program Administrator', 'role_name' => 'System Administrator']]);
app(Vite::class)->useHotFile(storage_path('app/gis-preview-no-hot'));
$base = [
    'association' => 'Coastal Association', 'association_url' => null,
    'municipality_id' => 1, 'municipality' => 'North', 'barangay_id' => 10, 'barangay' => 'Bay',
    'component_id' => 1, 'component' => 'Capture Fisheries', 'status' => 'Active', 'archived' => false,
    'latitude' => 11.2745, 'longitude' => 124.0524, 'valid' => true, 'published' => true,
    'association_id' => 1, 'revision' => str_repeat('a', 32).':1', 'editable' => true,
    'update_url' => 'http://127.0.0.1:8127/admin/gis/1',
    'publication_url' => 'http://127.0.0.1:8127/admin/gis/1/unpublish',
    'latitude_text' => '11.2745', 'longitude_text' => '124.0524', 'created_at' => '', 'updated_at' => '',
];
$records = collect([
    array_replace($base, ['id' => 1, 'name' => 'Landing area']),
    array_replace($base, ['id' => 2, 'name' => 'Association office', 'published' => false]),
    array_replace($base, ['id' => 3, 'name' => 'Seaweed production site', 'latitude' => 10.9408, 'longitude' => 124.015, 'municipality_id' => 2, 'municipality' => 'South', 'barangay_id' => 20, 'barangay' => 'Shore', 'component_id' => 2, 'component' => 'Aquaculture']),
    array_replace($base, ['id' => 4, 'name' => 'Location needing review', 'valid' => false, 'latitude' => null, 'longitude' => null, 'archived' => true, 'editable' => false]),
]);
// Writes are deliberately rejected in this visual-only fixture.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Preview has no database writes.', 'uncertain' => true]);
    exit;
}
echo view('shared.gis.index', ['records' => $records, 'unmapped' => collect(), 'associations' => collect([(object) ['id' => 1, 'name' => 'Coastal Association']])])->render();
