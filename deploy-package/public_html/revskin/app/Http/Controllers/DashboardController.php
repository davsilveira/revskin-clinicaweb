<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Dashboard/Index', [
            // Add your dashboard stats here
            // Example:
            // 'stats' => [
            //     'total' => Model::count(),
            //     'pending' => Model::where('status', 'pending')->count(),
            // ],
        ]);
    }
}

