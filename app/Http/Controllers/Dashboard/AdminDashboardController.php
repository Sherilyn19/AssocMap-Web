<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;

class AdminDashboardController extends Controller
{
    public function admin(AdminDashboardService $dashboard): Response
    {
        $user = session('auth_user');

        try {
            return response()->view('admin-pages.dashboard', ['user' => $user, ...$dashboard->overview()]);
        } catch (QueryException $exception) {
            report($exception);

            // Unavailable data must never be presented as zero records.
            return response()->view('admin-pages.dashboard', ['user' => $user, 'unavailable' => true], 503);
        }
    }
}
