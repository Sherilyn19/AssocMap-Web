<?php

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

