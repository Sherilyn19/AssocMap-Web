<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * ============================================================
 * AdminDashboardController
 * app/Http/Controllers/Dashboard/AdminDashboardController.php
 * ============================================================
 * This controller strictly handles the Admin dashboard rendering.
 * ============================================================
 */
class AdminDashboardController extends Controller
{
    /**
     * System Administrator dashboard.
     * Route: GET /admin/dashboard (dashboard.admin)
     */
    public function admin(): View
    {
        /*
         * TODO:
         * Inject AdminDashboardService to fetch:
         *   - Total associations count
         *   - Total members count
         *   - GIS published markers count
         *   - Recent audit log entries
         */

        // Retrieve the authenticated user's data from the session
        $user = session('auth_user');

        // Render the admin-specific dashboard view
        return view('admin-pages.dashboard', compact('user'));
    }
}
