<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachProfile;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $coachesTotal = User::role('coach')->count();

        $activeCoaches = CoachProfile::where('status', 'active')->count();
        $inactiveCoaches = CoachProfile::where('status', 'inactive')->count();
        $suspendedCoaches = CoachProfile::whereNotNull('suspended_at')->count();

        return view('admin.dashboard', compact(
            'coachesTotal',
            'activeCoaches',
            'inactiveCoaches',
            'suspendedCoaches'
        ));
    }
}
