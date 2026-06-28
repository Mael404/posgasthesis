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

    public function storeComment(Request $request, $announcement): JsonResponse
    {
        $this->authorizeAdmin();
        $this->ensureAnnouncementsTableReady();
        $this->ensureAnnouncementCommentsTableReady();
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
        $this->markAnnouncementUnreadForStaff($announcementId, $parentId);

        return response()->json([
            'success' => true,
            'message' => $parentId ? 'Reply posted.' : 'Comment posted.',
            'comment' => $this->formatComment($this->commentById($commentId)),
        ]);
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

    private function ensureAnnouncementCommentsTableReady(): void
    {
        if (! Schema::hasTable('announcement_comments') || ! Schema::hasColumn('announcement_comments', 'parent_id')) {
            abort(500, 'Announcement comments table is not ready. Please run php artisan migrate.');
        }
    }

    private function trimAnnouncementInput(Request $request): void
    {
        $request->merge([
            'subject' => trim((string) $request->input('subject', '')),
            'message' => trim((string) $request->input('message', '')),
        ]);
    }

    private function trimCommentInput(Request $request): void
    {
        $request->merge([
            'comment' => trim((string) $request->input('comment', '')),
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

    private function totalComments(): int
    {
        if (! Schema::hasTable('announcement_comments')) {
            return 0;
        }

        return (int) DB::table('announcement_comments')->count();
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

    private function markAnnouncementUnreadForStaff(int $announcementId, ?int $parentId = null): void
    {
        if (! Schema::hasTable('announcement_reads')) {
            return;
        }

        if ($parentId) {
            $staffUserId = $this->staffRecipientForComment($parentId);

            if ($staffUserId) {
                DB::table('announcement_reads')
                    ->where('announcement_id', $announcementId)
                    ->where('user_id', $staffUserId)
                    ->delete();
            }

            return;
        }

        DB::table('announcement_reads')
            ->where('announcement_id', $announcementId)
            ->delete();
    }

    private function staffRecipientForComment(int $commentId): ?int
    {
        if (! Schema::hasTable('announcement_comments') || ! Schema::hasTable('users')) {
            return null;
        }

        $visited = [];
        $currentId = $commentId;

        while ($currentId > 0 && ! in_array($currentId, $visited, true)) {
            $visited[] = $currentId;

            $comment = DB::table('announcement_comments')
                ->leftJoin('users', 'announcement_comments.user_id', '=', 'users.id')
                ->select(
                    'announcement_comments.parent_id',
                    'announcement_comments.user_id',
                    'users.role as author_role'
                )
                ->where('announcement_comments.id', $currentId)
                ->first();

            if (! $comment) {
                return null;
            }

            if (! in_array((string) ($comment->author_role ?? ''), self::ADMIN_ROLES, true)) {
                return (int) $comment->user_id;
            }

            $currentId = (int) ($comment->parent_id ?? 0);
        }

        return null;
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
