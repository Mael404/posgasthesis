<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    private const ADMIN_AUTO_BACKUP_SESSION_KEY = 'admin_auto_backup_download_url';

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route($request->user()->dashboardRouteName());
        }

        return view('pages.auth.signin', ['title' => 'Sign In']);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route($request->user()->dashboardRouteName());
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'status' => 'active',
        ], $remember)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match an active user account.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user instanceof User || ! $user->hasKnownRole()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your account role is not allowed to access this system.'])
                ->onlyInput('email');
        }

        $this->queueDailyAdminBackupDownload($request, $user);

        return redirect()->route($user->dashboardRouteName());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }

    private function queueDailyAdminBackupDownload(Request $request, User $user): void
    {
        if ($user->role !== User::ROLE_VP_ADMIN) {
            return;
        }

        if (! Schema::hasColumn('users', 'last_auto_backup_downloaded_on')) {
            return;
        }

        $today = Carbon::now(config('app.timezone', 'Asia/Manila'))->toDateString();
        $lastBackupDate = $user->last_auto_backup_downloaded_on;
        $lastBackupDate = $lastBackupDate instanceof \DateTimeInterface
            ? $lastBackupDate->format('Y-m-d')
            : (string) $lastBackupDate;

        if ($lastBackupDate === $today) {
            return;
        }

        $request->session()->flash(
            self::ADMIN_AUTO_BACKUP_SESSION_KEY,
            route('admin.backup.db', ['auto' => 'daily-login'])
        );
    }
}
