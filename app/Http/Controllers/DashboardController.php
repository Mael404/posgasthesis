<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->dashboardRouteName());
    }

    public function admin(): View
    {
        return view('pages.admin.admindashboard', ['title' => 'VP Admin Dashboard']);
    }

    public function semiAdmin(): View
    {
        return $this->dashboard(
            'Semi Admin Dashboard',
            'Semi Admin',
            'Operational access for staff review, approvals, reports, and daily supervision.'
        );
    }

    public function staff(): View
    {
        return view('pages.staff.staffdashboard', ['title' => 'Staff Dashboard']);
    }

    private function dashboard(string $title, string $roleName, string $roleDescription): View
    {
        return view('pages.dashboard.ecommerce', compact('title', 'roleName', 'roleDescription'));
    }
}
