<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $announcementItems = $announcements->getCollection();
        $focusedAnnouncementId = (int) request('announcement', 0);

        if ($focusedAnnouncementId > 0 && ! $announcementItems->contains('id', $focusedAnnouncementId)) {
            $focusedAnnouncement = DB::table('announcements')
                ->leftJoin('users', 'announcements.admin_id', '=', 'users.id')
                ->select('announcements.*', 'users.name as admin_name')
                ->where('announcements.id', $focusedAnnouncementId)
                ->first();

            if ($focusedAnnouncement) {
                $announcementItems->prepend($focusedAnnouncement);
                $announcements->setCollection($announcementItems);
            }
        }

        return view('pages.admin.announcements', [
            'title' => 'Announcements',
            'announcements' => $announcements,
            'commentsByAnnouncement' => $this->commentsByAnnouncement(
                $announcementItems->pluck('id')->map(fn ($id) => (int) $id)->all()
            ),
            'totalComments' => $this->totalComments(),
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

            if (Schema::hasTable('announcement_comments')) {
                DB::table('announcement_comments')
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

    private function totalComments(): int
    {
        if (! Schema::hasTable('announcement_comments')) {
            return 0;
        }

        return (int) DB::table('announcement_comments')->count();
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
