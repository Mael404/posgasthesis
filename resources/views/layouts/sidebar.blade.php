
@php
    use App\Helpers\MenuHelper;
    $menuGroups = MenuHelper::getMenuGroups();

    // Get current path
    $currentPath = request()->path();
@endphp

<aside id="sidebar"
    class="fixed left-0 top-0 z-[100000] mt-0 flex h-screen flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900"
    x-data="{
        openSubmenus: {},
        init() {
            // Auto-open Dashboard menu on page load
            this.initializeActiveMenus();
        },
        initializeActiveMenus() {
            @foreach ($menuGroups as $groupIndex => $menuGroup)
                @foreach ($menuGroup['items'] as $itemIndex => $item)
                    @if (isset($item['subItems']) && MenuHelper::isItemActive($item))
                        this.openSubmenus['{{ $groupIndex }}-{{ $itemIndex }}'] = true;
                    @endif
                @endforeach
            @endforeach
        },
        toggleSubmenu(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            const newState = !this.openSubmenus[key];

            // Close all other submenus when opening a new one
            if (newState) {
                this.openSubmenus = {};
            }

            this.openSubmenus[key] = newState;
        },
        isSubmenuOpen(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            return this.openSubmenus[key] || false;
        },
        isActive(path) {
            return window.location.pathname === path || '{{ $currentPath }}' === path.replace(/^\//, '');
        }
    }"
    :class="{
        'w-[290px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen || $store.sidebar.isHovered,
        'w-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
        'translate-x-0': $store.sidebar.isMobileOpen,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
    }"
    @mouseenter="if (!$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)">
    <!-- Logo Section -->
    <div class="pt-6 pb-5 flex"
        :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
        'xl:justify-center' :
        'justify-start'">
        <a href="/" class="inline-flex items-center justify-center transition-all duration-300">
            <img x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                class="h-auto w-[215px] max-w-full object-contain" src="{{ asset('images/jrs.png') }}"
                alt="JRM Fuel Station Logo" />
            <img x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen"
                class="h-8 w-10 object-contain" src="{{ asset('images/jrs.png') }}"
                alt="JRM Fuel Station Logo" />
        </a>
    </div>
    <!-- Navigation Menu -->
    <div class="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
        <nav class="mb-6">
            <div class="flex flex-col gap-4">
                @foreach ($menuGroups as $groupIndex => $menuGroup)
                    <div>
                        <!-- Menu Group Title -->
                        <h2 class="mb-4 text-xs uppercase flex leading-[20px] text-gray-400"
                            :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                            'lg:justify-center' : 'justify-start'">
                            <template
                                x-if="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
                                <span>{{ $menuGroup['title'] }}</span>
                            </template>
                            <template x-if="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path fill-rule="evenodd" clip-rule="evenodd" d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z" fill="currentColor"/>
                                </svg>
                            </template>
                        </h2>

                        <!-- Menu Items -->
                        <ul class="flex flex-col gap-1">
                            @foreach ($menuGroup['items'] as $itemIndex => $item)
                                {{-- Temporarily hide these sidebar links while keeping their pages available. --}}
                                @continue(auth()->user()?->role === 'vp_admin' && in_array($item['name'], ['Official Receipts', 'Printed Transactions'], true))
                                @continue(auth()->user()?->role === 'staff' && in_array($item['name'], ['Liters Tracking', 'Printed Transactions', 'Official Receipts', 'Billing'], true))
                                @php
                                    $itemIsActive = MenuHelper::isItemActive($item);
                                    $itemBadge = (int) ($item['badge'] ?? 0);
                                    $itemBadgeKey = $item['badgeKey'] ?? null;
                                    $itemBadgeShouldRender = $itemBadgeKey || $itemBadge > 0;
                                @endphp
                                <li>
                                    @if (isset($item['subItems']))
                                        <!-- Menu Item with Submenu -->
                                        <button @click="toggleSubmenu({{ $groupIndex }}, {{ $itemIndex }})"
                                            class="menu-item group w-full"
                                            :class="[
                                                (isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) || {{ $itemIsActive ? 'true' : 'false' }}) ?
                                                'menu-item-active' : 'menu-item-inactive',
                                                !$store.sidebar.isExpanded && !$store.sidebar.isHovered ?
                                                'xl:justify-center' : 'xl:justify-start'
                                            ]">

                                            <!-- Icon -->
                                            <span :class="(isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) || {{ $itemIsActive ? 'true' : 'false' }}) ?
                                                    'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>

                                            <!-- Text -->
                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="menu-item-text flex min-w-0 flex-1 items-center gap-2">
                                                <span class="truncate">{{ $item['name'] }}</span>
                                                @if ($itemBadgeShouldRender)
                                                    <span class="sidebar-notification-badge {{ $itemBadge > 0 ? '' : 'hidden' }}" @if ($itemBadgeKey) data-sidebar-badge="{{ $itemBadgeKey }}" @endif aria-live="polite">
                                                        {{ $itemBadge }}
                                                    </span>
                                                @endif
                                                @if (!empty($item['new']))
                                                    <span class="absolute right-10"
                                                        :class="isActive('{{ $item['path'] ?? '' }}') ?
                                                            'menu-dropdown-badge menu-dropdown-badge-active' :
                                                            'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                                        new
                                                    </span>
                                                @endif
                                            </span>

                                            <!-- Chevron Down Icon -->
                                            <svg x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="ml-auto w-5 h-5 transition-transform duration-200"
                                                :class="{
                                                    'rotate-180 text-brand-500': isSubmenuOpen({{ $groupIndex }},
                                                        {{ $itemIndex }})
                                                }"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>

                                        <!-- Submenu -->
                                        <div x-show="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) && ($store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen)">
                                            <ul class="mt-2 space-y-1 ml-9">
                                                @foreach ($item['subItems'] as $subItem)
                                                    @continue(auth()->user()?->role === 'vp_admin' && $subItem['name'] === 'Liters Movement & Inventory')
                                                    @php
                                                        $subItemIsActive = MenuHelper::isItemActive($subItem);
                                                        $subItemBadge = (int) ($subItem['badge'] ?? 0);
                                                        $subItemBadgeKey = $subItem['badgeKey'] ?? null;
                                                        $subItemBadgeShouldRender = $subItemBadgeKey || $subItemBadge > 0;
                                                    @endphp
                                                    <li>
                                                        <a href="{{ $subItem['path'] }}"
                                                            class="menu-dropdown-item {{ $subItemIsActive ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive' }}">
                                                            {{ $subItem['name'] }}
                                                            <span class="flex items-center gap-1 ml-auto">
                                                                @if ($subItemBadgeShouldRender)
                                                                    <span class="sidebar-notification-badge {{ $subItemBadge > 0 ? '' : 'hidden' }}" @if ($subItemBadgeKey) data-sidebar-badge="{{ $subItemBadgeKey }}" @endif aria-live="polite">
                                                                        {{ $subItemBadge }}
                                                                    </span>
                                                                @endif
                                                                @if (!empty($subItem['new']))
                                                                    <span
                                                                        :class="isActive('{{ $subItem['path'] }}') ?
                                                                            'menu-dropdown-badge menu-dropdown-badge-active' :
                                                                            'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                                                        new
                                                                    </span>
                                                                @endif
                                                                @if (!empty($subItem['pro']))
                                                                    <span
                                                                        :class="isActive('{{ $subItem['path'] }}') ?
                                                                            'menu-dropdown-badge-pro menu-dropdown-badge-pro-active' :
                                                                            'menu-dropdown-badge-pro menu-dropdown-badge-pro-inactive'">
                                                                        pro
                                                                    </span>
                                                                @endif
                                                            </span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @else
                                        <!-- Simple Menu Item -->
                                        <a href="{{ $item['path'] }}" class="menu-item group"
                                            :class="[
                                                {{ $itemIsActive ? 'true' : 'false' }} ? 'menu-item-active' :
                                                'menu-item-inactive',
                                                (!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                                                'xl:justify-center' :
                                                'justify-start'
                                            ]">

                                            <!-- Icon -->
                                            <span
                                                :class="{{ $itemIsActive ? 'true' : 'false' }} ? 'menu-item-icon-active' :
                                                    'menu-item-icon-inactive'">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>

                                            <!-- Text -->
                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="menu-item-text flex min-w-0 flex-1 items-center gap-2">
                                                <span class="truncate">{{ $item['name'] }}</span>
                                                @if ($itemBadgeShouldRender)
                                                    <span class="sidebar-notification-badge ml-auto {{ $itemBadge > 0 ? '' : 'hidden' }}" @if ($itemBadgeKey) data-sidebar-badge="{{ $itemBadgeKey }}" @endif aria-live="polite">
                                                        {{ $itemBadge }}
                                                    </span>
                                                @endif
                                                @if (!empty($item['new']))
                                                    <span
                                                        class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-brand-500 text-white">
                                                        new
                                                    </span>
                                                @endif
                                            </span>
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        <!-- Sidebar Widget -->
        <div x-data x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" x-transition class="mt-auto">
            @include('layouts.sidebar-widget')
        </div>

    </div>
</aside>

@once
    @push('scripts')
        <script>
            (() => {
                const endpoint = @json(route('sidebar.badges', [], false));
                const intervalMs = 2000;

                const updateBadges = (badges = {}) => {
                    document.querySelectorAll('[data-sidebar-badge]').forEach((badge) => {
                        const key = badge.dataset.sidebarBadge;

                        if (!Object.prototype.hasOwnProperty.call(badges, key)) {
                            return;
                        }

                        const count = Number.parseInt(badges[key], 10) || 0;
                        badge.textContent = count;
                        badge.classList.toggle('hidden', count < 1);
                    });
                };

                const fetchBadges = async () => {
                    try {
                        const response = await fetch(endpoint, {
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        updateBadges(payload.badges || {});
                    } catch (error) {
                        // Keep the sidebar quiet if the network briefly fails.
                    }
                };

                document.addEventListener('DOMContentLoaded', () => {
                    fetchBadges();

                    const timer = window.setInterval(fetchBadges, intervalMs);
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) {
                            fetchBadges();
                        }
                    });
                    window.addEventListener('beforeunload', () => window.clearInterval(timer));
                });
            })();
        </script>
    @endpush
@endonce
