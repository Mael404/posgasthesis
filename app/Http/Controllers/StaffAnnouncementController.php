<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StaffAnnouncementController extends Controller
{
    public function index(): View
    {
        $this->ensureAnnouncementsTablesReady();

        $userId = (int) auth()->id();

        $announcements = DB::table('announcements')
            ->leftJoin('users', 'announcements.admin_id', '=', 'users.id')
            ->leftJoin('announcement_reads', function ($join) use ($userId) {
                $join->on('announcement_reads.announcement_id', '=', 'announcements.id')
                    ->where('announcement_reads.user_id', '=', $userId);
            })
            ->select(
                'announcements.*',
                'users.name as admin_name',
                'announcement_reads.read_at'
            )
            ->orderByDesc('announcements.created_at')
            ->orderByDesc('announcements.id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.staff.announcements', [
            'title' => 'Notifications',
            'announcements' => $announcements,
            'unreadCount' => $this->unreadCount($userId),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensureAnnouncementsTablesReady();

        $userId = (int) auth()->id();
        $now = now();

        $readAnnouncementIds = DB::table('announcement_reads')
            ->where('user_id', $userId)
            ->pluck('announcement_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $unreadAnnouncementIds = DB::table('announcements')
            ->when(! empty($readAnnouncementIds), function ($query) use ($readAnnouncementIds) {
                $query->whereNotIn('id', $readAnnouncementIds);
            })
            ->pluck('id');

        foreach ($unreadAnnouncementIds as $announcementId) {
            DB::table('announcement_reads')->insert([
                'announcement_id' => (int) $announcementId,
                'user_id' => $userId,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.',
                'unread_count' => 0,
            ]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    public function markRead(Request $request, $announcement): JsonResponse
    {
        $this->ensureAnnouncementsTablesReady();

        $announcementId = (int) $announcement;
        $userId = (int) auth()->id();

        if (! DB::table('announcements')->where('id', $announcementId)->exists()) {
            abort(404);
        }

        $now = now();

        $readKey = [
            'announcement_id' => $announcementId,
            'user_id' => $userId,
        ];
        $readExists = DB::table('announcement_reads')->where($readKey)->exists();

        DB::table('announcement_reads')->updateOrInsert(
            $readKey,
            [
                'read_at' => $now,
                'updated_at' => $now,
            ] + ($readExists ? [] : ['created_at' => $now])
        );

        return response()->json([
            'success' => true,
            'read_at' => $now->format('M d, Y h:i A'),
            'unread_count' => $this->unreadCount($userId),
        ]);
    }

    private function ensureAnnouncementsTablesReady(): void
    {
        if (! Schema::hasTable('announcements') || ! Schema::hasTable('announcement_reads')) {
            abort(500, 'Announcements tables are not ready. Please run php artisan migrate.');
        }
    }

    private function unreadCount(int $userId): int
    {
        return (int) DB::table('announcements')
            ->whereNotExists(function ($query) use ($userId) {
                $query->selectRaw('1')
                    ->from('announcement_reads')
                    ->whereColumn('announcement_reads.announcement_id', 'announcements.id')
                    ->where('announcement_reads.user_id', $userId);
            })
            ->count();
    }
}
