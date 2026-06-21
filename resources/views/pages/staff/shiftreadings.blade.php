@extends('layouts.app')

@section('content')
    @php
        $pumpMap = [
            ['id' => 1, 'label' => 'Pump 01', 'fuel' => 'Premium'],
            ['id' => 2, 'label' => 'Pump 02', 'fuel' => 'Unleaded'],
            ['id' => 3, 'label' => 'Pump 03', 'fuel' => 'Premium'],
            ['id' => 4, 'label' => 'Pump 04', 'fuel' => 'Unleaded'],
            ['id' => 5, 'label' => 'Pump 05', 'fuel' => 'Diesel'],
            ['id' => 6, 'label' => 'Pump 06', 'fuel' => 'Diesel'],
        ];
        $denominations = [
            ['key' => '1000', 'label' => '1000', 'value' => 1000, 'type' => 'Paper'],
            ['key' => '500', 'label' => '500', 'value' => 500, 'type' => 'Paper'],
            ['key' => '200', 'label' => '200', 'value' => 200, 'type' => 'Paper'],
            ['key' => '100', 'label' => '100', 'value' => 100, 'type' => 'Paper'],
            ['key' => '50', 'label' => '50', 'value' => 50, 'type' => 'Paper'],
            ['key' => '20_paper', 'label' => '20', 'value' => 20, 'type' => 'Paper'],
            ['key' => '20_coin', 'label' => '20', 'value' => 20, 'type' => 'Coin'],
            ['key' => '10', 'label' => '10', 'value' => 10, 'type' => 'Coin'],
            ['key' => '5', 'label' => '5', 'value' => 5, 'type' => 'Coin'],
            ['key' => '1', 'label' => '1', 'value' => 1, 'type' => 'Coin'],
            ['key' => '025', 'label' => '25 cents', 'value' => 0.25, 'type' => 'Coin'],
        ];

        $cardClass = 'rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $textareaClass = 'w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $fileClass = 'block w-full rounded-lg border border-gray-300 bg-transparent text-theme-sm text-gray-800 shadow-theme-xs file:mr-3 file:border-0 file:bg-gray-100 file:px-3 file:py-2.5 file:text-theme-xs file:font-medium file:text-gray-700 hover:file:bg-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:file:bg-white/[0.06] dark:file:text-gray-300';
        $primaryButtonClass = 'inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $dangerButtonClass = 'inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-error-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60';
        $iconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $viewIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 shadow-theme-xs transition hover:border-brand-300 hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/15';
        $voidIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-error-200 bg-error-50 text-error-600 shadow-theme-xs transition hover:border-error-300 hover:bg-error-100 dark:border-error-500/25 dark:bg-error-500/10 dark:text-error-400 dark:hover:bg-error-500/15';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';

        $periodLabel = function ($reading) {
            try {
                $from = \Carbon\Carbon::parse($reading->period_from ?? $reading->created_at ?? now());
                $to = \Carbon\Carbon::parse($reading->period_to ?? $reading->period_from ?? $reading->created_at ?? now());

                return $from->isSameDay($to)
                    ? $from->format('M d, Y')
                    : $from->format('M d') . ' - ' . $to->format('M d, Y');
            } catch (\Throwable) {
                return 'No period selected';
            }
        };

        $statusMeta = function ($status) {
            $status = strtolower((string) ($status ?: 'Pending'));

            if (str_contains($status, 'verified') || str_contains($status, 'approved')) {
                return ['label' => 'Verified', 'class' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500'];
            }

            if (str_contains($status, 'reject')) {
                return ['label' => 'Rejected', 'class' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-500'];
            }

            if (str_contains($status, 'void')) {
                return ['label' => 'Voided', 'class' => 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300'];
            }

            return ['label' => 'Pending Verification', 'class' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400'];
        };

        $varianceMeta = function ($variance) {
            $variance = (float) $variance;

            if (abs($variance) < 0.01) {
                return ['label' => 'Balanced', 'class' => 'text-success-600 dark:text-success-500', 'display' => 'PHP 0.00'];
            }

            if ($variance < 0) {
                return ['label' => 'Shortage', 'class' => 'text-error-600 dark:text-error-500', 'display' => '-PHP ' . number_format(abs($variance), 2)];
            }

            return ['label' => 'Overage', 'class' => 'text-brand-600 dark:text-brand-400', 'display' => '+PHP ' . number_format($variance, 2)];
        };

        $rows = collect($readings->items());
        $summary = [
            'total' => $readings->total(),
            'pending' => $rows->filter(fn ($reading) => str_contains(strtolower((string) ($reading->status ?? 'Pending')), 'pending'))->count(),
            'verified' => $rows->filter(fn ($reading) => str_contains(strtolower((string) ($reading->status ?? '')), 'verified'))->count(),
            'rejected' => $rows->filter(fn ($reading) => str_contains(strtolower((string) ($reading->status ?? '')), 'reject'))->count(),
        ];
    @endphp

    <x-common.page-breadcrumb pageTitle="Shift Readings" />

    <div
        x-data="shiftReadingsModule({
            csrfToken: @js(csrf_token()),
            routes: {
                store: @js(route('staff.readings.store')),
                detailsBase: @js(url('/staff/readings')),
                voidTemplate: @js(route('staff.readings.void', ['reading' => '__ID__']))
            },
            pumps: @js($pumpMap),
            denominations: @js($denominations),
            prevReadings: @js($prevReadings ?? []),
            liveSales: @js($liveSales ?? []),
            latestSubmitted: @js(optional($latestReading)->created_at)
        })"
        x-init="init()"
        x-effect="document.body.classList.toggle('overflow-hidden', modal !== null || confirmation.open || messageDialog.open || lightbox.open)"
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

        @if (! empty($dataWarning))
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                {{ $dataWarning }}
            </div>
        @endif

        <section class="{{ $cardClass }} p-5 lg:p-6">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Staff Module</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Shift Readings</h1>
                    <p class="mt-2 max-w-3xl {{ $captionClass }}">Submit periodical settlement readings and track admin review status.</p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row xl:justify-end">
                    <button type="button" x-on:click="openForm()" class="{{ $primaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        Submit Settlement
                    </button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                    <p class="{{ $labelClass }}">Total</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($summary['total']) }}</p>
                </div>
                <div class="rounded-xl border border-warning-100 bg-warning-50/50 p-3 dark:border-warning-500/20 dark:bg-warning-500/10">
                    <p class="{{ $labelClass }}">Pending</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-warning-700 dark:text-warning-400">{{ number_format($summary['pending']) }}</p>
                </div>
                <div class="rounded-xl border border-success-100 bg-success-50/50 p-3 dark:border-success-500/20 dark:bg-success-500/10">
                    <p class="{{ $labelClass }}">Verified</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-success-700 dark:text-success-500">{{ number_format($summary['verified']) }}</p>
                </div>
                <div class="rounded-xl border border-error-100 bg-error-50/50 p-3 dark:border-error-500/20 dark:bg-error-500/10">
                    <p class="{{ $labelClass }}">Rejected</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-error-700 dark:text-error-500">{{ number_format($summary['rejected']) }}</p>
                </div>
            </div>
        </section>

        <section class="{{ $cardClass }} overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Settlement History</h2>
                    <p class="mt-1 {{ $captionClass }}">Newest submitted readings appear first.</p>
                </div>
            </div>

            <div class="max-w-full overflow-x-auto">
                <table class="min-w-[1080px] w-full table-fixed">
                    <colgroup>
                        <col class="w-[15%]">
                        <col class="w-[15%]">
                        <col class="w-[15%]">
                        <col class="w-[14%]">
                        <col class="w-[15%]">
                        <col class="w-[17%]">
                        <col class="w-[9%]">
                    </colgroup>
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                        <tr>
                            <th class="{{ $thClass }}">Period</th>
                            <th class="{{ $thNumberClass }}">Total Remitted</th>
                            <th class="{{ $thNumberClass }}">Expected</th>
                            <th class="{{ $thNumberClass }}">Variance</th>
                            <th class="{{ $thClass }}">Status</th>
                            <th class="{{ $thClass }}">Admin Feedback</th>
                            <th class="{{ $thClass }}">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($readings as $reading)
                            @php
                                $status = $statusMeta($reading->status ?? 'Pending');
                                $variance = $varianceMeta($reading->cash_variance ?? 0);
                                $statusValue = strtolower((string) ($reading->status ?? 'Pending'));
                                $canVoid = str_contains($statusValue, 'pending') || str_contains($statusValue, 'reject');
                                $feedback = collect([$reading->rejection_reason ?? null, $reading->admin_remarks ?? null, $reading->void_reason ?? null])
                                    ->filter(fn ($value) => trim((string) $value) !== '')
                                    ->implode(' | ');
                            @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                <td class="{{ $tdPrimaryClass }}">
                                    <span class="block truncate">{{ $periodLabel($reading) }}</span>
                                    <span class="mt-1 block text-theme-xs font-normal text-gray-500 dark:text-gray-400">
                                        {{ optional(\Carbon\Carbon::parse($reading->created_at ?? now()))->format('M d, Y h:i A') }}
                                    </span>
                                </td>
                                <td class="{{ $tdNumberClass }} font-semibold text-gray-800 dark:text-white/90">PHP {{ number_format((float) ($reading->total_remitted ?? 0), 2) }}</td>
                                <td class="{{ $tdNumberClass }}">
                                    <span class="block font-medium text-gray-800 dark:text-white/90">PHP {{ number_format((float) ($reading->expected_collection ?? 0), 2) }}</span>
                                    <span class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">{{ number_format((float) ($reading->total_pump_liters ?? 0), 2) }} L</span>
                                </td>
                                <td class="{{ $tdNumberClass }}">
                                    <span class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $variance['label'] }}</span>
                                    <span class="block font-semibold {{ $variance['class'] }}">{{ $variance['display'] }}</span>
                                </td>
                                <td class="{{ $tdClass }}">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $status['class'] }}">{{ $status['label'] }}</span>
                                </td>
                                <td class="{{ $tdClass }}">
                                    <span class="line-clamp-2 break-words">{{ $feedback ?: 'No remarks' }}</span>
                                </td>
                                <td class="{{ $tdClass }}">
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" x-on:click="viewDetails({{ (int) $reading->id }})" class="{{ $viewIconButtonClass }}" title="View details" aria-label="View details">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" stroke="currentColor" stroke-width="1.8"/></svg>
                                        </button>
                                        @if ($canVoid)
                                            <button type="button" x-on:click="voidReading({{ (int) $reading->id }}, @js($periodLabel($reading)))" class="{{ $voidIconButtonClass }}" title="Void settlement" aria-label="Void settlement">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center">
                                    <p class="text-theme-sm font-medium text-gray-800 dark:text-white/90">No shift readings found.</p>
                                    <p class="mt-1 {{ $captionClass }}">Submitted settlement readings will appear here.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($readings->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                    {{ $readings->onEachSide(1)->links() }}
                </div>
            @endif
        </section>

        <div x-show="modal === 'form'" x-cloak class="fixed inset-0 z-[100010] flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-950/75 backdrop-blur-md" x-on:click="if (!saving) closeForm()"></div>
            <div class="relative w-full max-w-7xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <button type="button" x-on:click="closeForm()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400" aria-label="Close settlement form">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>

                <div class="border-b border-gray-100 bg-gray-50/70 p-5 pr-14 dark:border-gray-800 dark:bg-white/[0.02] md:p-6 md:pr-16">
                    <p class="{{ $labelClass }}">New Settlement</p>
                    <h2 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Submit Periodical Settlement</h2>
                </div>

                <form x-ref="form" x-on:submit.prevent="submitReading()" class="max-h-[82vh] overflow-y-auto p-5 custom-scrollbar md:p-6">
                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                        <div class="space-y-5">
                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <label class="block {{ $labelClass }}">
                                        <span>Period From</span>
                                        <input type="date" name="period_from" x-model="form.period_from" x-on:input="clearFieldError('period_from')" class="mt-1.5 {{ $inputClass }}" x-bind:class="fieldErrors.period_from ? 'border-error-400 focus:border-error-400 focus:ring-error-500/20' : ''">
                                    </label>
                                    <label class="block {{ $labelClass }}">
                                        <span>Period To</span>
                                        <input type="date" name="period_to" x-model="form.period_to" x-on:input="clearFieldError('period_to')" class="mt-1.5 {{ $inputClass }}" x-bind:class="fieldErrors.period_to ? 'border-error-400 focus:border-error-400 focus:ring-error-500/20' : ''">
                                    </label>
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Pump Meter Readings</h3>
                                        <p class="{{ $captionClass }}">Opening values are prefilled from the latest submitted reading.</p>
                                    </div>
                                    <span class="text-theme-xs font-medium tabular-nums text-gray-500 dark:text-gray-400" x-text="formatLiters(totalPumpLiters()) + ' total liters'"></span>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    @foreach ($pumpMap as $pump)
                                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <div class="mb-4 flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $pump['label'] }}</p>
                                                    <p class="{{ $captionClass }}">{{ $pump['fuel'] }}</p>
                                                </div>
                                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300" x-text="money(pumpSales({{ $pump['id'] }}))"></span>
                                            </div>

                                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <label class="block {{ $labelClass }}">
                                                    <span>Sales Opening</span>
                                                    <input type="number" step="0.01" name="pump{{ $pump['id'] }}_sales_start" x-model="form.pumps[{{ $pump['id'] }}].sales_start" x-on:input="clearFieldError('pump{{ $pump['id'] }}_sales_start')" class="mt-1.5 {{ $inputClass }}">
                                                </label>
                                                <label class="block {{ $labelClass }}">
                                                    <span>Sales Closing</span>
                                                    <input type="number" step="0.01" name="pump{{ $pump['id'] }}_sales_end" x-model="form.pumps[{{ $pump['id'] }}].sales_end" x-on:input="clearFieldError('pump{{ $pump['id'] }}_sales_end')" class="mt-1.5 {{ $inputClass }}" x-bind:class="fieldErrors.pump{{ $pump['id'] }}_sales_end ? 'border-error-400 focus:border-error-400 focus:ring-error-500/20' : ''">
                                                </label>
                                                <label class="block {{ $labelClass }}">
                                                    <span>Liters Opening</span>
                                                    <input type="number" step="0.01" name="pump{{ $pump['id'] }}_liters_start" x-model="form.pumps[{{ $pump['id'] }}].liters_start" x-on:input="clearFieldError('pump{{ $pump['id'] }}_liters_start')" class="mt-1.5 {{ $inputClass }}">
                                                </label>
                                                <label class="block {{ $labelClass }}">
                                                    <span>Liters Closing</span>
                                                    <input type="number" step="0.01" name="pump{{ $pump['id'] }}_liters_end" x-model="form.pumps[{{ $pump['id'] }}].liters_end" x-on:input="clearFieldError('pump{{ $pump['id'] }}_liters_end')" class="mt-1.5 {{ $inputClass }}" x-bind:class="fieldErrors.pump{{ $pump['id'] }}_liters_end ? 'border-error-400 focus:border-error-400 focus:ring-error-500/20' : ''">
                                                </label>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Dipstick Readings</h3>
                                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                    @foreach (['premium' => 'Premium', 'regular' => 'Regular', 'diesel' => 'Diesel'] as $fuelKey => $fuelLabel)
                                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $fuelLabel }}</p>
                                            <div class="mt-3 grid grid-cols-1 gap-3">
                                                <label class="block {{ $labelClass }}">
                                                    <span>Opening</span>
                                                    <input type="number" step="0.01" name="dipstick_{{ $fuelKey }}_start" x-model="form.dipstick.{{ $fuelKey }}_start" x-on:input="clearFieldError('dipstick_{{ $fuelKey }}_start')" class="mt-1.5 {{ $inputClass }}">
                                                </label>
                                                <label class="block {{ $labelClass }}">
                                                    <span>Current</span>
                                                    <input type="number" step="0.01" name="dipstick_{{ $fuelKey }}" x-model="form.dipstick.{{ $fuelKey }}" x-on:input="clearFieldError('dipstick_{{ $fuelKey }}')" class="mt-1.5 {{ $inputClass }}">
                                                </label>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Meter Photos</h3>
                                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                                    @for ($dispenser = 1; $dispenser <= 3; $dispenser++)
                                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Dispenser {{ $dispenser }}</p>
                                            <div class="mt-3 space-y-3">
                                                <label class="block {{ $labelClass }}">
                                                    <span>Liters Photo</span>
                                                    <input type="file" accept="image/*" name="dispenser_image_{{ $dispenser }}" x-on:change="setPhoto($event, 'liters', {{ $dispenser }})" class="mt-1.5 {{ $fileClass }}" x-bind:class="fieldErrors['photo_liters_{{ $dispenser }}'] ? 'border-error-400' : ''">
                                                </label>
                                                <template x-if="photoPreviews.liters[{{ $dispenser }}]">
                                                    <img x-bind:src="photoPreviews.liters[{{ $dispenser }}]" alt="" class="h-28 w-full rounded-lg border border-gray-200 object-contain dark:border-gray-800">
                                                </template>
                                                <label class="block {{ $labelClass }}">
                                                    <span>Sales Photo</span>
                                                    <input type="file" accept="image/*" name="dispenser_sales_image_{{ $dispenser }}" x-on:change="setPhoto($event, 'sales', {{ $dispenser }})" class="mt-1.5 {{ $fileClass }}" x-bind:class="fieldErrors['photo_sales_{{ $dispenser }}'] ? 'border-error-400' : ''">
                                                </label>
                                                <template x-if="photoPreviews.sales[{{ $dispenser }}]">
                                                    <img x-bind:src="photoPreviews.sales[{{ $dispenser }}]" alt="" class="h-28 w-full rounded-lg border border-gray-200 object-contain dark:border-gray-800">
                                                </template>
                                            </div>
                                        </article>
                                    @endfor
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-5 xl:sticky xl:top-4 xl:self-start">
                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Live Settlement Preview</h3>
                                        <p class="{{ $captionClass }}">Computed from the current form values.</p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-theme-xs font-medium" x-bind:class="varianceMeta().badge" x-text="varianceMeta().label"></span>
                                </div>

                                <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                                    <template x-for="row in previewRows()" :key="row.label">
                                        <div class="flex items-center justify-between gap-4 py-2.5">
                                            <span class="text-theme-sm text-gray-500 dark:text-gray-400" x-text="row.label"></span>
                                            <span class="text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-bind:class="row.className" x-text="row.value"></span>
                                        </div>
                                    </template>
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Cash Count</h3>
                                    <span class="text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="money(cashTotal())"></span>
                                </div>
                                <div class="space-y-2">
                                    <template x-for="denomination in denominations" :key="denomination.key">
                                        <div class="grid grid-cols-[minmax(0,1fr)_5rem_6.5rem] items-center gap-2">
                                            <div class="min-w-0">
                                                <p class="truncate text-theme-xs font-medium text-gray-700 dark:text-gray-300" x-text="denomination.label"></p>
                                                <p class="text-[11px] uppercase text-gray-400" x-text="denomination.type"></p>
                                            </div>
                                            <input type="number" min="0" step="1" x-model="form.cash[denomination.key]" x-bind:name="'cash_denominations[' + denomination.key + ']'" class="{{ $inputClass }} px-2 text-right">
                                            <span class="text-right text-theme-xs font-semibold tabular-nums text-gray-700 dark:text-gray-300" x-text="money(denominationAmount(denomination))"></span>
                                        </div>
                                    </template>
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">GCash</h3>
                                    <button type="button" x-on:click="addPaymentRow('gcash')" class="{{ $iconButtonClass }}" title="Add GCash row" aria-label="Add GCash row">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    </button>
                                </div>
                                <div class="space-y-3">
                                    <template x-for="(row, index) in form.gcash" :key="row.key">
                                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                            <div class="grid grid-cols-1 gap-2">
                                                <input type="text" x-model="row.reference_no" x-bind:name="'gcash_entries[' + index + '][reference_no]'" class="{{ $inputClass }}" placeholder="Reference no.">
                                                <div class="grid grid-cols-[minmax(0,1fr)_2.5rem] gap-2">
                                                    <input type="number" min="0" step="0.01" x-model="row.amount" x-bind:name="'gcash_entries[' + index + '][amount]'" class="{{ $inputClass }}" placeholder="Amount">
                                                    <button type="button" x-on:click="removePaymentRow('gcash', index)" class="{{ $iconButtonClass }}" title="Remove GCash row" aria-label="Remove GCash row">
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 7h12M9 7V5.75A1.75 1.75 0 0 1 10.75 4h2.5A1.75 1.75 0 0 1 15 5.75V7m-7 0 .75 12h6.5L16 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Cheque</h3>
                                    <button type="button" x-on:click="addPaymentRow('cheque')" class="{{ $iconButtonClass }}" title="Add cheque row" aria-label="Add cheque row">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    </button>
                                </div>
                                <div class="space-y-3">
                                    <template x-for="(row, index) in form.cheque" :key="row.key">
                                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                            <div class="grid grid-cols-1 gap-2">
                                                <input type="text" x-model="row.cheque_no" x-bind:name="'cheque_entries[' + index + '][cheque_no]'" class="{{ $inputClass }}" placeholder="Cheque no.">
                                                <div class="grid grid-cols-[minmax(0,1fr)_2.5rem] gap-2">
                                                    <input type="number" min="0" step="0.01" x-model="row.amount" x-bind:name="'cheque_entries[' + index + '][amount]'" class="{{ $inputClass }}" placeholder="Amount">
                                                    <button type="button" x-on:click="removePaymentRow('cheque', index)" class="{{ $iconButtonClass }}" title="Remove cheque row" aria-label="Remove cheque row">
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 7h12M9 7V5.75A1.75 1.75 0 0 1 10.75 4h2.5A1.75 1.75 0 0 1 15 5.75V7m-7 0 .75 12h6.5L16 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </section>
                        </aside>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <button type="button" x-on:click="closeForm()" class="{{ $secondaryButtonClass }}">Cancel</button>
                        <button type="submit" class="{{ $primaryButtonClass }}" x-bind:disabled="saving">
                            <span x-text="saving ? 'Submitting...' : 'Submit Settlement'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="modal === 'details'" x-cloak class="fixed inset-0 z-[100010] flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-950/75 backdrop-blur-md" x-on:click="closeDetails()"></div>
            <div class="relative w-full max-w-6xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <button type="button" x-on:click="closeDetails()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400" aria-label="Close settlement details">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>

                <div class="border-b border-gray-100 bg-gray-50/70 p-5 pr-14 dark:border-gray-800 dark:bg-white/[0.02] md:p-6 md:pr-16">
                    <p class="{{ $labelClass }}">Settlement Details</p>
                    <h2 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90" x-text="detailsTitle()"></h2>
                </div>

                <div class="max-h-[82vh] overflow-y-auto p-5 custom-scrollbar md:p-6">
                    <template x-if="details.loading">
                        <div class="py-14 text-center text-theme-sm text-gray-500 dark:text-gray-400">Loading settlement details...</div>
                    </template>

                    <template x-if="details.error">
                        <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-theme-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300" x-text="details.error"></div>
                    </template>

                    <template x-if="details.reading && !details.loading && !details.error">
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <p class="{{ $labelClass }}">Total Remitted</p>
                                    <p class="mt-2 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="money(details.reading.total_remitted)"></p>
                                </article>
                                <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <p class="{{ $labelClass }}">Expected</p>
                                    <p class="mt-2 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="money(details.reading.expected_collection)"></p>
                                </article>
                                <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <p class="{{ $labelClass }}">Pump Liters</p>
                                    <p class="mt-2 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatLiters(details.reading.total_pump_liters)"></p>
                                </article>
                                <article class="rounded-xl border p-4" x-bind:class="varianceState(details.reading.cash_variance).boxClass">
                                    <p class="text-theme-xs font-medium uppercase" x-text="varianceState(details.reading.cash_variance).label"></p>
                                    <p class="mt-2 text-lg font-semibold tabular-nums" x-text="varianceState(details.reading.cash_variance).value"></p>
                                </article>
                            </div>

                            <template x-if="feedbackText()">
                                <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300" x-text="feedbackText()"></div>
                            </template>

                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Pump Breakdown</h3>
                                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    <template x-for="pump in pumps" :key="pump.id">
                                        <article class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                            <div class="mb-3 flex items-center justify-between">
                                                <p class="text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="pump.label"></p>
                                                <span class="text-theme-xs text-gray-500 dark:text-gray-400" x-text="pump.fuel"></span>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/[0.03]">
                                                    <p class="{{ $labelClass }}">Sales</p>
                                                    <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="money(readingPumpValue(pump.id, 'dispensed_sales'))"></p>
                                                </div>
                                                <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/[0.03]">
                                                    <p class="{{ $labelClass }}">Liters</p>
                                                    <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatLiters(readingPumpValue(pump.id, 'dispensed_liters'))"></p>
                                                </div>
                                            </div>
                                        </article>
                                    </template>
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Meter Photos</h3>
                                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <template x-for="image in details.images" :key="image.image_type + '-' + image.dispenser">
                                        <button type="button" x-on:click="openLightbox(image.image_url)" class="overflow-hidden rounded-lg border border-gray-200 text-left dark:border-gray-800">
                                            <img x-bind:src="image.image_url" alt="" class="h-40 w-full bg-gray-50 object-contain dark:bg-gray-950">
                                            <div class="flex items-center justify-between px-3 py-2">
                                                <span class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400" x-text="'Dispenser ' + image.dispenser"></span>
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium uppercase text-gray-600 dark:bg-white/[0.06] dark:text-gray-300" x-text="image.image_type"></span>
                                            </div>
                                        </button>
                                    </template>
                                    <template x-if="!details.images.length">
                                        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-theme-sm text-gray-500 dark:border-gray-700 dark:text-gray-400 md:col-span-3">No photos found.</div>
                                    </template>
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Financial Reconciliation</h3>
                                <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                                    <template x-for="row in financialRows()" :key="row.key">
                                        <details class="group">
                                            <summary class="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-3 py-3 [&::-webkit-details-marker]:hidden">
                                                <span class="text-theme-sm text-gray-600 dark:text-gray-400" x-text="row.label"></span>
                                                <span class="text-theme-sm font-semibold tabular-nums" x-bind:class="row.className" x-text="row.display"></span>
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition group-open:rotate-180 dark:border-gray-800 dark:text-gray-400">
                                                    <svg class="size-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </span>
                                            </summary>
                                            <div class="pb-3">
                                                <template x-if="row.items.length">
                                                    <div class="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                                                        <table class="min-w-[560px] w-full">
                                                            <tbody>
                                                                <template x-for="(item, index) in row.items" :key="index">
                                                                    <tr class="border-t border-gray-100 first:border-t-0 dark:border-gray-800">
                                                                        <template x-for="cell in detailItemCells(row.key, item)" :key="cell.label">
                                                                            <td class="px-3 py-2 align-top text-theme-xs text-gray-700 dark:text-gray-300">
                                                                                <p class="text-[11px] font-medium uppercase text-gray-400" x-text="cell.label"></p>
                                                                                <p class="mt-0.5 break-words" x-bind:class="cell.className" x-text="cell.value"></p>
                                                                            </td>
                                                                        </template>
                                                                    </tr>
                                                                </template>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </template>
                                                <template x-if="!row.items.length">
                                                    <div class="rounded-lg bg-gray-50 px-3 py-4 text-center text-theme-xs text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">No breakdown records.</div>
                                                </template>
                                            </div>
                                        </details>
                                    </template>
                                </div>
                            </section>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div x-show="messageDialog.open" x-cloak class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4" style="z-index: 100250;">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md" x-on:click="closeMessageDialog()"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <button type="button" x-on:click="closeMessageDialog()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400" aria-label="Close message">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
                <div class="p-6 pr-14">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" x-bind:class="messageDialog.type === 'info' ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'">
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

        <div x-show="confirmation.open" x-cloak class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4" style="z-index: 100260;">
            <div class="fixed inset-0 bg-gray-950/85 backdrop-blur-md" x-on:click="resolveConfirmation(false)"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" x-bind:class="confirmation.tone === 'danger' ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400' : confirmation.tone === 'success' ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500' : 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8.5v4.25M12 16h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="confirmation.title"></h2>
                            <p class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="confirmation.message"></p>
                            <p x-show="confirmation.details" x-cloak class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="confirmation.details"></p>
                        </div>
                    </div>

                    <label x-show="confirmation.needsInput" x-cloak class="mt-5 block {{ $labelClass }}">
                        <span x-text="confirmation.inputLabel"></span>
                        <textarea x-model="confirmation.inputValue" rows="3" class="mt-1.5 {{ $textareaClass }}" x-bind:placeholder="confirmation.inputPlaceholder"></textarea>
                    </label>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" x-on:click="resolveConfirmation(false)" class="{{ $secondaryButtonClass }}" x-text="confirmation.cancelText"></button>
                        <button type="button" x-ref="confirmPrimary" x-on:click="resolveConfirmation(true)" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg px-4 text-theme-sm font-medium text-white shadow-theme-xs transition disabled:cursor-not-allowed disabled:opacity-60" x-bind:class="confirmation.tone === 'danger' ? 'bg-error-500 hover:bg-error-600' : confirmation.tone === 'success' ? 'bg-success-500 hover:bg-success-600' : 'bg-brand-500 hover:bg-brand-600'" x-text="confirmation.confirmText"></button>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="lightbox.open" x-cloak class="fixed inset-0 flex items-center justify-center bg-gray-950/90 p-4" style="z-index: 100400;" x-on:click="closeLightbox()">
            <button type="button" class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Close image">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
            <img x-bind:src="lightbox.url" alt="" class="max-h-[88vh] max-w-[94vw] rounded-xl object-contain shadow-theme-xl" x-on:click.stop>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function shiftReadingsModule(config) {
            return {
                pumps: config.pumps || [],
                denominations: config.denominations || [],
                liveSales: config.liveSales || [],
                toast: { open: false, message: '', timer: null },
                modal: null,
                saving: false,
                fieldErrors: {},
                form: {},
                photoPreviews: { liters: {}, sales: {} },
                messageDialog: { open: false, type: 'error', title: '', message: '' },
                confirmation: {
                    open: false,
                    title: '',
                    message: '',
                    details: '',
                    confirmText: 'Confirm',
                    cancelText: 'Cancel',
                    tone: 'brand',
                    needsInput: false,
                    inputLabel: '',
                    inputPlaceholder: '',
                    inputValue: '',
                    resolver: null
                },
                details: { loading: false, error: '', reading: null, images: [], breakdowns: {} },
                lightbox: { open: false, url: '' },
                init() {
                    this.resetForm();
                    this.restoreToast();
                },
                today() {
                    const date = new Date();
                    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
                    return date.toISOString().slice(0, 10);
                },
                resetForm() {
                    const prev = config.prevReadings || {};
                    const pumps = {};

                    this.pumps.forEach((pump) => {
                        pumps[pump.id] = {
                            sales_start: Number(prev['p' + pump.id + '_sales'] || 0).toFixed(2),
                            sales_end: '',
                            liters_start: Number(prev['p' + pump.id + '_liters'] || 0).toFixed(2),
                            liters_end: ''
                        };
                    });

                    const cash = {};
                    this.denominations.forEach((denomination) => {
                        cash[denomination.key] = '';
                    });

                    this.form = {
                        period_from: this.today(),
                        period_to: this.today(),
                        pumps,
                        dipstick: {
                            premium_start: Number(prev.dipstick_premium || 0).toFixed(2),
                            premium: '',
                            regular_start: Number(prev.dipstick_regular || 0).toFixed(2),
                            regular: '',
                            diesel_start: Number(prev.dipstick_diesel || 0).toFixed(2),
                            diesel: ''
                        },
                        cash,
                        gcash: [this.blankPaymentRow('gcash')],
                        cheque: [this.blankPaymentRow('cheque')]
                    };

                    this.fieldErrors = {};
                    this.photoPreviews = { liters: {}, sales: {} };
                    this.saving = false;
                },
                openForm() {
                    this.resetForm();
                    this.modal = 'form';
                },
                closeForm() {
                    if (this.saving) return;
                    this.modal = null;
                },
                clearFieldError(field) {
                    delete this.fieldErrors[field];
                },
                setFieldError(field) {
                    this.fieldErrors[field] = true;
                },
                setPhoto(event, type, dispenser) {
                    const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;

                    if (!file) {
                        delete this.photoPreviews[type][dispenser];
                        return;
                    }

                    this.photoPreviews[type][dispenser] = URL.createObjectURL(file);
                    this.clearFieldError('photo_' + type + '_' + dispenser);
                },
                blankPaymentRow(type) {
                    return type === 'gcash'
                        ? { key: this.rowKey(), reference_no: '', amount: '' }
                        : { key: this.rowKey(), cheque_no: '', amount: '' };
                },
                rowKey() {
                    return window.crypto && window.crypto.randomUUID
                        ? window.crypto.randomUUID()
                        : String(Date.now() + Math.random());
                },
                addPaymentRow(type) {
                    this.form[type].push(this.blankPaymentRow(type));
                },
                removePaymentRow(type, index) {
                    if (this.form[type].length === 1) {
                        this.form[type][0] = this.blankPaymentRow(type);
                        return;
                    }

                    this.form[type].splice(index, 1);
                },
                number(value) {
                    return Number(value || 0);
                },
                money(value) {
                    return 'PHP ' + Number(value || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                },
                formatLiters(value) {
                    return Number(value || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + ' L';
                },
                pumpSales(id) {
                    const pump = this.form.pumps[id] || {};
                    return Math.max(0, this.number(pump.sales_end) - this.number(pump.sales_start));
                },
                pumpLiters(id) {
                    const pump = this.form.pumps[id] || {};
                    return Math.max(0, this.number(pump.liters_end) - this.number(pump.liters_start));
                },
                totalPumpSales() {
                    return this.pumps.reduce((total, pump) => total + this.pumpSales(pump.id), 0);
                },
                totalPumpLiters() {
                    return this.pumps.reduce((total, pump) => total + this.pumpLiters(pump.id), 0);
                },
                saleInPeriod(sale) {
                    if (!sale.created_at || !this.form.period_from || !this.form.period_to) {
                        return true;
                    }

                    const saleDate = new Date(String(sale.created_at).replace(' ', 'T'));
                    const start = new Date(this.form.period_from + 'T00:00:00');
                    const end = new Date(this.form.period_to + 'T23:59:59');

                    return saleDate >= start && saleDate <= end;
                },
                liveAdjustments() {
                    return this.liveSales
                        .filter((sale) => this.saleInPeriod(sale))
                        .reduce((totals, sale) => {
                            const type = String(sale.payment_type || '').toLowerCase();

                            if (type === 'credit') {
                                totals.credit += this.number(sale.amount);
                            }

                            if (type === 'collection') {
                                totals.collections += this.number(sale.amount);
                            }

                            totals.discount += this.number(sale.discount);
                            totals.manualDiscount += this.number(sale.manual_discount);
                            totals.surcharge += this.number(sale.surcharge);

                            return totals;
                        }, { credit: 0, collections: 0, discount: 0, manualDiscount: 0, surcharge: 0 });
                },
                expectedCollection() {
                    const adjustments = this.liveAdjustments();
                    return this.totalPumpSales()
                        + adjustments.surcharge
                        + adjustments.collections
                        - adjustments.credit
                        - adjustments.discount
                        - adjustments.manualDiscount;
                },
                denominationAmount(denomination) {
                    return Math.max(0, Number(this.form.cash[denomination.key] || 0)) * Number(denomination.value || 0);
                },
                cashTotal() {
                    return this.denominations.reduce((total, denomination) => total + this.denominationAmount(denomination), 0);
                },
                paymentTotal(type) {
                    return this.form[type].reduce((total, row) => total + Math.max(0, this.number(row.amount)), 0);
                },
                totalRemitted() {
                    return this.cashTotal() + this.paymentTotal('gcash') + this.paymentTotal('cheque');
                },
                variance() {
                    return this.totalRemitted() - this.expectedCollection();
                },
                varianceMeta() {
                    const variance = this.variance();

                    if (Math.abs(variance) < 0.01) {
                        return { label: 'Balanced', badge: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500' };
                    }

                    if (variance < 0) {
                        return { label: 'Shortage', badge: 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-500' };
                    }

                    return { label: 'Overage', badge: 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400' };
                },
                previewRows() {
                    const adjustments = this.liveAdjustments();

                    return [
                        { label: 'Gross Pump Sales', value: this.money(this.totalPumpSales()) },
                        { label: 'Excess Markup', value: '+' + this.money(adjustments.surcharge), className: 'text-brand-600 dark:text-brand-400' },
                        { label: 'Account Payments', value: '+' + this.money(adjustments.collections), className: 'text-success-600 dark:text-success-500' },
                        { label: 'Credit Sales', value: '-' + this.money(adjustments.credit), className: 'text-error-600 dark:text-error-500' },
                        { label: 'Discounts', value: '-' + this.money(adjustments.discount + adjustments.manualDiscount), className: 'text-error-600 dark:text-error-500' },
                        { label: 'Expected Collection', value: this.money(this.expectedCollection()), className: 'text-brand-600 dark:text-brand-400' },
                        { label: 'Actual Remitted', value: this.money(this.totalRemitted()) },
                        { label: 'Cash Variance', value: (this.variance() > 0 ? '+' : '') + this.money(this.variance()), className: this.variance() < -0.01 ? 'text-error-600 dark:text-error-500' : 'text-success-600 dark:text-success-500' }
                    ];
                },
                validatePaymentRows(type) {
                    const referenceKey = type === 'gcash' ? 'reference_no' : 'cheque_no';
                    const label = type === 'gcash' ? 'GCash' : 'Cheque';

                    for (const row of this.form[type]) {
                        const reference = String(row[referenceKey] || '').trim();
                        const amount = this.number(row.amount);

                        if ((reference && amount <= 0) || (!reference && amount > 0)) {
                            return label + ' reference and amount are required for every entered row.';
                        }
                    }

                    return '';
                },
                validateForm() {
                    this.fieldErrors = {};

                    if (!this.form.period_from || !this.form.period_to) {
                        this.setFieldError('period_from');
                        this.setFieldError('period_to');
                        return 'Select the settlement period.';
                    }

                    if (this.form.period_from > this.form.period_to) {
                        this.setFieldError('period_to');
                        return 'The period end cannot be earlier than the period start.';
                    }

                    for (const pump of this.pumps) {
                        const row = this.form.pumps[pump.id] || {};

                        for (const field of ['sales_start', 'sales_end', 'liters_start', 'liters_end']) {
                            if (row[field] === '' || row[field] === null || Number.isNaN(Number(row[field]))) {
                                this.setFieldError('pump' + pump.id + '_' + field);
                                return pump.label + ' has an incomplete meter reading.';
                            }
                        }

                        if (this.number(row.sales_end) < this.number(row.sales_start)) {
                            this.setFieldError('pump' + pump.id + '_sales_end');
                            return pump.label + ' sales closing cannot be lower than opening.';
                        }

                        if (this.number(row.liters_end) < this.number(row.liters_start)) {
                            this.setFieldError('pump' + pump.id + '_liters_end');
                            return pump.label + ' liters closing cannot be lower than opening.';
                        }
                    }

                    for (const field of ['premium_start', 'premium', 'regular_start', 'regular', 'diesel_start', 'diesel']) {
                        if (this.form.dipstick[field] === '' || this.form.dipstick[field] === null || Number.isNaN(Number(this.form.dipstick[field]))) {
                            this.setFieldError('dipstick_' + field);
                            return 'Complete all dipstick readings.';
                        }
                    }

                    for (const dispenser of [1, 2, 3]) {
                        for (const type of ['liters', 'sales']) {
                            if (!this.photoPreviews[type][dispenser]) {
                                this.setFieldError('photo_' + type + '_' + dispenser);
                                return 'Upload both liters and sales photos for every dispenser.';
                            }
                        }
                    }

                    return this.validatePaymentRows('gcash') || this.validatePaymentRows('cheque');
                },
                async submitReading() {
                    const validationMessage = this.validateForm();

                    if (validationMessage) {
                        this.showNotice('error', validationMessage);
                        return;
                    }

                    const confirmed = await this.askConfirmation({
                        title: 'Submit Settlement',
                        message: 'Submit this settlement for admin verification?',
                        details: 'Expected collection: ' + this.money(this.expectedCollection()) + ' / Variance: ' + this.money(this.variance()),
                        confirmText: 'Submit Settlement',
                        tone: 'success'
                    });

                    if (!confirmed) return;

                    this.saving = true;

                    try {
                        const response = await fetch(config.routes.store, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken
                            },
                            body: new FormData(this.$refs.form)
                        });
                        let data = {};

                        try {
                            data = await response.json();
                        } catch (error) {
                            data = {};
                        }

                        if (!response.ok || !data.success) {
                            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                            this.showNotice('error', firstError || data.message || 'Unable to submit settlement.');
                            return;
                        }

                        this.reloadWithToast(data.message || 'Settlement submitted.');
                    } catch (error) {
                        this.showNotice('error', 'An unexpected error occurred.');
                    } finally {
                        this.saving = false;
                    }
                },
                async viewDetails(id) {
                    this.modal = 'details';
                    this.details = { loading: true, error: '', reading: null, images: [], breakdowns: {} };

                    try {
                        const response = await fetch(config.routes.detailsBase + '/' + id + '/details', {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message || 'Could not load settlement details.');
                        }

                        this.details = {
                            loading: false,
                            error: '',
                            reading: data.reading || {},
                            images: Array.isArray(data.meter_images) ? data.meter_images : [],
                            breakdowns: data.transaction_breakdowns || {}
                        };
                    } catch (error) {
                        this.details = { loading: false, error: error.message || 'Could not load settlement details.', reading: null, images: [], breakdowns: {} };
                    }
                },
                closeDetails() {
                    this.modal = null;
                    this.details = { loading: false, error: '', reading: null, images: [], breakdowns: {} };
                },
                detailsTitle() {
                    if (this.details.loading) return 'Loading...';
                    if (!this.details.reading) return 'Settlement';
                    return this.dateLabel(this.details.reading) + ' / ' + this.statusLabel(this.details.reading.status);
                },
                dateLabel(reading) {
                    if (!reading) return 'No period selected';
                    const from = reading.period_from || reading.created_at;
                    const to = reading.period_to || from;

                    if (!from) return 'No period selected';

                    const fromDate = new Date(String(from).slice(0, 10) + 'T00:00:00');
                    const toDate = new Date(String(to).slice(0, 10) + 'T00:00:00');
                    const fromText = fromDate.toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
                    const toText = toDate.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });

                    return fromDate.toDateString() === toDate.toDateString()
                        ? toDate.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
                        : fromText + ' - ' + toText;
                },
                statusLabel(status) {
                    const value = String(status || 'Pending').toLowerCase();
                    if (value.includes('verified') || value.includes('approved')) return 'Verified';
                    if (value.includes('reject')) return 'Rejected';
                    if (value.includes('void')) return 'Voided';
                    return 'Pending Verification';
                },
                varianceState(value) {
                    const amount = Number(value || 0);

                    if (Math.abs(amount) < 0.01) {
                        return {
                            label: 'Balanced',
                            value: 'PHP 0.00',
                            boxClass: 'border-success-200 bg-success-50 text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-500'
                        };
                    }

                    if (amount < 0) {
                        return {
                            label: 'Shortage',
                            value: '-PHP ' + Math.abs(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                            boxClass: 'border-error-200 bg-error-50 text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400'
                        };
                    }

                    return {
                        label: 'Overage',
                        value: '+PHP ' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                        boxClass: 'border-brand-200 bg-brand-50 text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400'
                    };
                },
                feedbackText() {
                    const reading = this.details.reading || {};
                    return [reading.rejection_reason, reading.admin_remarks, reading.void_reason]
                        .filter((value) => String(value || '').trim() !== '')
                        .join(' | ');
                },
                readingPumpValue(id, suffix) {
                    const reading = this.details.reading || {};
                    return Number(reading['pump' + id + '_' + suffix] || 0);
                },
                financialRows() {
                    const reading = this.details.reading || {};
                    const breakdowns = this.details.breakdowns || {};
                    const cashRows = Array.isArray(reading.cash_denominations)
                        ? reading.cash_denominations.filter((item) => Number(item.pieces || 0) > 0 || Number(item.amount || 0) > 0)
                        : [];
                    const gcashRows = Array.isArray(reading.gcash_entries) ? reading.gcash_entries : [];
                    const chequeRows = Array.isArray(reading.cheque_entries) ? reading.cheque_entries : [];

                    return [
                        this.financialRow('gross', 'Total Gross Pump Sales', reading.total_pump_sales, '', 'text-gray-800 dark:text-white/90', []),
                        this.financialRow('excess_markups', 'Excess Markup / Surcharge', reading.surcharge, '+', 'text-brand-600 dark:text-brand-400', breakdowns.excess_markups || []),
                        this.financialRow('payments_collected', 'Account Payments Collected', reading.payments_collected, '+', 'text-success-600 dark:text-success-500', breakdowns.payments_collected || []),
                        this.financialRow('credit_sales', 'Gross Credit Sales', reading.gross_credit_sales, '-', 'text-error-600 dark:text-error-500', breakdowns.credit_sales || []),
                        this.financialRow('system_discounts', 'System Discounts', reading.auto_discount, '-', 'text-error-600 dark:text-error-500', breakdowns.system_discounts || []),
                        this.financialRow('manual_discounts', 'Manual Discounts', reading.manual_discount, '-', 'text-error-600 dark:text-error-500', breakdowns.manual_discounts || []),
                        this.financialRow('cash_denominations', 'Physical Cash Remitted', reading.cash_on_hand, '', 'text-gray-800 dark:text-white/90', cashRows),
                        this.financialRow('gcash_entries', 'GCash Received', reading.gcash_amount, '', 'text-success-600 dark:text-success-500', gcashRows),
                        this.financialRow('cheque_entries', 'Cheque Received', reading.cheque_amount, '', 'text-brand-600 dark:text-brand-400', chequeRows),
                        this.financialRow('total_remitted', 'Total Actual Remittance', reading.total_remitted, '', 'text-gray-800 dark:text-white/90', [])
                    ];
                },
                financialRow(key, label, amount, prefix, className, items) {
                    return {
                        key,
                        label,
                        amount: Number(amount || 0),
                        display: prefix + this.money(amount),
                        className,
                        items: Array.isArray(items) ? items : []
                    };
                },
                detailItemCells(key, item) {
                    if (key === 'cash_denominations') {
                        return [
                            { label: 'Type', value: String(item.type || '-').toUpperCase() },
                            { label: 'Denomination', value: this.money(item.value) },
                            { label: 'Pieces', value: Number(item.pieces || 0).toLocaleString('en-US') },
                            { label: 'Amount', value: this.money(item.amount), className: 'font-semibold tabular-nums' }
                        ];
                    }

                    if (key === 'gcash_entries' || key === 'cheque_entries') {
                        return [
                            { label: key === 'gcash_entries' ? 'Reference' : 'Cheque No.', value: item.reference_no || item.cheque_no || '-' },
                            { label: 'Amount', value: this.money(item.amount), className: 'font-semibold tabular-nums' }
                        ];
                    }

                    const amountField = {
                        excess_markups: 'surcharge',
                        payments_collected: 'amount',
                        credit_sales: 'amount',
                        system_discounts: 'discount',
                        manual_discounts: 'manual_discount'
                    }[key] || 'amount';

                    return [
                        { label: 'Time', value: item.recorded_at || '-' },
                        { label: 'Ref', value: item.reference_no || item.or_number || ('TXN-' + (item.id || '')) },
                        { label: 'Account', value: item.customer_display || item.company_name || item.customer_name || 'Walk-In Customer' },
                        { label: 'Pump', value: item.pump_label || 'N/A' },
                        { label: 'Product', value: item.product_label || item.fuel_type || 'N/A' },
                        { label: 'Liters', value: this.formatLiters(item.liters) },
                        { label: 'Amount', value: this.money(item[amountField]), className: 'font-semibold tabular-nums' }
                    ];
                },
                openLightbox(url) {
                    if (!url) return;
                    this.lightbox = { open: true, url };
                },
                closeLightbox() {
                    this.lightbox = { open: false, url: '' };
                },
                async voidReading(id, label) {
                    const reason = await this.askConfirmation({
                        title: 'Void Settlement',
                        message: 'Void this settlement and unlock its transactions?',
                        details: label,
                        confirmText: 'Void Settlement',
                        tone: 'danger',
                        needsInput: true,
                        inputLabel: 'Void Reason',
                        inputPlaceholder: 'Optional note'
                    });

                    if (reason === false) return;

                    try {
                        const response = await fetch(config.routes.voidTemplate.replace('__ID__', id), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken
                            },
                            body: JSON.stringify({ void_reason: reason || '' })
                        });
                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            this.showNotice('error', data.message || 'Unable to void settlement.');
                            return;
                        }

                        this.reloadWithToast(data.message || 'Settlement voided.');
                    } catch (error) {
                        this.showNotice('error', 'An unexpected error occurred.');
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
                reloadWithToast(message) {
                    try {
                        window.sessionStorage.setItem('staff-reading-toast', message);
                    } catch (error) {
                        this.showToast(message);
                    }

                    window.location.reload();
                },
                restoreToast() {
                    try {
                        const message = window.sessionStorage.getItem('staff-reading-toast') || '';
                        window.sessionStorage.removeItem('staff-reading-toast');

                        if (message) {
                            this.$nextTick(() => this.showToast(message));
                        }
                    } catch (error) {
                        return;
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
                    this.messageDialog.open = false;
                },
                askConfirmation(options = {}) {
                    return new Promise((resolve) => {
                        this.confirmation = {
                            open: true,
                            title: options.title || 'Confirm Action',
                            message: options.message || 'Please confirm this action.',
                            details: options.details || '',
                            confirmText: options.confirmText || 'Confirm',
                            cancelText: options.cancelText || 'Cancel',
                            tone: options.tone || 'brand',
                            needsInput: Boolean(options.needsInput),
                            inputLabel: options.inputLabel || '',
                            inputPlaceholder: options.inputPlaceholder || '',
                            inputValue: '',
                            resolver: resolve
                        };

                        this.$nextTick(() => this.$refs.confirmPrimary?.focus());
                    });
                },
                resolveConfirmation(confirmed) {
                    const resolver = this.confirmation.resolver;
                    const value = confirmed
                        ? (this.confirmation.needsInput ? this.confirmation.inputValue : true)
                        : false;

                    this.confirmation.open = false;
                    this.confirmation.resolver = null;

                    if (resolver) {
                        resolver(value);
                    }
                }
            };
        }
    </script>
@endpush
