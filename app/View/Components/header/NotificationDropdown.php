<?php

namespace App\View\Components\header;

use App\Support\AdminHeaderNotifications;
use App\Support\StaffHeaderNotifications;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class NotificationDropdown extends Component
{
    public array $notifications = [];
    public int $unreadCount = 0;
    public ?string $notificationsUrl = null;
    public string $title = 'Notifications';
    public string $summaryLabel = 'recent admin alerts';
    public string $emptyTitle = 'No new notifications';
    public string $emptySubtitle = 'Staff comments and pending readings will appear here.';
    public string $seenStorageKey = 'adminHeaderNotificationSeenKeys';
    public string $defaultUrl = '#';
    public array $viewLinks = [];

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $role = auth()->user()->role ?? null;

        if ($role === 'staff') {
            $this->configureStaffNotifications();

            return;
        }

        if ($role !== 'vp_admin') {
            return;
        }

        $this->configureAdminNotifications();
    }

    private function configureAdminNotifications(): void
    {
        $this->notifications = app(AdminHeaderNotifications::class)->get();
        $this->unreadCount = count($this->notifications);
        $this->notificationsUrl = Route::has('admin.shift-readings.notifications')
            ? route('admin.shift-readings.notifications', [], false)
            : null;
        $this->defaultUrl = Route::has('admin.reconcile.index')
            ? route('admin.reconcile.index', [], false)
            : '/admin/reconciliation';
        $this->viewLinks = [
            [
                'label' => 'Shift Readings',
                'url' => $this->defaultUrl,
            ],
            [
                'label' => 'Announcements',
                'url' => Route::has('admin.announcements.index')
                    ? route('admin.announcements.index', [], false)
                    : '/admin/announcements',
            ],
        ];
    }

    private function configureStaffNotifications(): void
    {
        $userId = (int) auth()->id();

        $this->notifications = app(StaffHeaderNotifications::class)->get($userId);
        $this->unreadCount = count($this->notifications);
        $this->notificationsUrl = Route::has('staff.notifications.header')
            ? route('staff.notifications.header', [], false)
            : null;
        $this->summaryLabel = 'staff alerts';
        $this->emptySubtitle = 'Announcements and admin replies will appear here.';
        $this->seenStorageKey = 'staffHeaderNotificationSeenKeys-' . $userId;
        $this->defaultUrl = Route::has('staff.notifications.index')
            ? route('staff.notifications.index', [], false)
            : '/staff/notifications';
        $this->viewLinks = [
            [
                'label' => 'Notifications',
                'url' => $this->defaultUrl,
            ],
        ];
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.header.notification-dropdown');
    }
}
