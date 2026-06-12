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
        return $this->dashboard(
            'VP Admin Dashboard',
            'VP Admin',
            'Full administrative access for executive monitoring, user oversight, and reports.'
        );
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
        return $this->dashboard(
            'Staff Dashboard',
            'Staff',
            'Staff workspace for daily activities, profile access, forms, and assigned records.'
        );
    }

    private function dashboard(string $title, string $roleName, string $roleDescription): View
    {
        return view('pages.dashboard.ecommerce', compact('title', 'roleName', 'roleDescription'));
    }
}
