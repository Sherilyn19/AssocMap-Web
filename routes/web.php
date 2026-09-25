<?php

use App\Http\Controllers\Admin\AssociationManagementController;
use App\Http\Controllers\Admin\ProjectManagementController;
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
Route::get('/', function () {
    return view('welcome');
})->name('home');

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

Route::get('/officer/dashboard', [DashboardController::class, 'officer'])
    ->middleware('assocmap.auth:Field Officer')
    ->name('dashboard.officer');

Route::get('/member/dashboard', [DashboardController::class, 'member'])
    ->middleware('assocmap.auth:Association Member')
    ->name('dashboard.member');

// ============================================================
// USER-MANAGEMENT-ROUTES
// User and Access Control Module - System Administrator only.
// Named "users.*" to match sidebar.blade.php's nav item.
// ============================================================
use App\Http\Controllers\Admin\AdminUserManagementController;

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
use App\Http\Controllers\Admin\AreaManagementController;

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
        Route::get('/applications', [\App\Http\Controllers\Admin\MemberApplicationManagementController::class, 'index'])
            ->name('applications.index');
        Route::get('/applications/{application}', [\App\Http\Controllers\Admin\MemberApplicationManagementController::class, 'show'])
            ->whereNumber('application')
            ->name('applications.show');

        Route::get('/', [\App\Http\Controllers\Admin\MemberManagementController::class, 'index'])
            ->name('index');
        Route::get('/{member}', [\App\Http\Controllers\Admin\MemberManagementController::class, 'show'])
            ->whereNumber('member')
            ->name('show');
        Route::put('/{member}', [\App\Http\Controllers\Admin\MemberManagementController::class, 'update'])
            ->whereNumber('member')
            ->name('update');
        Route::patch('/{member}/archive', [\App\Http\Controllers\Admin\MemberManagementController::class, 'archive'])
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
    ->controller(\App\Http\Controllers\Admin\TrainingManagementController::class)
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

// MEMBERSHIP-WORKFLOW: scoped viewing; only the association account can submit/review.
Route::middleware('assocmap.auth')->prefix('membership')->name('membership.')
    ->controller(\App\Http\Controllers\MembershipController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/applications/create', 'create')->name('applications.create');
        Route::post('/applications', 'store')->middleware('throttle:membership-submit')->name('applications.store');
        Route::get('/applications/{application}', 'show')->whereNumber('application')->name('applications.show');
        Route::patch('/applications/{application}/review', 'review')->whereNumber('application')
            ->middleware('throttle:membership-review')->name('applications.review');
        Route::get('/members/{member}', 'member')->whereNumber('member')->name('members.show');
    });

// Provisioning a credential does not give administrators an approval endpoint.
Route::post('/admin/members/{member}/review-passphrase', [\App\Http\Controllers\MembershipController::class, 'credential'])
    ->whereNumber('member')->middleware(['assocmap.auth:System Administrator', 'throttle:membership-review'])
    ->name('members.review-passphrase');
