<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $announcementItems = $announcements->getCollection();

        return view('pages.staff.announcements', [
            'title' => 'Notifications',
            'announcements' => $announcements,
            'commentsByAnnouncement' => $this->commentsByAnnouncement(
                $announcementItems->pluck('id')->map(fn ($id) => (int) $id)->all()
            ),
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

    public function storeComment(Request $request, $announcement): JsonResponse
    {
        $this->ensureAnnouncementsTablesReady();
        $this->trimCommentInput($request);

        $announcementId = (int) $announcement;

        if (! DB::table('announcements')->where('id', $announcementId)->exists()) {
            abort(404);
        }

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $now = now();
        $commentId = DB::table('announcement_comments')->insertGetId([
            'announcement_id' => $announcementId,
            'user_id' => (int) auth()->id(),
            'comment' => $validated['comment'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $comment = DB::table('announcement_comments')
            ->leftJoin('users', 'announcement_comments.user_id', '=', 'users.id')
            ->select(
                'announcement_comments.id',
                'announcement_comments.announcement_id',
                'announcement_comments.user_id',
                'announcement_comments.comment',
                'announcement_comments.created_at',
                'users.name as staff_name'
            )
            ->where('announcement_comments.id', $commentId)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Comment sent to admin.',
            'comment' => $this->formatComment($comment),
        ]);
    }

    private function ensureAnnouncementsTablesReady(): void
    {
        if (
            ! Schema::hasTable('announcements')
            || ! Schema::hasTable('announcement_reads')
            || ! Schema::hasTable('announcement_comments')
        ) {
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

    private function trimCommentInput(Request $request): void
    {
        $request->merge([
            'comment' => trim((string) $request->input('comment', '')),
        ]);
    }

    private function commentsByAnnouncement(array $announcementIds): array
    {
        if (
            empty($announcementIds)
            || ! Schema::hasTable('announcement_comments')
            || ! Schema::hasTable('users')
        ) {
            return [];
        }

        return DB::table('announcement_comments')
            ->leftJoin('users', 'announcement_comments.user_id', '=', 'users.id')
            ->select(
                'announcement_comments.id',
                'announcement_comments.announcement_id',
                'announcement_comments.user_id',
                'announcement_comments.comment',
                'announcement_comments.created_at',
                'users.name as staff_name'
            )
            ->whereIn('announcement_comments.announcement_id', $announcementIds)
            ->orderBy('announcement_comments.created_at')
            ->orderBy('announcement_comments.id')
            ->get()
            ->groupBy('announcement_id')
            ->map(function ($comments) {
                return $comments->map(fn ($comment) => $this->formatComment($comment))->values()->all();
            })
            ->all();
    }

    private function formatComment($comment): array
    {
        return [
            'id' => (int) $comment->id,
            'announcement_id' => (int) $comment->announcement_id,
            'user_id' => (int) $comment->user_id,
            'staff_name' => $comment->staff_name ?: 'Staff #' . $comment->user_id,
            'comment' => (string) $comment->comment,
            'created_at' => $comment->created_at
                ? Carbon::parse($comment->created_at)->format('M d, Y h:i A')
                : 'N/A',
        ];
    }
}
