@extends('layouts.app')

@section('content')
    @php
        $activeStaff = collect($activeStaff ?? []);
        $inactiveStaff = collect($inactiveStaff ?? []);
        $allStaff = $activeStaff->concat($inactiveStaff)->unique('id')->values();
        $activeTab = in_array(request('staff_tab'), ['active', 'inactive'], true) ? request('staff_tab') : 'active';

        $initialsFor = function ($staff) {
            $firstName = trim((string) ($staff->first_name ?? ''));
            $lastName = trim((string) ($staff->last_name ?? ''));

            if ($firstName !== '' || $lastName !== '') {
                return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
            }

            $parts = preg_split('/\s+/', trim((string) ($staff->name ?? '')));

            return strtoupper(substr($parts[0] ?? 'S', 0, 1) . substr($parts[1] ?? '', 0, 1));
        };

        $roleLabel = function ($role) {
            return match ($role) {
                'semiadmin' => 'Semi Admin',
                'staff' => 'Staff',
                default => ucwords(str_replace('_', ' ', (string) $role)),
            };
        };

        $staffPayload = $allStaff->map(function ($staff) use ($roleLabel) {
            $name = trim((string) ($staff->name ?? '')) ?: trim((string) (($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')));
            $employeeId = $staff->employee_id ?? 'N/A';
            $role = $roleLabel($staff->role ?? 'staff');
            $email = $staff->email ?? '';
            $phone = $staff->phone ?? '';

            return [
                'id' => (int) $staff->id,
                'name' => $name ?: 'Staff #' . $staff->id,
                'employee_id' => $employeeId,
                'role' => $staff->role ?? 'staff',
                'role_label' => $role,
                'email' => $email,
                'phone' => $phone,
                'status' => $staff->status ?? 'active',
                'search' => strtolower($name . ' ' . $employeeId . ' ' . $role . ' ' . $email . ' ' . $phone),
            ];
        })->values();

        $latestStaff = $allStaff->sortByDesc('id')->first();
        $latestEmployeeId = $latestStaff->employee_id ?? 'N/A';

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $resetIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-warning-200 bg-warning-50 text-warning-700 shadow-theme-xs transition hover:border-warning-300 hover:bg-warning-100 dark:border-warning-500/25 dark:bg-warning-500/10 dark:text-warning-400 dark:hover:bg-warning-500/15';
        $deactivateIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-error-200 bg-error-50 text-error-600 shadow-theme-xs transition hover:border-error-300 hover:bg-error-100 dark:border-error-500/25 dark:bg-error-500/10 dark:text-error-400 dark:hover:bg-error-500/15';
        $reactivateIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-success-200 bg-success-50 text-success-600 shadow-theme-xs transition hover:border-success-300 hover:bg-success-100 dark:border-success-500/25 dark:bg-success-500/10 dark:text-success-500 dark:hover:bg-success-500/15';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
    @endphp

    <x-common.page-breadcrumb pageTitle="Staff Management" />

    <div
        x-data="staffManagementModule({
            activeTab: @js($activeTab),
            staff: @js($staffPayload),
            csrfToken: @js(csrf_token()),
            storeUrl: @js(route('staffs.store')),
            toggleStatusUrl: @js(route('staffs.toggleStatus')),
            resetPasswordUrl: @js(route('staffs.resetPassword'))
        })"
        x-init="init()"
        class="space-y-6">
        <style>[x-cloak] { display: none !important; }</style>

        <div
            x-show="toast.open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="fixed right-4 w-[calc(100%-2rem)] max-w-sm rounded-xl border border-white/10 p-4 text-white shadow-theme-lg sm:right-6"
            style="top: 5.5rem; z-index: 100300; background-color: #047857;"
            role="status"
            aria-live="polite">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15 text-white">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6.75 12.25 3.25 3.25 7.25-7.25" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-theme-sm font-semibold text-white">Success</p>
                    <p class="mt-1 text-theme-sm text-white/90" x-text="toast.message"></p>
                </div>
                <button type="button" x-on:click="closeToast()" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white/80 transition hover:bg-white/10 hover:text-white" aria-label="Close success message">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>
        </div>

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Staff Management</h1>
                </div>

                <div class="flex flex-wrap gap-2 xl:justify-end">
                    <button type="button" x-on:click="switchTab('inactive')" class="{{ $secondaryButtonClass }}">
                        Inactive Staff
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $inactiveStaff->count() }}</span>
                    </button>
                    <button type="button" x-on:click="openAdd()" class="{{ $primaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        Add Staff
                    </button>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <article class="rounded-xl border border-l-4 border-gray-200 border-l-brand-500 bg-brand-50/40 p-4 shadow-theme-xs dark:border-gray-800 dark:border-l-brand-400 dark:bg-brand-500/[0.05]">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M10 10.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5ZM17.5 7.75v5M15 10.25h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <div>
                        <p class="{{ $labelClass }}">Active Staff</p>
                        <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($activeStaff->count()) }}</p>
                    </div>
                </div>
            </article>
            <article class="rounded-xl border border-l-4 border-gray-200 border-l-error-500 bg-error-50/40 p-4 shadow-theme-xs dark:border-gray-800 dark:border-l-error-500 dark:bg-error-500/[0.05]">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-error-100 text-error-600 dark:bg-error-500/15 dark:text-error-400">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M10 10.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5ZM16 8.5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <div>
                        <p class="{{ $labelClass }}">Inactive Staff</p>
                        <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($inactiveStaff->count()) }}</p>
                    </div>
                </div>
            </article>
            <article class="rounded-xl border border-l-4 border-gray-200 border-l-warning-500 bg-warning-50/40 p-4 shadow-theme-xs dark:border-gray-800 dark:border-l-warning-400 dark:bg-warning-500/[0.05]">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7.75 4.75h8.5A1.75 1.75 0 0 1 18 6.5v11a1.75 1.75 0 0 1-1.75 1.75h-8.5A1.75 1.75 0 0 1 6 17.5v-11a1.75 1.75 0 0 1 1.75-1.75ZM9 8h6M9 12h6M9 16h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="{{ $labelClass }}">Latest Employee ID</p>
                        <p class="mt-2 truncate text-title-sm font-bold text-gray-800 dark:text-white/90">{{ $latestEmployeeId }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="{{ $cardClass }} p-2 md:p-2">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button type="button" x-on:click="switchTab('active')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'active' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Active Staff
                </button>
                <button type="button" x-on:click="switchTab('inactive')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'inactive' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Inactive / Archived
                </button>
            </div>
        </section>

        <section x-show="activeTab === 'active'" x-cloak class="{{ $cardClass }}">
            <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(260px,380px)] lg:items-end">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Active Staff</h2>
                </div>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Search active staff</span>
                    <input type="search" x-model.debounce.150ms="activeSearch" class="mt-1.5 {{ $inputClass }}" placeholder="Name, employee ID, phone, or email">
                </label>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[920px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[30%]">
                            <col class="w-[13%]">
                            <col class="w-[27%]">
                            <col class="w-[12%]">
                            <col class="w-[18%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Name</th>
                                <th class="{{ $thClass }}">Role</th>
                                <th class="{{ $thClass }}">Contact Info</th>
                                <th class="{{ $thClass }}">Status</th>
                                <th class="{{ $thClass }}">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($activeStaff as $staff)
                                <tr x-show="staffMatches('{{ $staff->id }}', activeSearch)">
                                    <td class="{{ $tdPrimaryClass }}">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-500 text-theme-sm font-semibold text-white">
                                                {{ $initialsFor($staff) ?: 'S' }}
                                            </span>
                                            <div class="min-w-0">
                                                <div class="truncate">{{ $staff->name ?: trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')) }}</div>
                                                <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">ID: {{ $staff->employee_id ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-0.5 text-theme-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ $roleLabel($staff->role ?? 'staff') }}</span>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <svg class="size-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5.5 6.75A2.25 2.25 0 0 1 7.75 4.5h8.5a2.25 2.25 0 0 1 2.25 2.25v10.5a2.25 2.25 0 0 1-2.25 2.25h-8.5a2.25 2.25 0 0 1-2.25-2.25V6.75Z" stroke="currentColor" stroke-width="1.6"/><path d="m7.25 8 4.75 4 4.75-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <span class="truncate">{{ $staff->email }}</span>
                                        </div>
                                        <div class="mt-1 flex min-w-0 items-center gap-2 text-theme-xs text-gray-500 dark:text-gray-400">
                                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7.25 5.25 9.5 9l-1.75 1.5c.95 1.95 2.3 3.3 4.25 4.25l1.5-1.75 3.75 2.25-.75 3.25c-.2.85-.95 1.5-1.85 1.5A10.65 10.65 0 0 1 4 9.35c0-.9.65-1.65 1.5-1.85l1.75-.25Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <span class="truncate">{{ $staff->phone ?: '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full bg-success-50 px-2.5 py-0.5 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">Active</span>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="flex min-w-[5.5rem] items-center gap-3">
                                            <button type="button" x-on:click="openReset('{{ $staff->id }}')" class="{{ $resetIconButtonClass }}" title="Reset password" aria-label="Reset password for {{ $staff->name }}">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14.5 10.25a4 4 0 1 0-3.25 3.93V17h2.5v2.25H16V21h2.25v-4.25l-3.75-3.75Zm-7.25 0h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                            <button type="button" x-on:click="toggleStatus('{{ $staff->id }}', 'inactive')" class="{{ $deactivateIconButtonClass }}" title="Deactivate account" aria-label="Deactivate {{ $staff->name }}">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M10 10.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5ZM16 8.5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No active staff accounts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section x-show="activeTab === 'inactive'" x-cloak class="{{ $cardClass }}">
            <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(260px,380px)] lg:items-end">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Inactive / Archived</h2>
                    <p class="mt-1 {{ $captionClass }}">These accounts cannot access the system until reactivated.</p>
                </div>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Search inactive staff</span>
                    <input type="search" x-model.debounce.150ms="inactiveSearch" class="mt-1.5 {{ $inputClass }}" placeholder="Name, employee ID, phone, or email">
                </label>
            </div>

            <div class="mb-5 rounded-xl border border-l-4 border-gray-200 border-l-error-500 bg-error-50/40 p-4 text-theme-sm text-error-700 dark:border-gray-800 dark:border-l-error-500 dark:bg-error-500/[0.05] dark:text-error-300">
                Inactive staff accounts are blocked by the login flow because only active users can sign in.
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[880px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[32%]">
                            <col class="w-[16%]">
                            <col class="w-[30%]">
                            <col class="w-[22%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Name</th>
                                <th class="{{ $thClass }}">Last Role</th>
                                <th class="{{ $thClass }}">Contact Info</th>
                                <th class="{{ $thClass }}">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($inactiveStaff as $staff)
                                <tr x-show="staffMatches('{{ $staff->id }}', inactiveSearch)" class="bg-gray-50/50 dark:bg-white/[0.01]">
                                    <td class="{{ $tdPrimaryClass }}">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-400 text-theme-sm font-semibold text-white dark:bg-gray-700">
                                                {{ $initialsFor($staff) ?: 'S' }}
                                            </span>
                                            <div class="min-w-0">
                                                <div class="truncate">{{ $staff->name ?: trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')) }}</div>
                                                <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">ID: {{ $staff->employee_id ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="{{ $tdClass }}">{{ $roleLabel($staff->role ?? 'staff') }}</td>
                                    <td class="{{ $tdClass }}">
                                        <div class="truncate">{{ $staff->email }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $staff->phone ?: '-' }}</div>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex rounded-full bg-error-50 px-2.5 py-0.5 text-theme-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Inactive</span>
                                            <button type="button" x-on:click="toggleStatus('{{ $staff->id }}', 'active')" class="{{ $reactivateIconButtonClass }}" title="Reactivate account" aria-label="Reactivate {{ $staff->name }}">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M10 10.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5ZM16 11l2 2 4-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No inactive staff accounts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div x-show="modal === 'add'" x-cloak x-on:keydown.escape.window="closeModal()" class="fixed inset-0 z-[100010] flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" x-on:click="closeModal()"></div>
            <div class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <button type="button" x-on:click="closeModal()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400" aria-label="Close add staff modal">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>

                <div class="border-b border-gray-100 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-white/[0.02] md:p-6">
                    <p class="{{ $labelClass }}">New Account</p>
                    <h2 class="mt-1 pr-10 text-xl font-semibold text-gray-800 dark:text-white/90">Add New Staff</h2>
                </div>

                <form x-on:submit.prevent="submitStaff()" class="max-h-[78vh] overflow-y-auto p-5 custom-scrollbar md:p-6">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <span>First Name</span>
                            <input type="text" x-model.trim="staffForm.first_name" class="mt-1.5 {{ $inputClass }}" required autocomplete="given-name">
                        </label>
                        <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <span>Last Name</span>
                            <input type="text" x-model.trim="staffForm.last_name" class="mt-1.5 {{ $inputClass }}" required autocomplete="family-name">
                        </label>
                        <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <span>Email Address</span>
                            <input type="email" x-model.trim="staffForm.email" class="mt-1.5 {{ $inputClass }}" required autocomplete="email">
                        </label>
                        <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <span>Phone Number</span>
                            <input type="text" x-model.trim="staffForm.phone" class="mt-1.5 {{ $inputClass }}" placeholder="0917..." autocomplete="tel">
                        </label>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        <div class="rounded-xl border border-l-4 border-gray-200 border-l-brand-500 bg-brand-50/40 p-4 dark:border-gray-800 dark:border-l-brand-400 dark:bg-brand-500/[0.05]">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M10 10.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5ZM17.5 7.75v5M15 10.25h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <div>
                                    <p class="{{ $labelClass }}">Role</p>
                                    <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90">Pump Attendant / Staff</p>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-l-4 border-gray-200 border-l-warning-500 bg-warning-50/40 p-4 dark:border-gray-800 dark:border-l-warning-400 dark:bg-warning-500/[0.05]">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14.5 10.25a4 4 0 1 0-3.25 3.93V17h2.5v2.25H16V21h2.25v-4.25l-3.75-3.75Zm-7.25 0h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <div>
                                    <p class="{{ $labelClass }}">Default Password</p>
                                    <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90">firstname + lastname</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" x-on:click="closeModal()" class="{{ $secondaryButtonClass }}">Cancel</button>
                        <button type="submit" class="{{ $primaryButtonClass }}" :disabled="savingStaff">
                            <span x-text="savingStaff ? 'Creating...' : 'Create Account'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="modal === 'reset'" x-cloak x-on:keydown.escape.window="closeModal()" class="fixed inset-0 z-[100010] flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" x-on:click="closeModal()"></div>
            <div class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <button type="button" x-on:click="closeModal()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400" aria-label="Close password reset modal">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>

                <div class="border-b border-gray-100 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-white/[0.02] md:p-6">
                    <p class="{{ $labelClass }}">Credential Reset</p>
                    <h2 class="mt-1 pr-10 text-xl font-semibold text-gray-800 dark:text-white/90">Reset Password</h2>
                    <p class="mt-1 {{ $captionClass }}" x-text="selectedStaff ? selectedStaff.name : 'Select a staff account'"></p>
                </div>

                <form x-on:submit.prevent="submitPasswordReset()" class="p-5 md:p-6">
                    <div class="space-y-4">
                        <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <span>New Password</span>
                            <input type="password" x-model="passwordForm.new_password" class="mt-1.5 {{ $inputClass }}" required minlength="6" autocomplete="new-password">
                        </label>
                        <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <span>Confirm Password</span>
                            <input type="password" x-model="passwordForm.confirm_password" class="mt-1.5 {{ $inputClass }}" required minlength="6" autocomplete="new-password">
                        </label>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" x-on:click="closeModal()" class="{{ $secondaryButtonClass }}">Cancel</button>
                        <button type="submit" class="{{ $primaryButtonClass }}" :disabled="savingPassword">
                            <span x-text="savingPassword ? 'Saving...' : 'Save Password'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            x-show="messageDialog.open"
            x-cloak
            x-on:keydown.escape.window="closeMessageDialog()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4"
            style="z-index: 100250;">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md" x-on:click="closeMessageDialog()"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <button type="button" x-on:click="closeMessageDialog()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400" aria-label="Close message">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>

                <div class="p-6 pr-14">
                    <div class="flex items-start gap-4">
                        <span
                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                            :class="messageDialog.type === 'info'
                                ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'
                                : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8.25h.01M11.25 11.25h1.5v5h-1.5z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="messageDialog.title"></h2>
                            <p class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="messageDialog.message"></p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" x-on:click="closeMessageDialog()" class="{{ $primaryButtonClass }}">OK</button>
                    </div>
                </div>
            </div>
        </div>

        <div
            x-show="confirmation.open"
            x-cloak
            x-on:keydown.escape.window="resolveConfirmation(false)"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4"
            style="z-index: 100260;">
            <div class="fixed inset-0 bg-gray-950/85 backdrop-blur-md" x-on:click="resolveConfirmation(false)"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span
                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                            :class="confirmation.tone === 'danger'
                                ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'
                                : confirmation.tone === 'success'
                                    ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500'
                                    : confirmation.tone === 'warning'
                                        ? 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400'
                                        : 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8.5v4.25M12 16h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="confirmation.title"></h2>
                            <p class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="confirmation.message"></p>
                            <p x-show="confirmation.details" x-cloak class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="confirmation.details"></p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" x-on:click="resolveConfirmation(false)" class="{{ $secondaryButtonClass }}" x-text="confirmation.cancelText"></button>
                        <button
                            type="button"
                            x-ref="confirmPrimary"
                            x-on:click="resolveConfirmation(true)"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition disabled:cursor-not-allowed disabled:opacity-60"
                            :class="confirmation.tone === 'danger'
                                ? 'bg-error-500 hover:bg-error-600'
                                : confirmation.tone === 'success'
                                    ? 'bg-success-500 hover:bg-success-600'
                                    : confirmation.tone === 'warning'
                                        ? 'bg-warning-500 hover:bg-warning-600'
                                        : 'bg-brand-500 hover:bg-brand-600'"
                            x-text="confirmation.confirmText"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function staffManagementModule(config) {
            return {
                activeTab: config.activeTab || 'active',
                staff: config.staff || [],
                staffById: {},
                activeSearch: '',
                inactiveSearch: '',
                modal: null,
                selectedStaff: null,
                toast: { open: false, message: '', timer: null },
                messageDialog: { open: false, type: 'error', title: '', message: '' },
                confirmation: {
                    open: false,
                    title: '',
                    message: '',
                    details: '',
                    confirmText: 'Confirm',
                    cancelText: 'Cancel',
                    tone: 'brand',
                    resolver: null
                },
                staffForm: {},
                passwordForm: {},
                savingStaff: false,
                savingPassword: false,
                reloadTimer: null,
                feedbackClosing: false,

                init() {
                    this.staffById = Object.fromEntries(this.staff.map(staff => [String(staff.id), staff]));
                    this.resetStaffForm();
                    this.resetPasswordForm();
                    this.showStoredToast();
                },

                switchTab(tab) {
                    this.activeTab = tab;
                    const url = new URL(window.location.href);
                    url.searchParams.set('staff_tab', tab);
                    window.history.replaceState({}, '', url);
                },

                staffMatches(id, term) {
                    const staff = this.staffById[String(id)];
                    const value = String(term || '').toLowerCase().trim();
                    return !value || (staff && staff.search.includes(value));
                },

                openAdd() {
                    this.resetStaffForm();
                    this.modal = 'add';
                },

                openReset(id) {
                    const staff = this.staffById[String(id)];
                    if (!staff) return;
                    this.selectedStaff = staff;
                    this.resetPasswordForm();
                    this.passwordForm.id = staff.id;
                    this.modal = 'reset';
                },

                closeModal() {
                    if (this.confirmation.open || this.messageDialog.open || this.feedbackClosing) return;

                    this.modal = null;
                    this.selectedStaff = null;
                },

                resetStaffForm() {
                    this.staffForm = {
                        first_name: '',
                        last_name: '',
                        email: '',
                        phone: ''
                    };
                },

                resetPasswordForm() {
                    this.passwordForm = {
                        id: '',
                        new_password: '',
                        confirm_password: ''
                    };
                },

                async submitStaff() {
                    if (!this.staffForm.first_name || !this.staffForm.last_name || !this.staffForm.email) {
                        this.showNotice('error', 'First name, last name, and email address are required.');
                        return;
                    }

                    const confirmed = await this.askConfirmation({
                        title: 'Create Staff Account',
                        message: 'Create this staff account with the default password?',
                        confirmText: 'Create Account',
                        tone: 'brand'
                    });
                    if (!confirmed) return;

                    this.savingStaff = true;
                    const data = await this.postJson(config.storeUrl, this.staffForm);
                    this.savingStaff = false;

                    if (!data.success) {
                        this.showNotice('error', data.message || 'Unable to create staff account.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Staff account created.');
                },

                async toggleStatus(id, status) {
                    const staff = this.staffById[String(id)];
                    const action = status === 'active' ? 'Reactivate' : 'Deactivate';
                    const confirmed = await this.askConfirmation({
                        title: action + ' Staff',
                        message: 'Do you want to ' + action.toLowerCase() + ' ' + (staff ? staff.name : 'this staff account') + '?',
                        confirmText: action,
                        tone: status === 'active' ? 'success' : 'danger'
                    });
                    if (!confirmed) return;

                    const data = await this.postJson(config.toggleStatusUrl, { id, status });

                    if (!data.success) {
                        this.showNotice('error', data.message || 'Unable to update staff status.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Staff status updated.');
                },

                async submitPasswordReset() {
                    if (!this.passwordForm.id || !this.passwordForm.new_password) {
                        this.showNotice('error', 'Enter the new password.');
                        return;
                    }

                    if (this.passwordForm.new_password.length < 6) {
                        this.showNotice('error', 'The new password must be at least 6 characters.');
                        return;
                    }

                    if (this.passwordForm.new_password !== this.passwordForm.confirm_password) {
                        this.showNotice('error', 'Passwords do not match.');
                        return;
                    }

                    const confirmed = await this.askConfirmation({
                        title: 'Reset Password',
                        message: 'Save a new password for ' + (this.selectedStaff ? this.selectedStaff.name : 'this staff account') + '?',
                        confirmText: 'Save Password',
                        tone: 'warning'
                    });
                    if (!confirmed) return;

                    this.savingPassword = true;
                    const data = await this.postJson(config.resetPasswordUrl, this.passwordForm);
                    this.savingPassword = false;

                    if (!data.success) {
                        this.showNotice('error', data.message || 'Unable to reset password.');
                        return;
                    }

                    this.modal = null;
                    this.selectedStaff = null;
                    this.resetPasswordForm();
                    this.showToast(data.message || 'Password reset successfully.');
                },

                async postJson(url, payload) {
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });
                        const data = await response.json();

                        if (response.ok) {
                            return data;
                        }

                        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                        return { success: false, message: data.message || firstError || 'Request failed.' };
                    } catch (error) {
                        return { success: false, message: 'An unexpected error occurred.' };
                    }
                },

                showNotice(type, message) {
                    if (type === 'success') {
                        this.showToast(message);
                        return;
                    }

                    this.showMessage(type, message);
                },

                showToast(message) {
                    this.closeToast();
                    this.toast.message = message;
                    this.toast.open = true;
                    this.toast.timer = window.setTimeout(() => this.closeToast(), 3000);
                },

                closeToast() {
                    if (this.toast.timer) {
                        window.clearTimeout(this.toast.timer);
                    }

                    this.toast = { open: false, message: '', timer: null };
                },

                showStoredToast() {
                    let message = '';

                    try {
                        message = window.sessionStorage.getItem('staff-management-toast') || '';
                        window.sessionStorage.removeItem('staff-management-toast');
                    } catch (error) {
                        message = '';
                    }

                    if (message) {
                        this.$nextTick(() => this.showToast(message));
                    }
                },

                showMessage(type, message, title = '') {
                    const dialogType = type === 'info' ? 'info' : 'error';
                    this.messageDialog = {
                        open: true,
                        type: dialogType,
                        title: title || (dialogType === 'info' ? 'Information' : 'Unable to Continue'),
                        message
                    };
                },

                closeMessageDialog() {
                    this.feedbackClosing = true;
                    this.messageDialog.open = false;

                    this.$nextTick(() => {
                        this.feedbackClosing = false;
                    });
                },

                askConfirmation(options = {}) {
                    return new Promise(resolve => {
                        this.confirmation = {
                            open: true,
                            title: options.title || 'Confirm Action',
                            message: options.message || 'Please confirm this action.',
                            details: options.details || '',
                            confirmText: options.confirmText || 'Confirm',
                            cancelText: options.cancelText || 'Cancel',
                            tone: options.tone || 'brand',
                            resolver: resolve
                        };

                        this.$nextTick(() => {
                            if (this.$refs.confirmPrimary) {
                                this.$refs.confirmPrimary.focus();
                            }
                        });
                    });
                },

                resolveConfirmation(confirmed) {
                    const resolver = this.confirmation.resolver;
                    this.feedbackClosing = true;
                    this.confirmation.open = false;
                    this.confirmation.resolver = null;

                    if (resolver) {
                        resolver(confirmed);
                    }

                    this.$nextTick(() => {
                        this.feedbackClosing = false;
                    });
                },

                reloadWithToast(message) {
                    try {
                        window.sessionStorage.setItem('staff-management-toast', message);
                    } catch (error) {
                        this.showToast(message);
                    }

                    this.scheduleReload();
                },

                scheduleReload(delay = 0) {
                    if (this.reloadTimer) {
                        window.clearTimeout(this.reloadTimer);
                    }

                    if (delay <= 0) {
                        window.location.reload();
                        return;
                    }

                    this.reloadTimer = window.setTimeout(() => window.location.reload(), delay);
                }
            };
        }
    </script>
@endpush
