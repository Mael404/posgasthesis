{{-- Notification Dropdown Component --}}
@once
    <script>
        window.shiftReadingNotificationDropdown = function(initialNotifications, notificationUrl) {
            return {
                dropdownOpen: false,
                notifications: Array.isArray(initialNotifications) ? initialNotifications : [],
                notificationUrl: notificationUrl || null,
                lastSeenId: Number(localStorage.getItem('shiftReadingNotificationLastSeenId') || 0),

                init() {
                    this.refreshNotifications();

                    if (this.notificationUrl) {
                        setInterval(() => this.refreshNotifications(), 15000);
                    }
                },

                get latestId() {
                    return this.notifications.reduce((latest, notification) => {
                        return Math.max(latest, Number(notification.id || 0));
                    }, 0);
                },

                get unreadCount() {
                    return this.notifications.filter((notification) => {
                        return Number(notification.id || 0) > this.lastSeenId;
                    }).length;
                },

                get notifying() {
                    return this.unreadCount > 0;
                },

                toggleDropdown() {
                    this.dropdownOpen = !this.dropdownOpen;

                    if (this.dropdownOpen) {
                        this.markCurrentAsSeen();
                    }
                },

                closeDropdown() {
                    this.dropdownOpen = false;
                },

                markCurrentAsSeen() {
                    if (this.latestId > this.lastSeenId) {
                        this.lastSeenId = this.latestId;
                        localStorage.setItem('shiftReadingNotificationLastSeenId', String(this.latestId));
                    }
                },

                initials(name) {
                    return String(name || 'S')
                        .trim()
                        .split(/\s+/)
                        .slice(0, 2)
                        .map((part) => part.charAt(0).toUpperCase())
                        .join('') || 'S';
                },

                async refreshNotifications() {
                    if (!this.notificationUrl) {
                        return;
                    }

                    try {
                        const response = await fetch(this.notificationUrl, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const data = await response.json();

                        if (Array.isArray(data.notifications)) {
                            this.notifications = data.notifications;
                        }
                    } catch (error) {
                        console.warn('Could not refresh shift reading notifications.', error);
                    }
                },

                handleItemClick() {
                    this.markCurrentAsSeen();
                    this.closeDropdown();
                },

                handleViewAllClick() {
                    this.markCurrentAsSeen();
                    this.closeDropdown();
                },
            };
        };
    </script>
@endonce

<div
    class="relative"
    x-data="shiftReadingNotificationDropdown(@js($notifications), @js($notificationsUrl))"
    x-init="init()"
    @click.away="closeDropdown()"
>
    <!-- Notification Button -->
    <button
        class="relative flex items-center justify-center text-gray-500 transition-colors bg-white border border-gray-200 rounded-full hover:text-dark-900 h-11 w-11 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        @click="toggleDropdown()"
        type="button"
    >
        <!-- Notification Badge -->
        <span
            x-show="notifying"
            class="absolute -right-1 -top-1 z-10 flex min-w-[18px] items-center justify-center rounded-full bg-orange-500 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white"
            style="display: none;"
        >
            <span x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
            <span class="absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-50 animate-ping"></span>
        </span>

        <!-- Bell Icon -->
        <svg
            class="fill-current"
            width="20"
            height="20"
            viewBox="0 0 20 20"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                fill-rule="evenodd"
                clip-rule="evenodd"
                d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z"
                fill=""
            />
        </svg>
    </button>

    <!-- Dropdown Start -->
    <div
        x-show="dropdownOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="fixed inset-x-3 top-[72px] z-99999 mt-0 flex h-[390px] max-h-[calc(100vh-96px)] w-auto flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white p-0 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark lg:absolute lg:inset-x-auto lg:right-0 lg:top-auto lg:mt-[17px] lg:w-[361px]"
        style="display: none;"
    >
        <!-- Dropdown Header -->
        <div class="flex shrink-0 items-center justify-between border-b border-brand-100 bg-brand-50 px-4 py-4 dark:border-brand-800/60 dark:bg-brand-950/30">
            <div>
                <h5 class="text-lg font-semibold text-brand-900 dark:text-brand-100">Shift Reading Notifications</h5>
                <p class="text-xs font-medium text-brand-700 dark:text-brand-300">
                    <span x-text="notifications.length"></span>
                    pending for verification
                </p>
            </div>

            <button @click="closeDropdown()" class="rounded-full p-1 text-brand-700 transition-colors hover:bg-brand-100 hover:text-brand-900 dark:text-brand-300 dark:hover:bg-brand-900/50 dark:hover:text-brand-100" type="button">
                <svg
                    class="fill-current"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z"
                        fill=""
                    />
                </svg>
            </button>
        </div>

        <!-- Notification List -->
        <ul class="custom-scrollbar flex min-h-0 flex-1 flex-col overflow-y-auto px-2 py-2">
            <template x-if="notifications.length === 0">
                <li class="flex h-full flex-col items-center justify-center rounded-lg px-4 py-8 text-center">
                    <span class="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-950/40 dark:text-brand-300">
                        <svg class="fill-current" width="22" height="22" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 2.5C6.68629 2.5 4 5.18629 4 8.5V12.5L2.75 14.25V15.25H17.25V14.25L16 12.5V8.5C16 5.18629 13.3137 2.5 10 2.5Z" fill="currentColor"/>
                            <path d="M8 16.25C8.23444 16.9704 9.02944 17.5 10 17.5C10.9706 17.5 11.7656 16.9704 12 16.25H8Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">No pending shift readings</span>
                    <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">New staff submissions will appear here.</span>
                </li>
            </template>

            <template x-for="notification in notifications" :key="notification.id">
                <li @click="handleItemClick()">
                    <a
                        class="mb-2 flex gap-3 rounded-lg border border-gray-100 bg-white px-4 py-3 hover:bg-brand-25 dark:border-gray-800 dark:bg-transparent dark:hover:bg-brand-950/20"
                        :href="notification.url || '{{ route('admin.reconcile.index') }}'"
                    >
                        <span class="relative flex h-10 w-10 max-w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700 dark:bg-brand-950/40 dark:text-brand-200">
                            <span x-text="initials(notification.staffName)"></span>
                            <span class="absolute bottom-0 right-0 z-10 h-2.5 w-full max-w-2.5 rounded-full border-[1.5px] border-white bg-brand-500 dark:border-gray-900"></span>
                        </span>

                        <span class="block min-w-0">
                            <span class="mb-1.5 block text-theme-sm text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-800 dark:text-white/90" x-text="notification.staffName"></span>
                                submitted a shift reading for
                                <span class="font-medium text-gray-800 dark:text-white/90" x-text="notification.period"></span>
                            </span>

                            <span class="flex items-center gap-2 text-gray-500 text-theme-xs dark:text-gray-400">
                                <span x-text="notification.status"></span>
                                <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                <span x-text="notification.time"></span>
                            </span>
                        </span>
                    </a>
                </li>
            </template>
        </ul>

        <!-- View All Button -->
        <div class="shrink-0 border-t border-gray-100 bg-white px-3 py-3 dark:border-gray-800 dark:bg-gray-dark">
            <a
                href="{{ route('admin.reconcile.index') }}"
                class="flex justify-center rounded-lg border border-brand-200 bg-white p-3 text-theme-sm font-semibold text-brand-800 shadow-theme-xs hover:bg-brand-50 hover:text-brand-900 dark:border-brand-800 dark:bg-gray-800 dark:text-brand-200 dark:hover:bg-brand-950/30"
                @click="handleViewAllClick()"
            >
                View All Shift Readings
            </a>
        </div>
    </div>
    <!-- Dropdown End -->
</div>
