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
            'submission_count' => 0,
            'pump_entry_count' => 0,
            'fuel_breakdown' => [],
            'tank_breakdown' => [],
            'reached_limits' => [],
            'configured_limit_count' => 0,
            'status' => [
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
        $tankBreakdown = collect($summary['tank_breakdown'] ?? []);
        $reachedLimits = collect($summary['reached_limits'] ?? []);
        $missingRequirements = $missingRequirements ?? [];

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $valueClass = 'mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90';
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
                return ['badge' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400', 'dot' => 'bg-warning-500', 'bar' => 'bg-warning-500'];
            }

            if (str_contains($fuel, 'regular') || str_contains($fuel, 'unleaded')) {
                return ['badge' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500', 'dot' => 'bg-success-500', 'bar' => 'bg-success-500'];
            }

            if (str_contains($fuel, 'premium')) {
                return ['badge' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400', 'dot' => 'bg-error-500', 'bar' => 'bg-error-500'];
            }

            return ['badge' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400', 'dot' => 'bg-brand-500', 'bar' => 'bg-brand-500'];
        };

        $readingStatusStyle = function ($status) {
            $status = strtolower(trim((string) $status));

            if (str_contains($status, 'verified') || str_contains($status, 'approved')) {
                return ['label' => 'Verified', 'class' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500'];
            }

            return ['label' => 'Pending', 'class' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400'];
        };
    @endphp

    <x-common.page-breadcrumb pageTitle="Liters Tracking" />

    <div class="space-y-6">
        @if ($missingRequirements)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Missing tracking requirement(s): {{ implode(', ', $missingRequirements) }}.
            </div>
        @endif

        @if ($reachedLimits->isNotEmpty())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-theme-sm text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300">
                <span class="font-semibold">Liters limit reached:</span>
                {{ $reachedLimits->map(fn ($tank) => ($tank['name'] ?? $tank['fuel_type'] ?? 'Tank') . ' (' . number_format((float) ($tank['liters'] ?? 0), 2) . ' L / ' . number_format((float) ($tank['limit'] ?? 0), 2) . ' L)')->implode(', ') }}.
                Shift readings can still be submitted; this is an alert only.
            </div>
        @endif

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Staff Module</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Liters Tracking</h1>
                    <p class="mt-2 {{ $captionClass }}">
                        Shared period {{ $period->period_code ?? 'Pending Migration' }} started {{ $period ? $dateLabel($period->started_at, 'M d, Y h:i A') : 'after migration' }}. All staff see the same submitted shift-reading totals and limits.
                    </p>
                </div>

                <span class="inline-flex w-fit rounded-full px-3 py-1 text-theme-xs font-medium {{ $summary['status']['badge_class'] }}">
                    {{ $summary['status']['label'] }}
                </span>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Total Liters</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['total_liters'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">{{ number_format((int) $summary['submission_count']) }} shift submission{{ (int) $summary['submission_count'] === 1 ? '' : 's' }}</p>
            </article>
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Configured Limits</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['limit'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">Shared across {{ number_format((int) ($summary['configured_limit_count'] ?? 0)) }} tank{{ (int) ($summary['configured_limit_count'] ?? 0) === 1 ? '' : 's' }}</p>
            </article>
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Remaining Liters</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['remaining_liters'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">{{ (float) $summary['over_liters'] > 0 ? number_format((float) $summary['over_liters'], 2) . ' L over limit' : 'Before limit' }}</p>
            </article>
            <article class="{{ $cardClass }} {{ $summary['status']['border_class'] }}">
                <p class="{{ $labelClass }}">Status</p>
                <p class="{{ $valueClass }}">{{ $summary['status']['label'] }}</p>
                <p class="mt-1 {{ $captionClass }}">{{ number_format((float) $summary['percent'], 2) }}% used</p>
            </article>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Shared Tank Limits</h2>
                <p class="mt-1 {{ $captionClass }}">P1/P3 Premium, P2/P4 Unleaded, and P5/P6 Diesel are accumulated from every pending or verified shift submission.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                @foreach ($tankBreakdown as $tank)
                    @php
                        $style = $fuelStyle($tank['fuel_type'] ?? '');
                    @endphp
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $tank['name'] ?? $tank['fuel_type'] ?? 'Tank' }}</p>
                                <p class="mt-1 {{ $captionClass }}">{{ $tank['connected_pumps_display'] ?? 'N/A' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $tank['status']['badge_class'] ?? '' }}">{{ $tank['status']['label'] ?? 'Normal' }}</span>
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <p class="text-xl font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) ($tank['liters'] ?? 0), 2) }} L</p>
                            <p class="{{ $captionClass }}">{{ ($tank['has_limit'] ?? false) ? number_format((float) ($tank['limit'] ?? 0), 2) . ' L limit' : 'No limit' }}</p>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white dark:bg-white/[0.08]">
                            <div class="h-full rounded-full {{ $style['bar'] }}" style="width: {{ $tank['progress_width'] ?? 0 }}%"></div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Fuel Breakdown</h2>
                <p class="mt-1 {{ $captionClass }}">Current active tracking period.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach ($summary['fuel_breakdown'] as $fuel)
                    @php
                        $style = $fuelStyle($fuel['fuel_type'] ?? '');
                    @endphp
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $style['badge'] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                            {{ $fuel['fuel_type'] }}
                        </span>
                        <p class="mt-4 text-xl font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) $fuel['liters'], 2) }} L</p>
                        <p class="mt-1 {{ $captionClass }}">&#8369;{{ number_format((float) $fuel['amount'], 2) }} meter sales</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Included Shift Reading Submissions</h2>
                    <p class="mt-1 {{ $captionClass }}">This shared view includes every staff member's Pending and Verified submissions.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">
                    {{ number_format((int) $summary['submission_count']) }} submissions
                </span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[1120px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[15%]">
                            <col class="w-[15%]">
                            <col class="w-[16%]">
                            <col class="w-[24%]">
                            <col class="w-[10%]">
                            <col class="w-[12%]">
                            <col class="w-[8%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Submitted</th>
                                <th class="{{ $thClass }}">Staff</th>
                                <th class="{{ $thClass }}">Reading Period</th>
                                <th class="{{ $thClass }}">Pump Meter Deltas</th>
                                <th class="{{ $thNumberClass }}">Liters</th>
                                <th class="{{ $thNumberClass }}">Meter Sales</th>
                                <th class="{{ $thClass }}">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($transactionItems as $transaction)
                                @php
                                    $pumpEntries = collect($transaction->pump_entries ?? []);
                                    $readingStatus = $readingStatusStyle($transaction->status ?? 'Pending');
                                @endphp
                                <tr>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $dateLabel($transaction->created_at ?? null, 'M d, Y') }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $dateLabel($transaction->created_at ?? null, 'h:i A') }}</div>
                                    </td>
                                    <td class="{{ $tdPrimaryClass }}">{{ $transaction->staff_name ?? 'Staff' }}</td>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $dateLabel($transaction->period_from ?? null, 'M d, Y') }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">to {{ $dateLabel($transaction->period_to ?? null, 'M d, Y') }}</div>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="flex flex-wrap gap-1.5">
                                            @forelse ($pumpEntries as $entry)
                                                @php
                                                    $fuel = $fuelStyle($entry['fuel_type'] ?? '');
                                                @endphp
                                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $fuel['badge'] }}">
                                                    P{{ (int) ($entry['pump_id'] ?? 0) }}: {{ number_format((float) ($entry['liters'] ?? 0), 2) }} L
                                                </span>
                                            @empty
                                                <span class="text-theme-xs text-gray-500 dark:text-gray-400">No positive meter movement</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format((float) ($transaction->total_liters ?? 0), 2) }} L</td>
                                    <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) ($transaction->total_amount ?? 0), 2) }}</td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $readingStatus['class'] }}">{{ $readingStatus['label'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                        No pending or verified shift-reading submissions in this period yet.
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
    </div>
@endsection
