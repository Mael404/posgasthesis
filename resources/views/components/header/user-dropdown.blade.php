@php
    $user = auth()->user();
    $homeLabel = ($user?->role ?? null) === 'staff' ? 'Pump Station' : 'My Dashboard';
@endphp

@if ($user)
    <div class="relative" x-data="{
        dropdownOpen: false,
        toggleDropdown() {
            this.dropdownOpen = !this.dropdownOpen;
        },
        closeDropdown() {
            this.dropdownOpen = false;
        }
    }" @click.away="closeDropdown()">
        <button class="flex min-w-0 items-center text-gray-700 dark:text-gray-400" @click.prevent="toggleDropdown()" type="button">
            <span class="mr-3 flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-50 text-sm font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
                {{ strtoupper(substr($user->name ?? $user->email, 0, 1)) }}
            </span>

            <span class="mr-1 hidden max-w-[120px] truncate font-medium text-theme-sm sm:block">
                {{ $user->name }}
            </span>

            <svg class="h-5 w-5 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': dropdownOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>

        <div
            x-show="dropdownOpen"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute right-0 z-50 mt-[17px] flex w-[260px] flex-col rounded-lg border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
            style="display: none;"
        >
            <div>
                <span class="block font-medium text-gray-700 text-theme-sm dark:text-gray-400">{{ $user->name }}</span>
                <span class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</span>
                <span class="mt-2 inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
                    {{ $user->roleLabel() }}
                </span>
            </div>

            <ul class="flex flex-col gap-1 pt-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                <li>
                    <a href="{{ route($user->dashboardRouteName()) }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium text-gray-700 group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                        <span class="text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 3.25C4.25736 3.25 3.25 4.25736 3.25 5.5V8.99998C3.25 10.2426 4.25736 11.25 5.5 11.25H9C10.2426 11.25 11.25 10.2426 11.25 8.99998V5.5C11.25 4.25736 10.2426 3.25 9 3.25H5.5ZM12.75 5.5C12.75 4.25736 13.7574 3.25 15 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V8.99998C20.75 10.2426 19.7426 11.25 18.5 11.25H15C13.7574 11.25 12.75 10.2426 12.75 8.99998V5.5ZM5.5 12.75C4.25736 12.75 3.25 13.7574 3.25 15V18.5C3.25 19.7426 4.25736 20.75 5.5 20.75H9C10.2426 20.75 11.25 19.7427 11.25 18.5V15C11.25 13.7574 10.2426 12.75 9 12.75H5.5ZM15 12.75C13.7574 12.75 12.75 13.7574 12.75 15V18.5C12.75 19.7426 13.7574 20.75 15 20.75H18.5C19.7426 20.75 20.75 19.7427 20.75 18.5V15C20.75 13.7574 19.7426 12.75 18.5 12.75H15Z" fill="currentColor" />
                            </svg>
                        </span>
                        {{ $homeLabel }}
                    </a>
                </li>
            </ul>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="mt-3 flex w-full items-center gap-3 rounded-lg px-3 py-2 font-medium text-gray-700 group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                    <span class="text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </span>
                    Sign out
                </button>
            </form>
        </div>
    </div>
@endif
