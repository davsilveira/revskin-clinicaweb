<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Legacy /dashboard URL — redirect to the role-based home (no 404).
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->homeRouteName());
    }
}
