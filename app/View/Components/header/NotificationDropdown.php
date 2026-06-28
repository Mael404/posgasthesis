<?php

namespace App\View\Components\header;

use App\Support\AdminHeaderNotifications;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

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

        $this->notifications = app(AdminHeaderNotifications::class)->get();
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
}
