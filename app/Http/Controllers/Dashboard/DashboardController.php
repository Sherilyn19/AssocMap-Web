<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * ============================================================
 * DashboardController
 * app/Http/Controllers/Dashboard/DashboardController.php
 * ============================================================
 * Serves the dashboard view for each authenticated role.
 * All three methods are protected by the AssocMapAuth middleware
 * which validates the session before reaching here.
 *
 * SRP: this controller only handles dashboard rendering.
 * ============================================================
 */
class DashboardController extends Controller
{
    /**
     * System Administrator dashboard.
     * Route: GET /admin/dashboard
     */
    public function admin(): View
    {
        /*
         * TODO (Capstone 2):
         * Inject AdminDashboardService to fetch:
         *   - Total associations count
         *   - Total members count
         *   - GIS published markers count
         *   - Recent audit log entries
         */

        // Pass the authenticated user's name to the view
        $user = session('auth_user');

        return view('dashboard.admin', compact('user'));
    }

    /**
     * Field Officer dashboard.
     * Route: GET /officer/dashboard
     */
    public function officer(): View
    {
        /*
         * TODO (Capstone 2):
         * Inject FieldOfficerService to fetch:
         *   - Assigned associations (scoped by field_officer_id)
         *   - Pending member applications
         *   - Recent monitoring entries
         */

        $user = session('auth_user');

        return view('dashboard.officer', compact('user'));
    }

    /**
     * Association Member dashboard.
     * Route: GET /member/dashboard
     */
    public function member(): View
    {
        /*
         * TODO (Capstone 2):
         * Inject MemberService to fetch:
         *   - Their association info
         *   - Active projects
         *   - Upcoming trainings
         */

        $user = session('auth_user');

        return view('dashboard.member', compact('user'));
    }
}
