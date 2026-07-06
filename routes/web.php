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

// FIX: Updated to use AdminDashboardController
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
// USER-MANAGEMENT-ROUTES-START
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
