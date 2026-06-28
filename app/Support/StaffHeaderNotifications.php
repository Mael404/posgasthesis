<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class StaffHeaderNotifications
{
    private const ADMIN_ROLES = ['admin', 'vp_admin', 'semiadmin'];

    public function get(int $userId, int $limit = 10): array
    {
        if ($userId < 1) {
            return [];
        }

        try {
            $replyNotifications = collect($this->targetedAdminReplyNotifications($userId, $limit));
            $replyAnnouncementIds = $replyNotifications
                ->pluck('announcement_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            return $replyNotifications
                ->merge($this->unreadAnnouncementNotifications($userId, $limit, $replyAnnouncementIds))
                ->sortByDesc('sort_timestamp')
                ->take($limit)
                ->map(function (array $notification) {
                    unset($notification['sort_timestamp'], $notification['announcement_id']);

                    return $notification;
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function targetedAdminReplyNotifications(int $userId, int $limit): array
    {
        if (
            ! Schema::hasTable('announcement_comments')
            || ! Schema::hasTable('announcement_reads')
            || ! Schema::hasTable('announcements')
            || ! Schema::hasTable('users')
            || ! Schema::hasColumn('announcement_comments', 'parent_id')
        ) {
            return [];
        }

        return DB::table('announcement_comments as replies')
            ->join('announcement_comments as parent_comments', 'replies.parent_id', '=', 'parent_comments.id')
            ->join('announcements', 'replies.announcement_id', '=', 'announcements.id')
            ->leftJoin('users as reply_users', 'replies.user_id', '=', 'reply_users.id')
            ->leftJoin('announcement_reads', function ($join) use ($userId) {
                $join->on('announcement_reads.announcement_id', '=', 'replies.announcement_id')
                    ->where('announcement_reads.user_id', '=', $userId);
            })
            ->select(
                'replies.id',
                'replies.announcement_id',
                'replies.comment',
                'replies.created_at',
                'announcements.subject',
                'reply_users.name as admin_name',
                'reply_users.role as admin_role',
                'announcement_reads.read_at'
            )
            ->where('parent_comments.user_id', $userId)
            ->whereIn('reply_users.role', self::ADMIN_ROLES)
            ->where(function ($query) {
                $query->whereNull('announcement_reads.read_at')
                    ->orWhereColumn('replies.created_at', '>', 'announcement_reads.read_at');
            })
            ->orderByDesc('replies.created_at')
            ->limit($limit)
            ->get()
            ->map(function ($reply) {
                $createdAt = $reply->created_at ? Carbon::parse($reply->created_at) : null;

                return [
                    'id' => 'staff-admin-reply-' . (int) $reply->id,
                    'type' => 'announcement_reply',
                    'announcement_id' => (int) $reply->announcement_id,
                    'actorName' => $reply->admin_name ?: 'Admin',
                    'summary' => 'replied to your comment on',
                    'context' => $reply->subject ?: 'Announcement',
                    'body' => Str::limit((string) $reply->comment, 120),
                    'badge' => 'Admin Reply',
                    'time' => $createdAt ? $createdAt->diffForHumans() : '',
                    'url' => $this->notificationUrl((int) $reply->announcement_id),
                    'sort_timestamp' => $createdAt ? $createdAt->timestamp : 0,
                ];
            })
            ->all();
    }

    private function unreadAnnouncementNotifications(int $userId, int $limit, array $excludeAnnouncementIds = []): array
    {
        if (
            ! Schema::hasTable('announcements')
            || ! Schema::hasTable('announcement_reads')
            || ! Schema::hasTable('users')
        ) {
            return [];
        }

        return DB::table('announcements')
            ->leftJoin('users', 'announcements.admin_id', '=', 'users.id')
            ->leftJoin('announcement_reads', function ($join) use ($userId) {
                $join->on('announcement_reads.announcement_id', '=', 'announcements.id')
                    ->where('announcement_reads.user_id', '=', $userId);
            })
            ->select(
                'announcements.id',
                'announcements.subject',
                'announcements.message',
                'announcements.created_at',
                'announcements.updated_at',
                'users.name as admin_name'
            )
            ->whereNull('announcement_reads.read_at')
            ->when(! empty($excludeAnnouncementIds), function ($query) use ($excludeAnnouncementIds) {
                $query->whereNotIn('announcements.id', $excludeAnnouncementIds);
            })
            ->orderByDesc('announcements.updated_at')
            ->orderByDesc('announcements.created_at')
            ->limit($limit)
            ->get()
            ->map(function ($announcement) {
                $createdAt = $announcement->updated_at
                    ? Carbon::parse($announcement->updated_at)
                    : ($announcement->created_at ? Carbon::parse($announcement->created_at) : null);

                return [
                    'id' => 'staff-announcement-' . (int) $announcement->id,
                    'type' => 'announcement',
                    'announcement_id' => (int) $announcement->id,
                    'actorName' => $announcement->admin_name ?: 'Admin',
                    'summary' => 'posted',
                    'context' => $announcement->subject ?: 'Announcement',
                    'body' => Str::limit((string) $announcement->message, 120),
                    'badge' => 'Announcement',
                    'time' => $createdAt ? $createdAt->diffForHumans() : '',
                    'url' => $this->notificationUrl((int) $announcement->id),
                    'sort_timestamp' => $createdAt ? $createdAt->timestamp : 0,
                ];
            })
            ->all();
    }

    private function notificationUrl(int $announcementId): string
    {
        return Route::has('staff.notifications.index')
            ? route('staff.notifications.index', ['announcement' => $announcementId], false)
            : '/staff/notifications?announcement=' . $announcementId;
    }
}
