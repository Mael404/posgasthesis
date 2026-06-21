<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    private const ADMIN_ROLES = ['admin', 'vp_admin', 'semiadmin'];

    public function index(): View
    {
        $this->authorizeAdmin();
        $this->ensureAnnouncementsTableReady();

        $announcements = DB::table('announcements')
            ->leftJoin('users', 'announcements.admin_id', '=', 'users.id')
            ->select('announcements.*', 'users.name as admin_name')
            ->orderByDesc('announcements.created_at')
            ->orderByDesc('announcements.id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.admin.announcements', [
            'title' => 'Announcements',
            'announcements' => $announcements,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureAnnouncementsTableReady();
        $this->trimAnnouncementInput($request);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        DB::table('announcements')->insert([
            'admin_id' => auth()->id(),
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse($request, 'Announcement posted for all staff.');
    }

    public function update(Request $request, $announcement): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureAnnouncementsTableReady();
        $this->trimAnnouncementInput($request);

        $announcementId = (int) $announcement;

        if (! DB::table('announcements')->where('id', $announcementId)->exists()) {
            abort(404);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        DB::table('announcements')
            ->where('id', $announcementId)
            ->update([
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'updated_at' => now(),
            ]);

        return $this->successResponse($request, 'Announcement updated.');
    }

    public function destroy(Request $request, $announcement): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureAnnouncementsTableReady();

        DB::transaction(function () use ($announcement) {
            $announcementId = (int) $announcement;

            if (Schema::hasTable('announcement_reads')) {
                DB::table('announcement_reads')
                    ->where('announcement_id', $announcementId)
                    ->delete();
            }

            $deleted = DB::table('announcements')
                ->where('id', $announcementId)
                ->delete();

            if ($deleted < 1) {
                abort(404);
            }
        });

        return $this->successResponse($request, 'Announcement deleted.');
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->check() || ! in_array(auth()->user()->role ?? null, self::ADMIN_ROLES, true)) {
            abort(403);
        }
    }

    private function ensureAnnouncementsTableReady(): void
    {
        if (! Schema::hasTable('announcements')) {
            abort(500, 'Announcements table is not ready. Please run php artisan migrate.');
        }
    }

    private function trimAnnouncementInput(Request $request): void
    {
        $request->merge([
            'subject' => trim((string) $request->input('subject', '')),
            'message' => trim((string) $request->input('message', '')),
        ]);
    }

    private function successResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}
