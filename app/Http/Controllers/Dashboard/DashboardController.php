<?php

// app/Http/Controllers/Dashboard/DashboardController.php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function officer(): View
    {
        $user = session('auth_user');

        return view('dashboard.officer', compact('user'));
    }

    public function member(): View
    {
        $user = session('auth_user');

        return view('dashboard.member', compact('user'));
    }
}