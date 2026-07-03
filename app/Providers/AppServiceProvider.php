<?php

namespace App\Providers;

use App\View\Components\Header\NotificationDropdown;
use App\View\Components\Header\UserDropdown;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (class_exists(NotificationDropdown::class)) {
            Blade::component(NotificationDropdown::class, 'header.notification-dropdown');
        }

        if (class_exists(UserDropdown::class)) {
            Blade::component(UserDropdown::class, 'header.user-dropdown');
        }
    }
}
