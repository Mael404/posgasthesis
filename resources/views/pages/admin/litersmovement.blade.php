@extends('layouts.app')

@section('content')
    @php
        $summary = array_merge([
            'total_liters' => 0,
            'total_amount' => 0,
            'limit' => 4000,
            'remaining_liters' => 4000,
            'over_liters' => 0,
            'percent' => 0,
            'progress_width' => 0,
            'transaction_count' => 0,
            'fuel_breakdown' => [],
            'tank_breakdown' => [],
            'staff_breakdown' => [],
            'reached_limits' => [],
            'configured_limit_count' => 0,
            'status' => [
                'key' => 'normal',
                'label' => 'Normal',
                'badge_class' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                'progress_class' => 'bg-success-500',
                'border_class' => 'border-success-200 bg-success-50/60 dark:border-success-500/25 dark:bg-success-500/10',
            ],
        ], $summary ?? []);
        $transactions = $transactions ?? collect();
        $transactionItems = $transactions instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($transactions->items())
            : collect($transactions);
        $history = $history ?? collect();
        $historyItems = $history instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($history->items())
            : collect($history);
        $tankBreakdown = collect($summary['tank_breakdown'] ?? []);
        $reachedLimits = collect($summary['reached_limits'] ?? []);
        $missingRequirements = $missingRequirements ?? [];

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $valueClass = 'mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $dangerButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';

        $dateLabel = function ($value, $format = 'M d, Y h:i A') {
            if (empty($value)) {
                return 'N/A';
            }

            try {
                return \Carbon\Carbon::parse($value)->format($format);
            } catch (\Throwable) {
                return (string) $value;
            }
        };

        $fuelStyle = function ($fuel) {
            $fuel = strtolower((string) $fuel);

            if (str_contains($fuel, 'diesel')) {
                return [
                    'badge' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                    'dot' => 'bg-warning-500',
                    'bar' => 'bg-warning-500',
                    'border' => 'border-warning-300 dark:border-warning-500/40',
                    'soft' => 'bg-warning-50/70 dark:bg-warning-500/10',
                    'header' => 'bg-warning-500 text-gray-950',
                ];
            }

            if (str_contains($fuel, 'regular') || str_contains($fuel, 'unleaded')) {
                return [
                    'badge' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                    'dot' => 'bg-success-500',
                    'bar' => 'bg-success-500',
                    'border' => 'border-success-300 dark:border-success-500/40',
                    'soft' => 'bg-success-50/70 dark:bg-success-500/10',
                    'header' => 'bg-success-500 text-white',
                ];
            }

            if (str_contains($fuel, 'premium')) {
                return [
                    'badge' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                    'dot' => 'bg-error-500',
                    'bar' => 'bg-error-500',
                    'border' => 'border-error-300 dark:border-error-500/40',
                    'soft' => 'bg-error-50/70 dark:bg-error-500/10',
                    'header' => 'bg-error-500 text-white',
                ];
            }

            return [
                'badge' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
                'dot' => 'bg-brand-500',
                'bar' => 'bg-brand-500',
                'border' => 'border-brand-300 dark:border-brand-500/40',
                'soft' => 'bg-brand-50/70 dark:bg-brand-500/10',
                'header' => 'bg-brand-500 text-white',
            ];
        };

        $paymentStyle = function ($type) {
            $type = strtolower(trim((string) $type));

            if ($type === 'credit') {
                return ['label' => 'Credit Sale', 'class' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400'];
            }

            return ['label' => 'Cash / Walk-In', 'class' => 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300'];
        };
    @endphp

    <x-common.page-breadcrumb pageTitle="Liters Movement" />

    <div
        x-data="litersMovementAdmin({
            sessionMessage: @js(session('success') ?: session('status')),
            sessionError: @js(session('error') ?: $errors->first()),
        })"
        x-init="init()"
        x-effect="document.body.classList.toggle('overflow-hidden', confirmation.open || messageDialog.open)"
        x-on:keydown.escape.window="closeTopLayer()"
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

        @if ($missingRequirements)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Missing tracking requirement(s): {{ implode(', ', $missingRequirements) }}.
            </div>
        @endif

        @if ($reachedLimits->isNotEmpty())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-theme-sm text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300">
                <span class="font-semibold">Tank limit reached:</span>
                {{ $reachedLimits->map(fn ($tank) => ($tank['name'] ?? $tank['fuel_type'] ?? 'Tank') . ' (' . number_format((float) ($tank['liters'] ?? 0), 2) . ' L / ' . number_format((float) ($tank['limit'] ?? 0), 2) . ' L)')->implode(', ') }}.
            </div>
        @endif

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(360px,520px)] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Admin Module</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Liters Movement</h1>
                    <p class="mt-2 {{ $captionClass }}">
                        Period {{ $period->period_code ?? 'Pending Migration' }} started {{ $period ? $dateLabel($period->started_at, 'M d, Y h:i A') : 'after migration' }}.
                    </p>
                </div>

                @if (! $missingRequirements)
                    <div class="grid grid-cols-1 gap-3">
                        <form method="POST" action="{{ route('admin.liters-movement.limit') }}" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                @foreach ($tankBreakdown as $tank)
                                    @php
                                        $style = $fuelStyle($tank['fuel_type'] ?? '');
                                        $limitKey = $tank['limit_key'] ?? '';
                                        $oldLimit = old("tank_limits.{$limitKey}", ($tank['has_limit'] ?? false) ? ($tank['limit'] ?? '') : '');
                                    @endphp
                                    <label class="min-w-0 text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                        <span class="flex items-center gap-2">
                                            <span class="h-2 w-2 rounded-full {{ $style['dot'] }}"></span>
                                            {{ $tank['fuel_type'] ?? 'Fuel' }}
                                        </span>
                                        <input type="number" name="tank_limits[{{ $limitKey }}]" min="1" step="0.01" value="{{ $oldLimit }}" placeholder="No limit" class="mt-1.5 {{ $inputClass }}">
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="{{ $captionClass }}">Leave a tank blank to skip limit alerts for that tank.</p>
                                <button type="submit" class="{{ $primaryButtonClass }}">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 9.2 16.7 19 6.9" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Update Limits
                                </button>
                            </div>
                        </form>

                        <form x-ref="generateForm" method="POST" action="{{ route('admin.liters-movement.generate') }}" class="hidden">
                            @csrf
                        </form>
                        <button type="button" x-on:click="confirmGenerate()" class="{{ $dangerButtonClass }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7.5h14M8 7.5V5.75C8 4.784 8.784 4 9.75 4h4.5C15.216 4 16 4.784 16 5.75V7.5M9 10.75v6M15 10.75v6M6.5 7.5l.75 12A1.75 1.75 0 0 0 9 21.25h6a1.75 1.75 0 0 0 1.75-1.75l.75-12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Generate Record & Reset
                        </button>
                    </div>
                @endif
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Total Liters</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['total_liters'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">&#8369;{{ number_format((float) $summary['total_amount'], 2) }}</p>
            </article>
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Configured Limits</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['limit'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">{{ number_format((int) ($summary['configured_limit_count'] ?? 0)) }} active tank limit{{ (int) ($summary['configured_limit_count'] ?? 0) === 1 ? '' : 's' }}</p>
            </article>
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Remaining Before Limits</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['remaining_liters'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">{{ (float) $summary['over_liters'] > 0 ? number_format((float) $summary['over_liters'], 2) . ' L over configured limits' : 'Across configured tanks' }}</p>
            </article>
            <article class="{{ $cardClass }} {{ $summary['status']['border_class'] }}">
                <p class="{{ $labelClass }}">Reached Tanks</p>
                <p class="{{ $valueClass }}">{{ number_format($reachedLimits->count()) }}</p>
                <p class="mt-1 {{ $captionClass }}">{{ number_format((int) $summary['transaction_count']) }} active transaction{{ (int) $summary['transaction_count'] === 1 ? '' : 's' }}</p>
            </article>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tank Progress</h2>
                    <p class="mt-1 {{ $captionClass }}">Active tracking period by tank limit.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                @foreach ($tankBreakdown as $tank)
                    @php
                        $style = $fuelStyle($tank['fuel_type'] ?? '');
                    @endphp
                    <div class="rounded-xl border p-4 {{ $style['border'] }} {{ $style['soft'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $tank['name'] ?? 'Tank' }}</p>
                                <p class="mt-1 {{ $captionClass }}">{{ $tank['connected_pumps_display'] ?? 'N/A' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $tank['status']['badge_class'] ?? '' }}">{{ $tank['status']['label'] ?? 'Normal' }}</span>
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <p class="text-xl font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) ($tank['liters'] ?? 0), 2) }} L</p>
                            <p class="{{ $captionClass }}">{{ ($tank['has_limit'] ?? false) ? number_format((float) ($tank['limit'] ?? 0), 2) . ' L limit' : 'No limit' }}</p>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/70 dark:bg-white/[0.08]">
                            <div class="h-full rounded-full {{ $style['bar'] }}" style="width: {{ $tank['progress_width'] ?? 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tank Ledgers</h2>
                <p class="mt-1 {{ $captionClass }}">Quantity, price, and totals separated by tank for the active period.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                @foreach ($tankBreakdown as $tank)
                    @php
                        $style = $fuelStyle($tank['fuel_type'] ?? '');
                        $entries = collect($tank['entries'] ?? []);
                    @endphp
                    <article class="{{ $cardClass }} overflow-hidden p-0">
                        <div class="px-5 py-4 {{ $style['header'] }}">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="truncate text-base font-semibold">{{ $tank['fuel_type'] ?? 'Fuel' }}</p>
                                    <p class="mt-1 truncate text-theme-xs opacity-90">{{ $tank['name'] ?? 'Tank' }} | {{ $tank['connected_pumps_display'] ?? 'N/A' }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-white/20 px-2.5 py-1 text-theme-xs font-semibold">
                                    {{ ($tank['has_limit'] ?? false) ? number_format((float) ($tank['limit'] ?? 0), 2) . ' L' : 'No limit' }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 border-b border-gray-200 bg-gray-50 text-theme-xs font-semibold uppercase text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                            <div class="px-4 py-2">Qty (L)</div>
                            <div class="px-4 py-2 text-right">Price</div>
                            <div class="px-4 py-2 text-right">Total</div>
                        </div>

                        <div class="max-h-[360px] overflow-y-auto">
                            @forelse ($entries as $entry)
                                <div class="grid grid-cols-3 border-b border-gray-100 text-theme-sm last:border-b-0 dark:border-gray-800">
                                    <div class="px-4 py-2.5 tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) ($entry['liters'] ?? 0), 2) }}</div>
                                    <div class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format((float) ($entry['price_per_liter'] ?? 0), 2) }}</div>
                                    <div class="px-4 py-2.5 text-right tabular-nums font-medium text-gray-800 dark:text-white/90">{{ number_format((float) ($entry['amount'] ?? 0), 2) }}</div>
                                </div>
                            @empty
                                <div class="px-4 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                    No active-period records.
                                </div>
                            @endforelse
                        </div>

                        <div class="grid grid-cols-3 border-t border-gray-200 bg-gray-50 text-theme-sm font-semibold dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="px-4 py-3 tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) ($tank['liters'] ?? 0), 2) }}</div>
                            <div class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format((float) ($tank['price_per_liter'] ?? 0), 2) }}</div>
                            <div class="px-4 py-3 text-right tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) ($tank['amount'] ?? 0), 2) }}</div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="{{ $cardClass }}">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Staff Breakdown</h2>
                    <p class="mt-1 {{ $captionClass }}">Active tracking totals grouped by staff.</p>
                </div>
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-w-full overflow-x-auto">
                        <table class="min-w-[640px] w-full table-fixed">
                            <colgroup>
                                <col class="w-[40%]">
                                <col class="w-[20%]">
                                <col class="w-[20%]">
                                <col class="w-[20%]">
                            </colgroup>
                            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                <tr>
                                    <th class="{{ $thClass }}">Staff</th>
                                    <th class="{{ $thNumberClass }}">Liters</th>
                                    <th class="{{ $thNumberClass }}">Amount</th>
                                    <th class="{{ $thNumberClass }}">Rows</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($summary['staff_breakdown'] as $staff)
                                    <tr>
                                        <td class="{{ $tdPrimaryClass }}">{{ $staff['staff_name'] }}</td>
                                        <td class="{{ $tdNumberClass }}">{{ number_format((float) $staff['liters'], 2) }} L</td>
                                        <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $staff['amount'], 2) }}</td>
                                        <td class="{{ $tdNumberClass }}">{{ number_format((int) $staff['transaction_count']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                            No staff transactions included yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Active Pump Transactions</h2>
                    <p class="mt-1 {{ $captionClass }}">All staff fuel transactions in the active tracking period.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">
                    {{ number_format((int) $summary['transaction_count']) }} rows
                </span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[1160px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[13%]">
                            <col class="w-[14%]">
                            <col class="w-[16%]">
                            <col class="w-[20%]">
                            <col class="w-[14%]">
                            <col class="w-[10%]">
                            <col class="w-[10%]">
                            <col class="w-[8%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Date / Time</th>
                                <th class="{{ $thClass }}">Staff</th>
                                <th class="{{ $thClass }}">Receipt</th>
                                <th class="{{ $thClass }}">Customer</th>
                                <th class="{{ $thClass }}">Pump / Fuel</th>
                                <th class="{{ $thNumberClass }}">Liters</th>
                                <th class="{{ $thNumberClass }}">Amount</th>
                                <th class="{{ $thClass }}">Pay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($transactionItems as $transaction)
                                @php
                                    $fuel = $fuelStyle($transaction->fuel_type ?? '');
                                    $payment = $paymentStyle($transaction->payment_type ?? '');
                                    $receipt = trim((string) ($transaction->or_number ?? ''));
                                    $reference = trim((string) ($transaction->reference_no ?? ''));
                                @endphp
                                <tr>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $dateLabel($transaction->created_at ?? null, 'M d, Y') }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $dateLabel($transaction->created_at ?? null, 'h:i A') }}</div>
                                    </td>
                                    <td class="{{ $tdPrimaryClass }}">{{ $transaction->staff_name ?? 'Staff' }}</td>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $receipt !== '' ? $receipt : 'No Receipt' }}</div>
                                        <div class="mt-1 truncate text-theme-xs text-gray-500 dark:text-gray-400" title="{{ $reference }}">{{ $reference !== '' ? $reference : 'No reference' }}</div>
                                    </td>
                                    <td class="{{ $tdPrimaryClass }}">
                                        <div class="truncate" title="{{ $transaction->customer_display_name ?? 'Walk-In Customer' }}">{{ $transaction->customer_display_name ?? 'Walk-In Customer' }}</div>
                                        <div class="mt-1 truncate text-theme-xs font-normal text-gray-500 dark:text-gray-400" title="{{ $transaction->plate_no ?? '' }}">{{ $transaction->plate_no ?: 'No plate' }}</div>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">Pump {{ (int) ($transaction->pump_id ?? 0) }}</div>
                                        <span class="mt-1 inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $fuel['badge'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $fuel['dot'] }}"></span>
                                            {{ $transaction->fuel_type ?? 'Fuel' }}
                                        </span>
                                    </td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format((float) ($transaction->liters ?? 0), 2) }} L</td>
                                    <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) ($transaction->amount ?? 0), 2) }}</td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $payment['class'] }}">{{ $payment['label'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                        No active-period pump transactions yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($transactions instanceof \Illuminate\Contracts\Pagination\Paginator && $transactions->hasPages())
                <div class="mt-5">
                    {{ $transactions->links() }}
                </div>
            @endif
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Generated History</h2>
                <p class="mt-1 {{ $captionClass }}">Saved records from previous tracking periods.</p>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[1040px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[17%]">
                            <col class="w-[17%]">
                            <col class="w-[17%]">
                            <col class="w-[12%]">
                            <col class="w-[12%]">
                            <col class="w-[12%]">
                            <col class="w-[13%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Period</th>
                                <th class="{{ $thClass }}">Started</th>
                                <th class="{{ $thClass }}">Ended</th>
                                <th class="{{ $thNumberClass }}">Limit</th>
                                <th class="{{ $thNumberClass }}">Liters</th>
                                <th class="{{ $thNumberClass }}">Amount</th>
                                <th class="{{ $thClass }}">Generated By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($historyItems as $record)
                                @php
                                    $snapshot = $record->snapshot ?? [];
                                    $recordLiters = (float) ($snapshot['total_liters'] ?? 0);
                                    $recordAmount = (float) ($snapshot['total_amount'] ?? 0);
                                    $recordLimit = (float) ($snapshot['liters_limit'] ?? $record->liters_limit ?? 0);
                                @endphp
                                <tr>
                                    <td class="{{ $tdPrimaryClass }}">
                                        <div class="truncate" title="{{ $record->period_code }}">{{ $record->period_code }}</div>
                                        <div class="mt-1 text-theme-xs font-normal text-gray-500 dark:text-gray-400">{{ number_format((int) ($snapshot['transaction_count'] ?? 0)) }} txn</div>
                                    </td>
                                    <td class="{{ $tdClass }}">{{ $dateLabel($record->started_at ?? null) }}</td>
                                    <td class="{{ $tdClass }}">{{ $dateLabel($record->ended_at ?? null) }}</td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format($recordLimit, 2) }} L</td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format($recordLiters, 2) }} L</td>
                                    <td class="{{ $tdNumberClass }}">&#8369;{{ number_format($recordAmount, 2) }}</td>
                                    <td class="{{ $tdClass }}">{{ $record->generated_by_name ?? 'Admin' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                        No generated records yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($history instanceof \Illuminate\Contracts\Pagination\Paginator && $history->hasPages())
                <div class="mt-5">
                    {{ $history->links() }}
                </div>
            @endif
        </section>

        <div x-show="confirmation.open" x-cloak class="fixed inset-0 z-[100020] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="confirmation.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <div
                x-show="confirmation.open"
                x-transition
                x-on:click.outside="resolveConfirmation(false)"
                class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Generate Record & Reset</h3>
                    <p class="mt-1 {{ $captionClass }}">Save the active tracking summary and start a new period at 0 liters?</p>
                </div>
                <div class="space-y-3 p-5">
                    <div class="rounded-xl border border-gray-200 p-4 text-theme-sm dark:border-gray-800">
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Current Liters</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) $summary['total_liters'], 2) }} L</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Transactions</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((int) $summary['transaction_count']) }}</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Limit</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) $summary['limit'], 2) }} L</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="resolveConfirmation(false)" class="{{ $secondaryButtonClass }}">Cancel</button>
                    <button x-ref="confirmPrimary" type="button" x-on:click="resolveConfirmation(true)" class="{{ $dangerButtonClass }}">Generate & Reset</button>
                </div>
            </div>
        </div>

        <div x-show="messageDialog.open" x-cloak class="fixed inset-0 z-[100030] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="messageDialog.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <div
                x-show="messageDialog.open"
                x-transition
                x-on:click.outside="closeMessage()"
                class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="messageDialog.title"></h3>
                    <p class="mt-1 {{ $captionClass }}" x-text="messageDialog.message"></p>
                </div>
                <div class="flex justify-end px-5 py-4">
                    <button type="button" x-on:click="closeMessage()" class="{{ $primaryButtonClass }}">OK</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function litersMovementAdmin(config) {
            return {
                toast: { open: false, message: '', timer: null },
                confirmation: { open: false },
                messageDialog: { open: false, title: '', message: '' },
                init() {
                    if (config.sessionMessage) {
                        this.showToast(config.sessionMessage);
                    }

                    if (config.sessionError) {
                        this.showMessage('Unable to Continue', config.sessionError);
                    }
                },
                async confirmGenerate() {
                    if (window.Swal && typeof window.Swal.fire === 'function') {
                        const result = await window.Swal.fire({
                            title: 'Generate Record & Reset?',
                            text: 'This saves the active liters movement and starts a new tracking period.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Generate & Reset',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#d92d20'
                        });

                        if (result.isConfirmed) {
                            this.$refs.generateForm.submit();
                        }

                        return;
                    }

                    this.confirmation.open = true;
                    this.$nextTick(() => {
                        if (this.$refs.confirmPrimary) {
                            this.$refs.confirmPrimary.focus();
                        }
                    });
                },
                resolveConfirmation(confirmed) {
                    this.confirmation.open = false;

                    if (confirmed && this.$refs.generateForm) {
                        this.$refs.generateForm.submit();
                    }
                },
                closeTopLayer() {
                    if (this.messageDialog.open) {
                        this.closeMessage();
                        return;
                    }

                    if (this.confirmation.open) {
                        this.resolveConfirmation(false);
                    }
                },
                showToast(message) {
                    this.closeToast();
                    this.toast.message = message || 'Action completed.';
                    this.toast.open = true;
                    this.toast.timer = window.setTimeout(() => this.closeToast(), 3200);
                },
                closeToast() {
                    if (this.toast.timer) {
                        window.clearTimeout(this.toast.timer);
                    }

                    this.toast = { open: false, message: '', timer: null };
                },
                showMessage(title, message) {
                    this.messageDialog = {
                        open: true,
                        title: title || 'Notice',
                        message: message || 'Please review the current action.',
                    };
                },
                closeMessage() {
                    this.messageDialog = { open: false, title: '', message: '' };
                }
            };
        }
    </script>
@endpush
