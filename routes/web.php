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
        Route::put('/{user}', [AdminUserManagementController::class, 'update'])->name('update');
        Route::patch('/{user}/toggle-active', [AdminUserManagementController::class, 'toggleActive'])->name('toggle-active');
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
        Route::patch('/municipalities/{areaUnit}/toggle-archive', [AreaManagementController::class, 'toggleArchiveMunicipality'])->name('municipalities.toggle-archive');

        Route::get('/barangays/{subUnit}', [AreaManagementController::class, 'showBarangay'])->name('barangays.show');
        Route::post('/barangays', [AreaManagementController::class, 'storeBarangay'])->name('barangays.store');
        Route::put('/barangays/{subUnit}', [AreaManagementController::class, 'updateBarangay'])->name('barangays.update');
        Route::patch('/barangays/{subUnit}/toggle-archive', [AreaManagementController::class, 'toggleArchiveBarangay'])->name('barangays.toggle-archive');
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