<?php

namespace App\View\Components\header;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Throwable;

class NotificationDropdown extends Component
{
    public array $notifications = [];
    public int $unreadCount = 0;
    public ?string $notificationsUrl = null;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        if ((auth()->user()->role ?? null) !== 'vp_admin') {
            return;
        }

        $this->notifications = $this->getShiftReadingNotifications();
        $this->unreadCount = count($this->notifications);
        $this->notificationsUrl = Route::has('admin.shift-readings.notifications')
            ? route('admin.shift-readings.notifications', [], false)
            : null;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.header.notification-dropdown');
    }

    private function getShiftReadingNotifications(): array
    {
        try {
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
                ->limit(10)
                ->get()
                ->map(fn ($reading) => [
                    'id' => (int) $reading->id,
                    'staffName' => $reading->staff_name ?: 'Staff',
                    'period' => $this->formatPeriod($reading->period_from, $reading->period_to),
                    'status' => 'Pending Verification',
                    'time' => $reading->created_at
                        ? Carbon::parse($reading->created_at)->diffForHumans()
                        : '',
                    'url' => Route::has('admin.reconcile.index')
                        ? route('admin.reconcile.index', [], false)
                        : '/admin/reconciliation',
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function formatPeriod($from, $to): string
    {
        if (! $from && ! $to) {
            return 'No period selected';
        }

        $fromDate = $from ? date('M d, Y', strtotime((string) $from)) : null;
        $toDate = $to ? date('M d, Y', strtotime((string) $to)) : $fromDate;

        return $fromDate === $toDate ? (string) $fromDate : "{$fromDate} - {$toDate}";
    }
}
