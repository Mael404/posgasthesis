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
            $remarkNotifications = collect($this->shiftReadingRemarkNotifications($userId, $limit));
            $replyNotifications = collect($this->targetedAdminReplyNotifications($userId, $limit));
            $replyAnnouncementIds = $replyNotifications
                ->pluck('announcement_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            return $remarkNotifications
                ->merge($replyNotifications)
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

    public function unreadShiftReadingRemarksCount(int $userId): int
    {
        if ($userId < 1 || ! $this->canReadShiftReadingRemarks()) {
            return 0;
        }

        try {
            return (int) $this->unreadShiftReadingRemarksQuery($userId)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function shiftReadingRemarkNotifications(int $userId, int $limit): array
    {
        if (! $this->canReadShiftReadingRemarks()) {
            return [];
        }

        $hasAdminUser = Schema::hasColumn('shift_readings', 'admin_remarks_by') && Schema::hasTable('users');
        $timestampColumn = $this->shiftReadingRemarkTimestampColumn();

        $query = $this->unreadShiftReadingRemarksQuery($userId);

        if ($hasAdminUser) {
            $query->leftJoin('users as remark_admins', 'shift_readings.admin_remarks_by', '=', 'remark_admins.id');
        }

        return $query
            ->select(
                'shift_readings.id',
                Schema::hasColumn('shift_readings', 'period_from') ? 'shift_readings.period_from' : DB::raw('NULL as period_from'),
                Schema::hasColumn('shift_readings', 'period_to') ? 'shift_readings.period_to' : DB::raw('NULL as period_to'),
                'shift_readings.admin_remarks',
                $timestampColumn ? DB::raw("shift_readings.{$timestampColumn} as remark_timestamp") : DB::raw('NULL as remark_timestamp'),
                $hasAdminUser ? 'remark_admins.name as admin_name' : DB::raw("'Admin' as admin_name")
            )
            ->when($timestampColumn, fn ($query) => $query->orderByDesc("shift_readings.{$timestampColumn}"))
            ->when(! $timestampColumn && Schema::hasColumn('shift_readings', 'id'), fn ($query) => $query->orderByDesc('shift_readings.id'))
            ->limit($limit)
            ->get()
            ->map(function ($reading) {
                $createdAt = $reading->remark_timestamp ? Carbon::parse($reading->remark_timestamp) : null;
                $version = $createdAt ? $createdAt->timestamp : 0;

                return [
                    'id' => 'shift-reading-remark-' . (int) $reading->id . '-' . $version,
                    'type' => 'shift_reading_remark',
                    'actorName' => $reading->admin_name ?: 'Admin',
                    'summary' => 'left remarks on',
                    'context' => $this->formatPeriod($reading->period_from, $reading->period_to),
                    'body' => Str::limit((string) $reading->admin_remarks, 120),
                    'badge' => 'Admin Remark',
                    'time' => $createdAt ? $createdAt->diffForHumans() : '',
                    'url' => $this->shiftReadingUrl((int) $reading->id),
                    'sort_timestamp' => $createdAt ? $createdAt->timestamp : 0,
                ];
            })
            ->all();
    }

    private function unreadShiftReadingRemarksQuery(int $userId)
    {
        $query = DB::table('shift_readings')
            ->where('shift_readings.user_id', $userId)
            ->whereNotNull('shift_readings.admin_remarks')
            ->whereRaw("TRIM(COALESCE(shift_readings.admin_remarks, '')) <> ''");

        $timestampColumn = $this->shiftReadingRemarkTimestampColumn();

        return $query->where(function ($query) use ($timestampColumn) {
            $query->whereNull('shift_readings.admin_remarks_seen_at');

            if ($timestampColumn) {
                $query->orWhereColumn('shift_readings.admin_remarks_seen_at', '<', "shift_readings.{$timestampColumn}");
            }
        });
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

    private function canReadShiftReadingRemarks(): bool
    {
        return Schema::hasTable('shift_readings')
            && Schema::hasColumn('shift_readings', 'id')
            && Schema::hasColumn('shift_readings', 'user_id')
            && Schema::hasColumn('shift_readings', 'admin_remarks')
            && Schema::hasColumn('shift_readings', 'admin_remarks_seen_at');
    }

    private function shiftReadingRemarkTimestampColumn(): ?string
    {
        foreach (['admin_remarks_updated_at', 'updated_at', 'created_at'] as $column) {
            if (Schema::hasColumn('shift_readings', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function shiftReadingUrl(int $readingId): string
    {
        return Route::has('staff.readings.index')
            ? route('staff.readings.index', ['reading' => $readingId], false)
            : '/staff/shift-readings?reading=' . $readingId;
    }

    private function formatPeriod($from, $to): string
    {
        if (! $from && ! $to) {
            return 'Shift Reading';
        }

        $fromDate = $from ? Carbon::parse((string) $from)->format('M d, Y') : null;
        $toDate = $to ? Carbon::parse((string) $to)->format('M d, Y') : $fromDate;

        return $fromDate === $toDate ? (string) $fromDate : "{$fromDate} - {$toDate}";
    }
}
