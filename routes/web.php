<?php

use App\Http\Controllers\Admin\AssociationManagementController;
use App\Http\Controllers\Admin\GisController;
use App\Http\Controllers\Admin\ProjectManagementController;
use App\Http\Controllers\Admin\AuditLogController;

/*
 * ============================================================
 * routes/web.php
 * ============================================================
 */

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

// ── Landing Page ─────────────────────────────────────────────
use App\Http\Controllers\Admin\AdminUserManagementController;
use App\Http\Controllers\Admin\AreaManagementController;
use App\Http\Controllers\Admin\MemberApplicationManagementController;
use App\Http\Controllers\Admin\MemberManagementController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\TrainingManagementController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\MonitoringController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/map', [\App\Http\Controllers\GisMapController::class, 'index'])->name('gis.public');
Route::get('/map/locations', [\App\Http\Controllers\GisMapController::class, 'index'])->name('gis.public.data');
Route::middleware('assocmap.auth')->group(function (): void {
    Route::get('/gis', [\App\Http\Controllers\GisMapController::class, 'index'])->name('gis.viewer');
    Route::get('/gis/locations', [\App\Http\Controllers\GisMapController::class, 'index'])->name('gis.viewer.data');
});

// ── Authentication ────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Protected Dashboards ──────────────────────────────────────
// assocmap.auth:RoleName checks session + enforces role match.

// AdminDashboardController
Route::get('/admin/dashboard', [AdminDashboardController::class, 'admin'])
    ->middleware('assocmap.auth:System Administrator')
    ->name('dashboard.admin');

Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])
    ->middleware('assocmap.auth:System Administrator')
    ->name('admin.audit-logs.index');

// Reports and downloads use the same current administrator permission check.
Route::middleware('assocmap.auth:System Administrator')->prefix('admin/reports')->name('reports.')
    ->controller(ReportsController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/export', 'export')->name('export');
    });

Route::get('/officer/dashboard', [DashboardController::class, 'officer'])
    ->middleware('assocmap.auth:Field Officer')
    ->name('dashboard.officer');

Route::middleware('assocmap.auth:Field Officer')->prefix('officer')->name('officer.')->group(function (): void {
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');
    
    Route::get('/areas',
        [\App\Http\Controllers\FieldOfficerUser\AreaController::class, 'index']
    )->name('areas.index');

    Route::get('/areas/{areaUnit}', [\App\Http\Controllers\FieldOfficerUser\AreaController::class, 'show'])->whereNumber('areaUnit')->name('areas.show');
    
    // Load association details within the Field Officer's assigned coverage.
    Route::get(
        '/areas/{areaUnit}/associations/{association}/{section}',
        [\App\Http\Controllers\FieldOfficerUser\AreaController::class, 'drawer']
    )
        ->whereNumber(['areaUnit', 'association'])
        ->whereIn('section', ['overview', 'members', 'projects', 'gis'])
        ->name('areas.drawer');

// Recheck the officer's assignment whenever drawer information is requested.
Route::get('/areas/{areaUnit}/associations/{association}/{section}', [
    \App\Http\Controllers\FieldOfficerUser\AreaController::class,
    'details',
])
    ->whereNumber('areaUnit')
    ->whereNumber('association')
    ->whereIn('section', ['association', 'members', 'projects', 'gis'])
    ->name('areas.details');

Route::get('/associations', [\App\Http\Controllers\FieldOfficerUser\AssociationController::class, 'index'])->name('associations.index');
    Route::get('/associations/{association}', [\App\Http\Controllers\FieldOfficerUser\AssociationController::class, 'show'])->whereNumber('association')->name('associations.show');
    // Every drawer request uses the same Field Officer authorization.
    Route::get('/associations/{association}/details/{section}', [
        \App\Http\Controllers\FieldOfficerUser\AssociationController::class,
        'details',
    ])
    ->whereNumber('association')
    ->whereIn('section', ['address', 'officer', 'members', 'projects', 'trainings'])
    ->name('associations.details');
    Route::get('/projects', [\App\Http\Controllers\FieldOfficerUser\ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [\App\Http\Controllers\FieldOfficerUser\ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
    Route::patch('/projects/{project}/materials/{material}/delivery', [\App\Http\Controllers\FieldOfficerUser\ProjectController::class, 'delivery'])->whereNumber(['project', 'material'])->name('projects.delivery');
    Route::controller(\App\Http\Controllers\FieldOfficerUser\TrainingController::class)->prefix('trainings')->name('trainings.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{training}', 'show')->whereNumber('training')->name('show');
        Route::put('/{training}', 'update')->whereNumber('training')->name('update');
        Route::post('/{training}/participants', 'addParticipant')->whereNumber('training')->name('participants.store');
        Route::patch('/{training}/participants/{participant}', 'attendance')->whereNumber(['training', 'participant'])->name('participants.attendance');
    });
});

Route::get('/member/dashboard', [\App\Http\Controllers\MemberWorkspaceController::class, 'dashboard'])
    ->middleware('assocmap.auth:Association Member')
    ->name('dashboard.member');

Route::middleware('assocmap.auth:Association Member')->prefix('member')->name('member.')
    ->controller(\App\Http\Controllers\MemberWorkspaceController::class)->group(function (): void {
        Route::get('/association', 'information')->name('information');
        Route::get('/members', 'members')->name('members');
        Route::get('/applications', 'applications')->name('applications');
        Route::get('/projects', 'projects')->name('projects');
        Route::get('/projects/{project}', 'project')->whereNumber('project')->name('projects.show');
        Route::get('/trainings', 'trainings')->name('trainings');
        Route::get('/trainings/{training}', 'training')->whereNumber('training')->name('trainings.show');
        Route::get('/production', 'production')->name('production');
    });

// ============================================================
// USER-MANAGEMENT-ROUTES
// User and Access Control Module - System Administrator only.
// Named "users.*" to match sidebar.blade.php's nav item.
// ============================================================

Route::middleware('assocmap.auth:System Administrator')
    ->prefix('admin/users')
    ->name('users.')
    ->group(function () {
        Route::get('/', [AdminUserManagementController::class, 'index'])->name('index');
        Route::post('/', [AdminUserManagementController::class, 'store'])->name('store');
        Route::put('/{user}', [AdminUserManagementController::class, 'update'])->whereNumber('user')->name('update');
        // Explicit actions make retries safe; an old toggle request must never change status.
        Route::patch('/{user}/activate', [AdminUserManagementController::class, 'activate'])->whereNumber('user')->name('activate');
        Route::patch('/{user}/deactivate', [AdminUserManagementController::class, 'deactivate'])->whereNumber('user')->name('deactivate');
    });
// USER-MANAGEMENT-ROUTES-END

// ============================================================
// AREA-MANAGEMENT-ROUTES
// Area Management Module - System Administrator only.
// ============================================================

Route::middleware('assocmap.auth:System Administrator')
    ->prefix('admin/areas')
    ->name('areas.')
    ->group(function () {
        Route::get('/', [AreaManagementController::class, 'index'])->name('index');

        Route::get('/municipalities/{areaUnit}', [AreaManagementController::class, 'showMunicipality'])->name('municipalities.show');
        Route::post('/municipalities', [AreaManagementController::class, 'storeMunicipality'])->name('municipalities.store');
        Route::put('/municipalities/{areaUnit}', [AreaManagementController::class, 'updateMunicipality'])->name('municipalities.update');
        Route::patch('/municipalities/{areaUnit}/archive', [AreaManagementController::class, 'archiveMunicipality'])->name('municipalities.archive');
        Route::patch('/municipalities/{areaUnit}/restore', [AreaManagementController::class, 'restoreMunicipality'])->name('municipalities.restore');

        Route::get('/barangays/{subUnit}', [AreaManagementController::class, 'showBarangay'])->name('barangays.show');
        Route::post('/barangays', [AreaManagementController::class, 'storeBarangay'])->name('barangays.store');
        Route::put('/barangays/{subUnit}', [AreaManagementController::class, 'updateBarangay'])->name('barangays.update');
        Route::patch('/barangays/{subUnit}/archive', [AreaManagementController::class, 'archiveBarangay'])->name('barangays.archive');
        Route::patch('/barangays/{subUnit}/restore', [AreaManagementController::class, 'restoreBarangay'])->name('barangays.restore');
    });
// AREA-MANAGEMENT-ROUTES-END

// ============================================================
// ASSOCIATION_ROUTES
// Association Management Module - System Administrator only.
// ============================================================
Route::prefix('admin/associations')
    ->name('admin.associations.')
    ->middleware('assocmap.auth:System Administrator')
    ->controller(AssociationManagementController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{association}', 'show')->name('show');
        Route::put('/{association}', 'update')->name('update');
        Route::patch('/{association}/archive', 'archive')->name('archive');
        Route::patch('/{association}/restore', 'restore')->name('restore');
        Route::patch('/{association}/representative', 'representative')->name('representative');
    });
// ASSOCMAP_ASSOCIATION_ROUTES_END
// The approved bootstrap exception creates one initial member, never an approval decision.
Route::middleware('assocmap.auth:System Administrator')->prefix('admin/associations/{association}/founding-member')
    ->whereNumber('association')->name('admin.founding-member.')
    ->controller(\App\Http\Controllers\Admin\FoundingMemberController::class)->group(function (): void {
        Route::get('/', 'create')->name('create');
        Route::post('/', 'store')->name('store');
    });

// ============================================================
// MEMBER-MANAGEMENT-ROUTES
// Member Management Module - System Administrator only.
// Administrator may manage official members and inspect applications.
// Approval / rejection remain Association Representative responsibilities.
// ============================================================
Route::middleware('assocmap.auth:System Administrator')
    ->prefix('admin/members')
    ->name('members.')
    ->group(function (): void {
        // Static application routes must stay before /{member}.
        Route::get('/applications', [MemberApplicationManagementController::class, 'index'])
            ->name('applications.index');
        Route::get('/applications/{application}', [MemberApplicationManagementController::class, 'show'])
            ->whereNumber('application')
            ->name('applications.show');

        Route::get('/', [MemberManagementController::class, 'index'])
            ->name('index');
        Route::get('/{member}', [MemberManagementController::class, 'show'])
            ->whereNumber('member')
            ->name('show');
        Route::put('/{member}', [MemberManagementController::class, 'update'])
            ->whereNumber('member')
            ->name('update');
        Route::patch('/{member}/archive', [MemberManagementController::class, 'archive'])
            ->whereNumber('member')
            ->name('archive');
    });
// MEMBER-MANAGEMENT-ROUTES-END
// ============================================================
// PROJECT-MANAGEMENT-ROUTES
// Admin Project Management - System Administrator only.
//
// ============================================================

// The projects.* names connect Blade links/forms to controller actions, independent
// of JavaScript folder paths. Nested material ownership is checked by controller/service.
Route::middleware('assocmap.auth:System Administrator')
    ->prefix('admin/projects')
    ->name('projects.')
    ->controller(ProjectManagementController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');

        // Static /create route is declared before the numeric project binding.
        Route::get('/{project}', 'show')
            ->whereNumber('project')
            ->name('show');

        Route::get('/{project}/edit', 'edit')
            ->whereNumber('project')
            ->name('edit');

        Route::put('/{project}', 'update')
            ->whereNumber('project')
            ->name('update');

        Route::patch('/{project}/archive', 'archive')
            ->whereNumber('project')
            ->name('archive');

        Route::post('/{project}/materials', 'storeMaterial')
            ->whereNumber('project')
            ->name('materials.store');

        Route::put('/{project}/materials/{material}', 'updateMaterial')
            ->whereNumber('project')
            ->whereNumber('material')
            ->name('materials.update');
    });

// PROJECT-MANAGEMENT-ROUTES-END
// Training records and attendance use the same administrator access policy as projects.
Route::middleware('assocmap.auth:System Administrator')
    ->prefix('admin/trainings')->name('trainings.')
    ->controller(TrainingManagementController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{training}', 'show')->whereNumber('training')->name('show');
        Route::get('/{training}/edit', 'edit')->whereNumber('training')->name('edit');
        Route::put('/{training}', 'update')->whereNumber('training')->name('update');
        Route::patch('/{training}/archive', 'archive')->whereNumber('training')->name('archive');
        Route::patch('/{training}/restore', 'restore')->whereNumber('training')->name('restore');
        Route::post('/{training}/participants', 'addParticipant')->whereNumber('training')->name('participants.store');
        Route::patch('/{training}/participants/{participant}', 'attendance')->whereNumber(['training', 'participant'])->name('participants.attendance');
        Route::delete('/{training}/participants/{participant}', 'removeParticipant')->whereNumber(['training', 'participant'])->name('participants.destroy');
    });

// Monitoring permissions and association assignments are checked by the service.
Route::middleware('assocmap.auth')->prefix('monitoring')->name('monitoring.')
    ->controller(MonitoringController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{type}/create', 'create')->whereIn('type', ['production', 'income', 'materials'])->name('create');
        Route::post('/{type}', 'store')->whereIn('type', ['production', 'income', 'materials'])->name('store');
        Route::get('/{type}/{record}/edit', 'edit')->whereIn('type', ['production', 'income', 'materials'])->whereNumber('record')->name('edit');
        Route::put('/{type}/{record}', 'update')->whereIn('type', ['production', 'income', 'materials'])->whereNumber('record')->name('update');
    });

    // Saved drafts are available to their eligible creator and administrator viewers.
Route::middleware('assocmap.auth')
    ->prefix('membership/drafts')
    ->name('membership.drafts.')
    ->controller(\App\Http\Controllers\MemberDraftController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');

        Route::post('/', 'store')
            ->middleware('throttle:membership-submit')->name('store');

        Route::get('/{draft}', 'show')->whereNumber('draft')->name('show');

        Route::put('/{draft}', 'update')->whereNumber('draft')
            ->middleware('throttle:membership-submit')->name('update');

        Route::patch('/{draft}/cancel', 'cancel')->whereNumber('draft')
            ->middleware('throttle:membership-submit')->name('cancel');

        Route::post('/{draft}/submit', 'submit')->whereNumber('draft')
            ->middleware('throttle:membership-submit')->name('submit');
    });

// Keep administrator routes administrator-only.
Route::middleware('assocmap.auth:Field Officer')
    ->prefix('officer/members')
    ->name('officer.members.')
    ->controller(\App\Http\Controllers\FieldOfficerUser\MemberController::class)
    ->group(function (): void {
        Route::get('/{member}/edit', 'edit')->whereNumber('member')->name('edit');
        Route::put('/{member}', 'update')->whereNumber('member')->name('update');
        Route::patch('/{member}/archive', 'archive')
            ->whereNumber('member')->name('archive');
    });    
// MEMBERSHIP-WORKFLOW: scoped viewing; only the association account can submit/review.
Route::middleware('assocmap.auth')->prefix('membership')->name('membership.')
    ->controller(MembershipController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/applications/create', 'create')->name('applications.create');
        Route::post('/applications', 'store')->middleware('throttle:membership-submit')->name('applications.store');
        Route::get('/applications/{application}', 'show')->whereNumber('application')->name('applications.show');
        Route::post('/applications/{application}/review-access', 'unlockReview')->whereNumber('application')
            ->middleware('throttle:membership-review')->name('applications.review-access');
        Route::patch('/applications/{application}/review', 'review')->whereNumber('application')
            ->middleware('throttle:membership-review')->name('applications.review');
        Route::get('/members/{member}', 'member')->whereNumber('member')->name('members.show');
    });

// Provisioning a credential does not give administrators an approval endpoint.
Route::post('/admin/members/{member}/review-passphrase', [MembershipController::class, 'credential'])
    ->whereNumber('member')->middleware(['assocmap.auth:System Administrator', 'throttle:membership-review'])
    ->name('members.review-passphrase');

// Allow only system administrators to open GIS Mapping.
Route::middleware(['assocmap.auth:System Administrator', 'throttle:10,1'])->prefix('admin/gis')->group(function (): void {
    Route::get('/transfer', [\App\Http\Controllers\Admin\GisTransferController::class, 'index'])->name('gis.transfer');
    Route::post('/import/preview', [\App\Http\Controllers\Admin\GisTransferController::class, 'preview'])->name('gis.import.preview');
    Route::post('/import/confirm', [\App\Http\Controllers\Admin\GisTransferController::class, 'confirm'])->name('gis.import.confirm');
    Route::post('/export', [\App\Http\Controllers\Admin\GisTransferController::class, 'export'])->name('gis.export');
});

Route::middleware('assocmap.auth:Field Officer')->prefix('officer/gis')->name('gis.officer.')->group(function (): void {
    Route::get('/', [GisController::class, 'index'])->name('index');
    Route::post('/', [GisController::class, 'store'])->name('store');
    Route::put('/{location}', [GisController::class, 'update'])->whereNumber('location')->name('update');
    Route::patch('/{location}/publish', [GisController::class, 'publish'])->whereNumber('location')->name('publish');
    Route::patch('/{location}/unpublish', [GisController::class, 'unpublish'])->whereNumber('location')->name('unpublish');
    Route::patch('/{location}/archive', [GisController::class, 'archive'])->whereNumber('location')->name('archive');
});

// This route name matches the existing GIS Mapping sidebar link.
Route::get('/admin/gis', [GisController::class, 'index'])
    ->middleware('assocmap.auth:System Administrator')
    ->name('gis.index');

// Every write rechecks the current administrator account and uses CSRF protection.
Route::post('/admin/gis', [GisController::class, 'store'])
    ->middleware('assocmap.auth:System Administrator')->name('gis.store');
Route::put('/admin/gis/{location}', [GisController::class, 'update'])
    ->whereNumber('location')->middleware('assocmap.auth:System Administrator')->name('gis.update');
Route::patch('/admin/gis/{location}/publish', [GisController::class, 'publish'])
    ->whereNumber('location')->middleware('assocmap.auth:System Administrator')->name('gis.publish');
Route::patch('/admin/gis/{location}/unpublish', [GisController::class, 'unpublish'])
    ->whereNumber('location')->middleware('assocmap.auth:System Administrator')->name('gis.unpublish');
Route::patch('/admin/gis/{location}/archive', [GisController::class, 'archive'])
    ->whereNumber('location')->middleware('assocmap.auth:System Administrator')->name('gis.archive');