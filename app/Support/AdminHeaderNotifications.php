<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdminHeaderNotifications
{
    private const ADMIN_ROLES = ['admin', 'vp_admin', 'semiadmin'];

    public function get(int $limit = 10): array
    {
        try {
            return collect([
                ...$this->shiftReadingNotifications($limit),
                ...$this->announcementCommentNotifications($limit),
            ])
                ->sortByDesc('sort_timestamp')
                ->take($limit)
                ->map(function (array $notification) {
                    unset($notification['sort_timestamp']);

                    return $notification;
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function shiftReadingNotifications(int $limit): array
    {
        if (! Schema::hasTable('shift_readings') || ! Schema::hasTable('users')) {
            return [];
        }

        return DB::table('shift_readings')
            ->join('users', 'shift_readings.user_id', '=', 'users.id')
            ->select(
                'shift_readings.id',
                'shift_readings.period_from',
                'shift_readings.period_to',
                'shift_readings.status',
                'shift_readings.created_at',
                'users.name as staff_name'
            )
            ->where('shift_readings.status', 'Pending')
            ->orderByDesc('shift_readings.created_at')
            ->limit($limit)
            ->get()
            ->map(function ($reading) {
                $createdAt = $reading->created_at ? Carbon::parse($reading->created_at) : null;

                return [
                    'id' => 'shift-reading-' . (int) $reading->id,
                    'type' => 'shift_reading',
                    'actorName' => $reading->staff_name ?: 'Staff',
                    'summary' => 'submitted a shift reading',
                    'context' => $this->formatPeriod($reading->period_from, $reading->period_to),
                    'body' => 'Pending Verification',
                    'badge' => 'Shift Reading',
                    'time' => $createdAt ? $createdAt->diffForHumans() : '',
                    'url' => Route::has('admin.reconcile.index')
                        ? route('admin.reconcile.index', [], false)
                        : '/admin/reconciliation',
                    'sort_timestamp' => $createdAt ? $createdAt->timestamp : 0,
                ];
            })
            ->all();
    }

    private function announcementCommentNotifications(int $limit): array
    {
        if (
            ! Schema::hasTable('announcement_comments')
            || ! Schema::hasTable('announcements')
            || ! Schema::hasTable('users')
        ) {
            return [];
        }

        $hasParentColumn = Schema::hasColumn('announcement_comments', 'parent_id');

        return DB::table('announcement_comments')
            ->join('announcements', 'announcement_comments.announcement_id', '=', 'announcements.id')
            ->leftJoin('users', 'announcement_comments.user_id', '=', 'users.id')
            ->select(
                'announcement_comments.id',
                'announcement_comments.announcement_id',
                $hasParentColumn ? 'announcement_comments.parent_id' : DB::raw('NULL as parent_id'),
                'announcement_comments.comment',
                'announcement_comments.created_at',
                'announcements.subject',
                'users.name as actor_name',
                'users.role as actor_role'
            )
            ->where(function ($query) {
                $query->whereNull('users.role')
                    ->orWhereNotIn('users.role', self::ADMIN_ROLES);
            })
            ->orderByDesc('announcement_comments.created_at')
            ->limit($limit)
            ->get()
            ->map(function ($comment) {
                $createdAt = $comment->created_at ? Carbon::parse($comment->created_at) : null;
                $announcementUrl = Route::has('admin.announcements.index')
                    ? route('admin.announcements.index', ['announcement' => (int) $comment->announcement_id], false)
                    : '/admin/announcements';

                return [
                    'id' => 'announcement-comment-' . (int) $comment->id,
                    'type' => 'announcement_comment',
                    'actorName' => $comment->actor_name ?: 'Staff',
                    'summary' => $comment->parent_id ? 'replied on' : 'commented on',
                    'context' => $comment->subject ?: 'Announcement',
                    'body' => Str::limit((string) $comment->comment, 120),
                    'badge' => $comment->parent_id ? 'Reply' : 'Comment',
                    'time' => $createdAt ? $createdAt->diffForHumans() : '',
                    'url' => $announcementUrl,
                    'sort_timestamp' => $createdAt ? $createdAt->timestamp : 0,
                ];
            })
            ->all();
    }

    private function formatPeriod($from, $to): string
    {
        if (! $from && ! $to) {
            return 'No period selected';
        }

        $fromDate = $from ? Carbon::parse((string) $from)->format('M d, Y') : null;
        $toDate = $to ? Carbon::parse((string) $to)->format('M d, Y') : $fromDate;

        return $fromDate === $toDate ? (string) $fromDate : "{$fromDate} - {$toDate}";
    }
}
