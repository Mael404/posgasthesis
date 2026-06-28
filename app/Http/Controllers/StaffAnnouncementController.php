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
    private const ADMIN_ROLES = ['admin', 'vp_admin', 'semiadmin'];

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
        $focusedAnnouncementId = (int) request('announcement', 0);

        if ($focusedAnnouncementId > 0 && ! $announcementItems->contains('id', $focusedAnnouncementId)) {
            $focusedAnnouncement = DB::table('announcements')
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
                ->where('announcements.id', $focusedAnnouncementId)
                ->first();

            if ($focusedAnnouncement) {
                $announcementItems->prepend($focusedAnnouncement);
                $announcements->setCollection($announcementItems);
            }
        }

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
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $parentId = $this->validatedParentCommentId($announcementId, $validated['parent_id'] ?? null);
        $now = now();
        $commentId = DB::table('announcement_comments')->insertGetId([
            'announcement_id' => $announcementId,
            'parent_id' => $parentId,
            'user_id' => (int) auth()->id(),
            'comment' => $validated['comment'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'success' => true,
            'message' => $parentId ? 'Reply sent to admin.' : 'Comment sent to admin.',
            'comment' => $this->formatComment($this->commentById($commentId)),
        ]);
    }

    private function ensureAnnouncementsTablesReady(): void
    {
        if (
            ! Schema::hasTable('announcements')
            || ! Schema::hasTable('announcement_reads')
            || ! Schema::hasTable('announcement_comments')
            || ! Schema::hasColumn('announcement_comments', 'parent_id')
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

        $hasParentColumn = Schema::hasColumn('announcement_comments', 'parent_id');

        return DB::table('announcement_comments')
            ->leftJoin('users', 'announcement_comments.user_id', '=', 'users.id')
            ->select(
                'announcement_comments.id',
                'announcement_comments.announcement_id',
                $hasParentColumn ? 'announcement_comments.parent_id' : DB::raw('NULL as parent_id'),
                'announcement_comments.user_id',
                'announcement_comments.comment',
                'announcement_comments.created_at',
                'users.name as author_name',
                'users.role as author_role'
            )
            ->whereIn('announcement_comments.announcement_id', $announcementIds)
            ->orderBy('announcement_comments.created_at')
            ->orderBy('announcement_comments.id')
            ->get()
            ->groupBy('announcement_id')
            ->map(function ($comments) {
                return $this->threadComments($comments);
            })
            ->all();
    }

    private function formatComment($comment): array
    {
        $role = (string) ($comment->author_role ?? '');
        $userId = (int) $comment->user_id;
        $isAdmin = in_array($role, self::ADMIN_ROLES, true);
        $authorName = $comment->author_name ?: (($isAdmin ? 'Admin #' : 'Staff #') . $userId);

        return [
            'id' => (int) $comment->id,
            'announcement_id' => (int) $comment->announcement_id,
            'parent_id' => $comment->parent_id ? (int) $comment->parent_id : null,
            'user_id' => $userId,
            'author_name' => $authorName,
            'author_role' => $role,
            'author_label' => $isAdmin ? 'Admin' : 'Staff',
            'staff_name' => $authorName,
            'comment' => (string) $comment->comment,
            'created_at' => $comment->created_at
                ? Carbon::parse($comment->created_at)->format('M d, Y h:i A')
                : 'N/A',
            'replies' => [],
        ];
    }

    private function threadComments($comments): array
    {
        $formatted = collect($comments)
            ->map(fn ($comment) => $this->formatComment($comment))
            ->values();

        $commentIds = $formatted
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $grouped = $formatted->groupBy(function (array $comment) use ($commentIds) {
            $parentId = $comment['parent_id'] ? (string) $comment['parent_id'] : null;

            return $parentId && in_array($parentId, $commentIds, true) ? $parentId : 'root';
        });

        $attachReplies = function (?int $parentId) use (&$attachReplies, $grouped) {
            $key = $parentId ? (string) $parentId : 'root';

            return collect($grouped->get($key, []))
                ->map(function (array $comment) use (&$attachReplies) {
                    $comment['replies'] = $attachReplies((int) $comment['id']);

                    return $comment;
                })
                ->values()
                ->all();
        };

        return $attachReplies(null);
    }

    private function validatedParentCommentId(int $announcementId, $parentId): ?int
    {
        $parentId = (int) $parentId;

        if ($parentId < 1) {
            return null;
        }

        $parentExists = DB::table('announcement_comments')
            ->where('id', $parentId)
            ->where('announcement_id', $announcementId)
            ->exists();

        if (! $parentExists) {
            abort(response()->json([
                'message' => 'The selected comment could not be found.',
                'errors' => [
                    'parent_id' => ['The selected comment could not be found.'],
                ],
            ], 422));
        }

        return $parentId;
    }

    private function commentById(int $commentId)
    {
        return DB::table('announcement_comments')
            ->leftJoin('users', 'announcement_comments.user_id', '=', 'users.id')
            ->select(
                'announcement_comments.id',
                'announcement_comments.announcement_id',
                'announcement_comments.parent_id',
                'announcement_comments.user_id',
                'announcement_comments.comment',
                'announcement_comments.created_at',
                'users.name as author_name',
                'users.role as author_role'
            )
            ->where('announcement_comments.id', $commentId)
            ->first();
    }
}
