<?php

/*
 * ============================================================
 * routes/web.php
 * ============================================================
 */

use App\Http\Controllers\Auth\AuthController;
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

Route::get('/admin/dashboard', [DashboardController::class, 'admin'])
    ->middleware('assocmap.auth:System Administrator')
    ->name('dashboard.admin');

Route::get('/officer/dashboard', [DashboardController::class, 'officer'])
    ->middleware('assocmap.auth:Field Officer')
    ->name('dashboard.officer');

Route::get('/member/dashboard', [DashboardController::class, 'member'])
    ->middleware('assocmap.auth:Association Member')
    ->name('dashboard.member');
