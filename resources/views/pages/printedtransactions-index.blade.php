@php
    $routeBase = $adminView ? 'admin.printed-transactions' : 'staff.printed-transactions';
    $prints = $prints ?? collect();
    $staffList = collect($staffList ?? []);
    $sessionMessage = session('success') ?: session('status');
    $sessionError = session('error');
    $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
    $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
    $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
    $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
    $dateInputClass = 'h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pl-4 pr-11 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
    $selectClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
    $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
    $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
    $dangerButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60';
    $viewIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 shadow-theme-xs transition hover:border-brand-300 hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/15';
    $markIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-success-200 bg-success-50 text-success-700 shadow-theme-xs transition hover:border-success-300 hover:bg-success-100 dark:border-success-500/25 dark:bg-success-500/10 dark:text-success-400 dark:hover:bg-success-500/15';
    $unmarkIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-warning-200 bg-warning-50 text-warning-700 shadow-theme-xs transition hover:border-warning-300 hover:bg-warning-100 dark:border-warning-500/25 dark:bg-warning-500/10 dark:text-warning-400 dark:hover:bg-warning-500/15';
    $deleteIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-error-200 bg-error-50 text-error-700 shadow-theme-xs transition hover:border-error-300 hover:bg-error-100 dark:border-error-500/25 dark:bg-error-500/10 dark:text-error-400 dark:hover:bg-error-500/15';
    $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
    $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
    $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
    $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
    $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
    $dateIcon = '<span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="size-6"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z" fill="currentColor"></path></svg></span>';
@endphp

<x-common.page-breadcrumb pageTitle="Printed Transactions" />

<div
    x-data="printedTransactionsModule({
        csrfToken: @js(csrf_token()),
        markUrl: @js(route($routeBase . '.mark', ['batch' => '__BATCH__'])),
        destroyUrl: @js($adminView ? route('admin.printed-transactions.destroy', ['batch' => '__BATCH__']) : null),
        bulkMarkUrl: @js($adminView ? route('admin.printed-transactions.bulk-mark') : null),
        bulkDeleteUrl: @js($adminView ? route('admin.printed-transactions.bulk-destroy') : null),
        storedToastKey: 'printed-transactions-toast',
        sessionMessage: @js($sessionMessage),
        sessionError: @js($sessionError)
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
                <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Printed Transactions</h1>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap xl:justify-end">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.02]">
                    <p class="{{ $labelClass }}">Batches</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $prints->total() }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.02]">
                    <p class="{{ $labelClass }}">Visible Total</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;{{ number_format($prints->getCollection()->sum('total_amount'), 2) }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="{{ $cardClass }}">
        <form method="GET" action="{{ route($routeBase . '.index') }}" class="grid grid-cols-1 items-end gap-3 md:grid-cols-2 xl:grid-cols-[minmax(160px,180px)_minmax(160px,180px)_minmax(160px,180px)_minmax(160px,180px)_minmax(220px,1fr)_auto_auto]">
            <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                <span>Status</span>
                <select name="status" class="{{ $selectClass }}">
                    <option value="">All</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="marked" {{ request('status') === 'marked' ? 'selected' : '' }}>Marked</option>
                </select>
            </label>

            @if ($adminView)
                <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                    <span>Staff</span>
                    <select name="staff_id" class="{{ $selectClass }}">
                        <option value="">All Staff</option>
                        @foreach ($staffList as $staff)
                            <option value="{{ $staff->id }}" {{ (string) request('staff_id') === (string) $staff->id ? 'selected' : '' }}>
                                {{ $staff->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                <span>From</span>
                <span class="relative block custom-datepicker">
                    <input type="text" name="start_date" value="{{ request('start_date') }}" placeholder="Select date" autocomplete="off" class="{{ $dateInputClass }}" data-printed-date>
                    {!! $dateIcon !!}
                </span>
            </label>

            <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                <span>To</span>
                <span class="relative block custom-datepicker">
                    <input type="text" name="end_date" value="{{ request('end_date') }}" placeholder="Select date" autocomplete="off" class="{{ $dateInputClass }}" data-printed-date>
                    {!! $dateIcon !!}
                </span>
            </label>

            <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                <span>Search</span>
                <input type="search" name="search" value="{{ request('search') }}" class="{{ $inputClass }}" placeholder="Batch or customer">
            </label>

            <button type="submit" class="{{ $primaryButtonClass }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                Filter
            </button>
            <a href="{{ route($routeBase . '.index') }}" class="{{ $secondaryButtonClass }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.75 12a7.25 7.25 0 1 0 2.13-5.13M4.75 5.5v4h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Reset
            </a>
        </form>
    </section>

    <section class="{{ $cardClass }}">
        <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Print Tracking Log</h2>
                <p class="mt-1 {{ $captionClass }}">{{ $prints->total() }} batch{{ $prints->total() === 1 ? '' : 'es' }} found.</p>
            </div>
            @if ($adminView)
                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="confirmBulkMark()" :disabled="selectedBatches.length === 0" class="{{ $secondaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.25 4.25L19 7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Mark Selected
                    </button>
                    <button type="button" x-on:click="confirmBulkDelete()" :disabled="selectedBatches.length === 0" class="{{ $dangerButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9.75 9.75v6.5M14.25 9.75v6.5M5.75 6.75h12.5M10 4.75h4M7 6.75l.55 12.1A1.5 1.5 0 0 0 9.05 20.25h5.9a1.5 1.5 0 0 0 1.5-1.4L17 6.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Delete Selected
                    </button>
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="max-w-full overflow-x-auto">
                <table class="{{ $adminView ? 'min-w-[1100px]' : 'min-w-[900px]' }} w-full table-fixed">
                    <colgroup>
                        @if ($adminView)
                            <col class="w-[5%]">
                        @endif
                        <col class="w-[12%]">
                        <col class="{{ $adminView ? 'w-[19%]' : 'w-[20%]' }}">
                        @if ($adminView)
                            <col class="w-[8%]">
                        @endif
                        <col class="{{ $adminView ? 'w-[20%]' : 'w-[31%]' }}">
                        <col class="{{ $adminView ? 'w-[10%]' : 'w-[11%]' }}">
                        <col class="{{ $adminView ? 'w-[12%]' : 'w-[13%]' }}">
                        <col class="{{ $adminView ? 'w-[14%]' : 'w-[13%]' }}">
                    </colgroup>
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                        <tr>
                            @if ($adminView)
                                <th class="{{ $thClass }}">
                                    <input type="checkbox" class="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900" x-bind:checked="allVisibleSelected()" x-on:change="toggleAllVisible($event.target.checked)" aria-label="Select all visible batches">
                                </th>
                            @endif
                            <th class="{{ $thClass }}">Printed At</th>
                            <th class="{{ $thClass }}">Customer</th>
                            @if ($adminView)
                                <th class="{{ $thClass }}">Staff</th>
                            @endif
                            <th class="{{ $thClass }}">Transactions</th>
                            <th class="{{ $thNumberClass }}">Total</th>
                            <th class="{{ $thClass }}">Status</th>
                            <th class="{{ $thClass }}">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($prints as $print)
                            @php
                                $isMarked = (bool) $print->is_marked;
                                $summaries = collect($print->transaction_summaries ?? []);
                                $printedAt = $print->printed_at ? \Carbon\Carbon::parse($print->printed_at) : null;
                            @endphp
                            <tr>
                                @if ($adminView)
                                    <td class="{{ $tdClass }}">
                                        <input type="checkbox" class="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900" value="{{ $print->id }}" x-model="selectedBatches" aria-label="Select batch {{ $print->batch_no ?: $print->id }}">
                                    </td>
                                @endif
                                <td class="{{ $tdClass }}">
                                    <div class="font-medium text-gray-800 dark:text-white/90">{{ $printedAt ? $printedAt->format('M d, Y') : 'N/A' }}</div>
                                    <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $printedAt ? $printedAt->format('h:i A') : '' }}</div>
                                </td>
                                <td class="{{ $tdClass }}">
                                    <div class="font-medium text-gray-800 dark:text-white/90">{{ $print->customer_name ?: 'N/A' }}</div>
                                    @if (! empty($print->customer_subtitle))
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $print->customer_subtitle }}</div>
                                    @endif
                                    @if (! empty($print->customer_contact))
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $print->customer_contact }}</div>
                                    @endif
                                </td>
                                @if ($adminView)
                                    <td class="{{ $tdClass }}">
                                        <div class="truncate" title="{{ $print->staff_name ?: 'Staff #' . $print->staff_id }}">
                                            {{ $print->staff_name ?: 'Staff #' . $print->staff_id }}
                                        </div>
                                    </td>
                                @endif
                                <td class="{{ $tdClass }}">
                                    <div class="flex flex-wrap gap-1.5">
                                        @forelse ($summaries->take(4) as $summary)
                                            <span class="inline-flex max-w-full rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-theme-xs font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                                                <span class="truncate">{{ $summary['reference'] }}</span>
                                            </span>
                                        @empty
                                            <span class="text-theme-xs text-gray-500 dark:text-gray-400">No references</span>
                                        @endforelse

                                        @if ($summaries->count() > 4)
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">+{{ $summaries->count() - 4 }} more</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="{{ $tdNumberClass }} font-semibold">&#8369;{{ number_format((float) $print->total_amount, 2) }}</td>
                                <td class="{{ $tdClass }}">
                                    @if ($isMarked)
                                        <span class="inline-flex rounded-full bg-success-50 px-2.5 py-0.5 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">Marked</span>
                                        @if (! empty($print->marked_at))
                                            <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($print->marked_at)->format('M d, h:i A') }}</div>
                                        @endif
                                        @if (! empty($print->marked_by_name))
                                            <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">By {{ $print->marked_by_name }}</div>
                                        @endif
                                    @else
                                        <span class="inline-flex rounded-full bg-warning-50 px-2.5 py-0.5 text-theme-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">Pending</span>
                                    @endif
                                </td>
                                <td class="{{ $tdClass }}">
                                    <div class="flex min-w-[6rem] items-center gap-2">
                                        <a href="{{ route($routeBase . '.print', $print->id) }}" target="_blank" class="{{ $viewIconButtonClass }}" title="View print copy" aria-label="View print copy">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 8V4h10v4M7 17H5.75A2.75 2.75 0 0 1 3 14.25v-3.5A2.75 2.75 0 0 1 5.75 8h12.5A2.75 2.75 0 0 1 21 10.75v3.5A2.75 2.75 0 0 1 18.25 17H17M7 14h10v6H7v-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </a>
                                        <button
                                            type="button"
                                            x-on:click="confirmMark({{ $print->id }}, {{ $isMarked ? 'false' : 'true' }}, @js($isMarked ? 'Return to Pending' : 'Mark Batch'), @js($isMarked ? 'Return this printed batch to pending?' : 'Mark this printed batch as checked?'))"
                                            class="{{ $isMarked ? $unmarkIconButtonClass : $markIconButtonClass }}"
                                            title="{{ $isMarked ? 'Return to pending' : 'Mark batch' }}"
                                            aria-label="{{ $isMarked ? 'Return to pending' : 'Mark batch' }}">
                                            @if ($isMarked)
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.75 12a7.25 7.25 0 1 0 2.13-5.13M4.75 5.5v4h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            @else
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.25 4.25L19 7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            @endif
                                        </button>
                                        @if ($adminView)
                                            <button
                                                type="button"
                                                x-on:click="confirmDelete({{ $print->id }})"
                                                class="{{ $deleteIconButtonClass }}"
                                                title="Delete batch"
                                                aria-label="Delete batch">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9.75 9.75v6.5M14.25 9.75v6.5M5.75 6.75h12.5M10 4.75h4M7 6.75l.55 12.1A1.5 1.5 0 0 0 9.05 20.25h5.9a1.5 1.5 0 0 0 1.5-1.4L17 6.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $adminView ? 8 : 6 }}" class="px-5 py-10 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                            <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 8V4h10v4M7 17H5.75A2.75 2.75 0 0 1 3 14.25v-3.5A2.75 2.75 0 0 1 5.75 8h12.5A2.75 2.75 0 0 1 21 10.75v3.5A2.75 2.75 0 0 1 18.25 17H17M7 14h10v6H7v-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </span>
                                        <p class="mt-3 text-theme-sm font-medium text-gray-800 dark:text-white/90">No printed transactions found.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($prints->hasPages())
            <div class="mt-5">
                {{ $prints->links() }}
            </div>
        @endif
    </section>

    <div x-show="confirmation.open" x-cloak class="fixed inset-0 z-999999 flex items-center justify-center p-4">
        <div x-show="confirmation.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55"></div>
        <div
            x-show="confirmation.open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-3 scale-95"
            class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-start gap-4">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                    :class="confirmation.tone === 'danger' ? 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400' : (confirmation.tone === 'warning' ? 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400' : 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400')">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.25 4.25L19 7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="confirmation.title"></h3>
                    <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400" x-text="confirmation.message"></p>
                </div>
            </div>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" x-on:click="closeConfirmation()" class="{{ $secondaryButtonClass }}" :disabled="confirmation.loading">Cancel</button>
                <button type="button" x-on:click="submitConfirmation()" :class="confirmation.tone === 'danger' ? @js($dangerButtonClass) : @js($primaryButtonClass)" :disabled="confirmation.loading">
                    <span x-show="!confirmation.loading" x-text="confirmation.confirmText"></span>
                    <span x-show="confirmation.loading">Saving...</span>
                </button>
            </div>
        </div>
    </div>

    <div x-show="messageDialog.open" x-cloak class="fixed inset-0 z-999999 flex items-center justify-center p-4">
        <div x-show="messageDialog.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55"></div>
        <div
            x-show="messageDialog.open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-3 scale-95"
            class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-start gap-4">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8v5M12 16.5h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="messageDialog.title"></h3>
                    <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400" x-text="messageDialog.message"></p>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" x-on:click="closeMessage()" class="{{ $secondaryButtonClass }}">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        function printedTransactionsModule(config) {
            return {
                toast: { open: false, message: '', timer: null },
                selectedBatches: [],
                confirmation: {
                    open: false,
                    action: 'mark',
                    id: null,
                    ids: [],
                    isMarked: true,
                    title: '',
                    message: '',
                    confirmText: 'Continue',
                    tone: 'success',
                    loading: false
                },
                messageDialog: {
                    open: false,
                    title: '',
                    message: ''
                },

                init() {
                    this.initDatePickers();
                    this.showStoredToast();

                    if (config.sessionMessage) {
                        this.$nextTick(() => this.showToast(config.sessionMessage));
                    }

                    if (config.sessionError) {
                        this.$nextTick(() => this.showMessage(config.sessionError));
                    }
                },

                initDatePickers() {
                    if (!window.flatpickr) return;

                    document.querySelectorAll('[data-printed-date]').forEach(function(input) {
                        if (input._printedDatePicker) return;

                        input._printedDatePicker = window.flatpickr(input, {
                            static: true,
                            monthSelectorType: 'static',
                            dateFormat: 'Y-m-d',
                            defaultDate: input.value || null,
                            allowInput: false,
                            disableMobile: true
                        });
                    });
                },

                confirmMark(id, isMarked, title, message) {
                    this.confirmation = {
                        open: true,
                        action: 'mark',
                        id,
                        ids: [],
                        isMarked,
                        title,
                        message,
                        confirmText: isMarked ? 'Mark Batch' : 'Return to Pending',
                        tone: isMarked ? 'success' : 'warning',
                        loading: false
                    };
                },

                confirmDelete(id) {
                    if (!config.destroyUrl) return;

                    this.confirmation = {
                        open: true,
                        action: 'delete',
                        id,
                        ids: [],
                        isMarked: false,
                        title: 'Delete Batch',
                        message: 'Delete this printed transaction batch permanently?',
                        confirmText: 'Delete Batch',
                        tone: 'danger',
                        loading: false
                    };
                },

                confirmBulkMark() {
                    const ids = this.selectedIds();

                    if (!ids.length) {
                        this.showMessage('Select at least one printed transaction batch first.');
                        return;
                    }

                    this.confirmation = {
                        open: true,
                        action: 'bulk-mark',
                        id: null,
                        ids,
                        isMarked: true,
                        title: 'Mark Selected',
                        message: 'Mark ' + ids.length + ' selected printed transaction batch' + (ids.length === 1 ? '' : 'es') + '?',
                        confirmText: 'Mark Selected',
                        tone: 'success',
                        loading: false
                    };
                },

                confirmBulkDelete() {
                    const ids = this.selectedIds();

                    if (!ids.length) {
                        this.showMessage('Select at least one printed transaction batch first.');
                        return;
                    }

                    this.confirmation = {
                        open: true,
                        action: 'bulk-delete',
                        id: null,
                        ids,
                        isMarked: false,
                        title: 'Delete Selected',
                        message: 'Delete ' + ids.length + ' selected printed transaction batch' + (ids.length === 1 ? '' : 'es') + ' permanently?',
                        confirmText: 'Delete Selected',
                        tone: 'danger',
                        loading: false
                    };
                },

                closeConfirmation() {
                    if (this.confirmation.loading) return;

                    this.confirmation.open = false;
                },

                async submitConfirmation() {
                    if (this.confirmation.loading) return;

                    this.confirmation.loading = true;
                    let url = '';
                    let method = 'POST';
                    let payload = {};

                    if (this.confirmation.action === 'mark') {
                        if (!this.confirmation.id) return;
                        url = config.markUrl.replace('__BATCH__', this.confirmation.id);
                        payload = { is_marked: this.confirmation.isMarked };
                    } else if (this.confirmation.action === 'delete') {
                        if (!this.confirmation.id || !config.destroyUrl) return;
                        url = config.destroyUrl.replace('__BATCH__', this.confirmation.id);
                        method = 'DELETE';
                    } else if (this.confirmation.action === 'bulk-mark') {
                        if (!config.bulkMarkUrl) return;
                        url = config.bulkMarkUrl;
                        payload = { batch_ids: this.confirmation.ids };
                    } else if (this.confirmation.action === 'bulk-delete') {
                        if (!config.bulkDeleteUrl) return;
                        url = config.bulkDeleteUrl;
                        method = 'DELETE';
                        payload = { batch_ids: this.confirmation.ids };
                    }

                    const data = await this.requestJson(url, method, payload);

                    if (!data.success) {
                        this.confirmation.loading = false;
                        this.showMessage(data.message || 'Unable to update printed transactions.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Printed transaction log updated.');
                },

                selectedIds() {
                    return this.selectedBatches
                        .map((id) => Number(id))
                        .filter((id) => Number.isInteger(id) && id > 0);
                },

                visibleIds() {
                    return Array.from(document.querySelectorAll('input[x-model="selectedBatches"][value]'))
                        .map((input) => Number(input.value))
                        .filter((id) => Number.isInteger(id) && id > 0);
                },

                allVisibleSelected() {
                    const visible = this.visibleIds();
                    const selected = this.selectedIds();

                    return visible.length > 0 && visible.every((id) => selected.includes(id));
                },

                toggleAllVisible(checked) {
                    const visible = this.visibleIds();
                    const selected = new Set(this.selectedIds());

                    visible.forEach((id) => {
                        if (checked) {
                            selected.add(id);
                        } else {
                            selected.delete(id);
                        }
                    });

                    this.selectedBatches = Array.from(selected).map((id) => String(id));
                },

                async requestJson(url, method, payload = {}) {
                    try {
                        const options = {
                            method,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json'
                            }
                        };

                        if (method !== 'GET') {
                            options.body = JSON.stringify(payload);
                        }

                        const response = await fetch(url, options);
                        const data = await response.json().catch(() => ({}));

                        if (response.ok && data.success !== false) {
                            return { success: true, message: data.message || '' };
                        }

                        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;

                        return { success: false, message: data.message || firstError || 'Request failed.' };
                    } catch (error) {
                        return { success: false, message: 'Could not update printed transactions.' };
                    }
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
                        message = window.sessionStorage.getItem(config.storedToastKey) || '';
                        window.sessionStorage.removeItem(config.storedToastKey);
                    } catch (error) {
                        message = '';
                    }

                    if (message) {
                        this.$nextTick(() => this.showToast(message));
                    }
                },

                reloadWithToast(message) {
                    try {
                        window.sessionStorage.setItem(config.storedToastKey, message);
                    } catch (error) {
                        this.showToast(message);
                    }

                    window.location.reload();
                },

                showMessage(message, title = 'Unable to Continue') {
                    this.messageDialog = {
                        open: true,
                        title,
                        message
                    };
                },

                closeMessage() {
                    this.messageDialog.open = false;
                }
            };
        }
    </script>
@endpush
