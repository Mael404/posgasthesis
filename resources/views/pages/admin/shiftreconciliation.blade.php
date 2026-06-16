@extends('layouts.app')

@section('content')
    @php
        $summary = array_merge([
            'total' => 0,
            'pending' => 0,
            'verified' => 0,
            'rejected' => 0,
        ], $summary ?? []);

        $cardClass = 'rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
        $buttonClass = 'inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-theme-xs font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $viewIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 shadow-theme-xs transition hover:border-brand-300 hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/15';
        $remarksIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-warning-200 bg-warning-50 text-warning-700 shadow-theme-xs transition hover:border-warning-300 hover:bg-warning-100 dark:border-warning-500/25 dark:bg-warning-500/10 dark:text-warning-400 dark:hover:bg-warning-500/15';
        $approveIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-success-200 bg-success-50 text-success-600 shadow-theme-xs transition hover:border-success-300 hover:bg-success-100 dark:border-success-500/25 dark:bg-success-500/10 dark:text-success-500 dark:hover:bg-success-500/15';
        $rejectIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-error-200 bg-error-50 text-error-600 shadow-theme-xs transition hover:border-error-300 hover:bg-error-100 dark:border-error-500/25 dark:bg-error-500/10 dark:text-error-400 dark:hover:bg-error-500/15';
        $primaryButtonClass = 'inline-flex h-9 items-center justify-center rounded-lg bg-brand-500 px-3 text-theme-xs font-medium text-white shadow-theme-xs transition hover:bg-brand-600';
        $dangerButtonClass = 'inline-flex h-9 items-center justify-center rounded-lg bg-error-500 px-3 text-theme-xs font-medium text-white shadow-theme-xs transition hover:bg-error-600';
        $eyeIcon = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $messageIcon = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $checkIcon = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $xIcon = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        $periodLabel = function ($reading) {
            try {
                $from = \Carbon\Carbon::parse($reading->period_from ?? $reading->created_at ?? now());
                $to = \Carbon\Carbon::parse($reading->period_to ?? $reading->period_from ?? $reading->created_at ?? now());

                if ($from->isSameDay($to)) {
                    return $from->format('M d, Y');
                }

                return $from->format('M d') . ' - ' . $to->format('M d, Y');
            } catch (\Throwable) {
                return 'No period selected';
            }
        };

        $periodSort = function ($reading) {
            try {
                return \Carbon\Carbon::parse($reading->period_to ?? $reading->period_from ?? $reading->created_at ?? now())->format('Ymd');
            } catch (\Throwable) {
                return '00000000';
            }
        };

        $statusMeta = function ($status) {
            $status = strtolower((string) ($status ?: 'Pending'));

            if (str_contains($status, 'verified') || str_contains($status, 'approved')) {
                return [
                    'label' => 'Verified',
                    'class' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                ];
            }

            if (str_contains($status, 'reject')) {
                return [
                    'label' => 'Rejected',
                    'class' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-500',
                ];
            }

            return [
                'label' => 'Pending Verification',
                'class' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
            ];
        };

        $varianceMeta = function ($variance) {
            $variance = (float) $variance;

            if (abs($variance) < 0.01) {
                return [
                    'label' => 'Balanced',
                    'class' => 'text-success-600 dark:text-success-500',
                    'display' => '&#8369;0.00',
                ];
            }

            if ($variance < 0) {
                return [
                    'label' => 'Shortage',
                    'class' => 'text-error-600 dark:text-error-500',
                    'display' => '-&#8369;' . number_format(abs($variance), 2),
                ];
            }

            return [
                'label' => 'Overage',
                'class' => 'text-brand-600 dark:text-brand-400',
                'display' => '+&#8369;' . number_format($variance, 2),
            ];
        };

        $rowPayloads = collect($readings->items())->map(function ($reading) use ($periodLabel, $statusMeta) {
            return [
                'id' => (int) ($reading->id ?? 0),
                'staff' => ucwords(strtolower((string) ($reading->staff_name ?? 'Staff'))),
                'period' => $periodLabel($reading),
                'status' => (string) ($reading->status ?? 'Pending'),
                'statusLabel' => $statusMeta($reading->status ?? 'Pending')['label'],
                'admin_remarks' => (string) ($reading->admin_remarks ?? ''),
                'rejection_reason' => (string) ($reading->rejection_reason ?? ''),
            ];
        })->keyBy('id')->all();
    @endphp

    <x-common.page-breadcrumb pageTitle="Shift Reconciliation" />

    <div class="space-y-6">
        <section class="{{ $cardClass }} p-5 lg:p-6">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,560px)] xl:items-end">
                <div class="min-w-0">
                    <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Shift Reconciliation</h1>
                    <p class="mt-1 {{ $captionClass }}">Review remittances, pump readings, collections, discounts, and settlement variances.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
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
            </div>

            @if (! empty($dataWarning))
                <div class="mt-5 rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300">
                    {{ $dataWarning }}
                </div>
            @endif
        </section>

        <section class="{{ $cardClass }} overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Settlement Queue</h2>
                    <p class="mt-1 {{ $captionClass }}">Newest readings appear first.</p>
                </div>
            </div>

            <div class="max-w-full overflow-x-auto">
                <table class="min-w-[1040px] w-full table-fixed">
                    <colgroup>
                        <col class="w-[15%]">
                        <col class="w-[13%]">
                        <col class="w-[13%]">
                        <col class="w-[16%]">
                        <col class="w-[12%]">
                        <col class="w-[14%]">
                        <col class="w-[17%]">
                    </colgroup>
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                        <tr>
                            <th class="{{ $thClass }}">Period Covered</th>
                            <th class="{{ $thClass }}">Staff</th>
                            <th class="{{ $thNumberClass }}">Total Remitted</th>
                            <th class="{{ $thNumberClass }}">Expected Collection</th>
                            <th class="{{ $thNumberClass }}">Cash Variance</th>
                            <th class="{{ $thClass }}">Status</th>
                            <th class="{{ $thClass }}">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($readings as $reading)
                            @php
                                $status = $statusMeta($reading->status ?? 'Pending');
                                $variance = $varianceMeta($reading->cash_variance ?? 0);
                                $isPending = str_contains(strtolower((string) ($reading->status ?? 'Pending')), 'pending');
                            @endphp
                            <tr id="row-{{ $reading->id }}" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                <td class="{{ $tdPrimaryClass }}" data-order="{{ $periodSort($reading) }}">
                                    <span class="block truncate">{{ $periodLabel($reading) }}</span>
                                </td>
                                <td class="{{ $tdPrimaryClass }}">
                                    <span class="block truncate">{{ ucwords(strtolower($reading->staff_name ?? 'Staff')) }}</span>
                                </td>
                                <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) ($reading->total_remitted ?? 0), 2) }}</td>
                                <td class="{{ $tdNumberClass }}">
                                    <span class="block font-medium text-gray-800 dark:text-white/90">&#8369;{{ number_format((float) ($reading->expected_collection ?? 0), 2) }}</span>
                                    <span class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">Pump: &#8369;{{ number_format((float) ($reading->total_pump_sales ?? 0), 2) }}</span>
                                </td>
                                <td class="{{ $tdNumberClass }}">
                                    <span class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $variance['label'] }}</span>
                                    <span class="block font-semibold {{ $variance['class'] }}">{!! $variance['display'] !!}</span>
                                </td>
                                <td class="{{ $tdClass }}">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $status['class'] }}">{{ $status['label'] }}</span>
                                </td>
                                <td class="{{ $tdClass }}">
                                    <div class="flex flex-nowrap items-center gap-1.5">
                                        <button type="button" class="{{ $viewIconButtonClass }}" onclick="viewDetails({{ (int) $reading->id }}, this)" title="View details" aria-label="View details">
                                            {!! $eyeIcon !!}
                                            <span class="sr-only">View details</span>
                                        </button>
                                        <button type="button" class="{{ $remarksIconButtonClass }}" onclick="openRemarksModal({{ (int) $reading->id }})" title="Remarks" aria-label="Remarks">
                                            {!! $messageIcon !!}
                                            <span class="sr-only">Remarks</span>
                                        </button>
                                        @if ($isPending)
                                            <button type="button" class="{{ $approveIconButtonClass }}" onclick="openApproveModal({{ (int) $reading->id }})" title="Approve" aria-label="Approve">
                                                {!! $checkIcon !!}
                                                <span class="sr-only">Approve</span>
                                            </button>
                                            <button type="button" class="{{ $rejectIconButtonClass }}" onclick="openRejectModal({{ (int) $reading->id }})" title="Reject" aria-label="Reject">
                                                {!! $xIcon !!}
                                                <span class="sr-only">Reject</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <div class="mx-auto max-w-md">
                                        <p class="text-theme-sm font-medium text-gray-800 dark:text-white/90">No shift readings found.</p>
                                        <p class="mt-1 {{ $captionClass }}">Submitted staff readings will appear here for reconciliation.</p>
                                    </div>
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
    </div>

    <div id="reconciliationToast" class="fixed right-4 top-24 z-[100400] hidden max-w-sm rounded-xl border border-gray-200 bg-white px-4 py-3 text-theme-sm shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
        <p id="reconciliationToastText" class="font-medium text-gray-800 dark:text-white/90"></p>
    </div>

    <div id="lightboxModal" data-reconciliation-modal class="fixed inset-0 z-[100300] hidden items-center justify-center bg-gray-950/85 p-4">
        <button type="button" class="absolute right-4 top-4 rounded-lg bg-white/10 px-3 py-2 text-theme-sm font-medium text-white hover:bg-white/20" data-close-modal="#lightboxModal">Close</button>
        <img id="lightboxImage" src="" alt="Meter reading" class="max-h-[88vh] max-w-full rounded-xl object-contain shadow-theme-xl">
    </div>

    <div id="detailsModal" data-reconciliation-modal class="fixed inset-0 z-[100200] hidden items-stretch justify-stretch overflow-hidden bg-gray-900/60 p-2 backdrop-blur-sm sm:p-4">
        <div class="flex min-h-0 w-full">
            <div class="relative flex h-[calc(100vh-1rem)] w-full max-w-none flex-col overflow-hidden rounded-xl bg-white shadow-theme-xl dark:bg-gray-900 sm:h-[calc(100vh-2rem)]">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Settlement Details</h2>
                        <p id="detailsSubtitle" class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Loading...</p>
                    </div>
                    <button type="button" class="{{ $buttonClass }}" data-close-modal="#detailsModal">Close</button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                    <div id="detailsLoadingState" class="flex min-h-[240px] flex-col items-center justify-center text-center">
                        <div class="h-10 w-10 animate-spin rounded-full border-4 border-brand-100 border-t-brand-500"></div>
                        <p class="mt-3 text-theme-sm font-medium text-gray-700 dark:text-gray-300">Loading settlement details...</p>
                    </div>

                    <div id="detailsErrorState" class="hidden rounded-xl border border-error-200 bg-error-50 p-4 text-theme-sm text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300"></div>

                    <div id="detailsContent" class="hidden space-y-5">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Staff</p>
                                <p id="viewStaff" class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90"></p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Period</p>
                                <p id="viewDate" class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90"></p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Total Remitted</p>
                                <p id="viewTotalRemitted" class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90"></p>
                            </div>
                            <div id="viewVarianceBox" class="rounded-xl border p-4"></div>
                        </div>

                        <div id="viewAdminFeedback" class="hidden rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300"></div>

                        <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.18fr)_minmax(0,0.82fr)]">
                            <section class="min-w-0 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Pump Meter & Photos</h3>
                                        <p class="{{ $captionClass }}">Three dispenser blocks, two pumps each.</p>
                                    </div>
                                    <div class="text-right">
                                        <p id="viewTotalSales" class="text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90"></p>
                                        <p id="viewTotalLiters" class="text-theme-xs tabular-nums text-gray-500 dark:text-gray-400"></p>
                                    </div>
                                </div>
                                <div id="pumpCardsContainer" class="grid grid-cols-1 gap-3 lg:grid-cols-3"></div>
                            </section>

                            <aside class="min-w-0 space-y-4">
                                <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Financial Reconciliation</h3>
                                    <div id="financialCalcBody" class="mt-4 divide-y divide-gray-100 dark:divide-gray-800"></div>
                                </section>
                            </aside>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="remarksModal" data-reconciliation-modal class="fixed inset-0 z-[100200] hidden items-center justify-center overflow-y-auto bg-gray-900/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-xl rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
            <form id="remarksForm">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Remarks</h2>
                        <p id="remarksSettlementMeta" class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400"></p>
                    </div>
                    <button type="button" class="{{ $buttonClass }}" data-close-modal="#remarksModal">Close</button>
                </div>
                <div class="space-y-4 p-5">
                    <input type="hidden" id="remarksReadingId">
                    <div id="remarksRejectedReasonBox" class="hidden rounded-xl border border-error-200 bg-error-50 p-4 text-theme-sm text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300">
                        <p class="mb-1 text-theme-xs font-semibold uppercase">Rejection Reason</p>
                        <p id="remarksRejectedReasonText"></p>
                    </div>
                    <label class="block">
                        <span class="text-theme-sm font-medium text-gray-700 dark:text-gray-300">Admin Remarks / Comment</span>
                        <textarea id="adminRemarks" maxlength="2000" rows="6" class="mt-2 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" placeholder="Type a remark that staff can view."></textarea>
                    </label>
                    <p class="text-theme-xs text-gray-500 dark:text-gray-400">Leave blank and save to clear the existing remark.</p>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" class="{{ $buttonClass }} h-11 text-theme-sm" data-close-modal="#remarksModal">Cancel</button>
                    <button type="submit" class="{{ $primaryButtonClass }} h-11 text-theme-sm" id="btnSaveRemarks">Save Remarks</button>
                </div>
            </form>
        </div>
    </div>

    <div id="approveModal" data-reconciliation-modal class="fixed inset-0 z-[100200] hidden items-center justify-center overflow-y-auto bg-gray-900/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
            <form id="approveForm">
                <input type="hidden" id="approveReadingId">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Approve Settlement?</h2>
                    <p id="approveSettlementMeta" class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400"></p>
                </div>
                <div class="p-5">
                    <p class="text-theme-sm text-gray-600 dark:text-gray-400">This will finalize the variance, apply pending collections to customer ledgers, deduct dispensed liters from tanks, and mark the settlement as verified.</p>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" class="{{ $buttonClass }} h-11 text-theme-sm" data-close-modal="#approveModal">Cancel</button>
                    <button type="submit" class="{{ $primaryButtonClass }} h-11 text-theme-sm" id="btnApprove">Approve & Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div id="rejectModal" data-reconciliation-modal class="fixed inset-0 z-[100200] hidden items-center justify-center overflow-y-auto bg-gray-900/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
            <form id="rejectForm">
                <input type="hidden" id="rejectReadingId">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Reject Submission?</h2>
                    <p id="rejectSettlementMeta" class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400"></p>
                </div>
                <div class="p-5">
                    <label class="block">
                        <span class="text-theme-sm font-medium text-gray-700 dark:text-gray-300">Reason for rejection</span>
                        <textarea id="rejectionReason" maxlength="2000" rows="5" class="mt-2 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" placeholder="Type the reason so staff can correct it."></textarea>
                    </label>
                    <p id="rejectReasonError" class="mt-2 hidden text-theme-xs font-medium text-error-600 dark:text-error-500">Rejection reason is required.</p>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" class="{{ $buttonClass }} h-11 text-theme-sm" data-close-modal="#rejectModal">Cancel</button>
                    <button type="submit" class="{{ $dangerButtonClass }} h-11 text-theme-sm" id="btnReject">Reject</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const reconciliationRows = @json($rowPayloads);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
        const detailsUrl = @json(url('/admin/reconciliation/details'));
        const approveUrl = @json(route('admin.reconcile.approve'));
        const rejectUrl = @json(route('admin.reconcile.reject'));
        const remarksUrl = @json(route('admin.reconcile.remarks'));
        const moneyFormatter = new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        function esc(value) {
            const element = document.createElement('textarea');
            element.textContent = value ?? '';
            return element.innerHTML;
        }

        function fnum(value) {
            return moneyFormatter.format(Number(value) || 0);
        }

        function peso(value) {
            return '&#8369;' + fnum(value);
        }

        function modalElement(selector) {
            return typeof selector === 'string' ? document.querySelector(selector) : selector;
        }

        function openModal(selector) {
            const modal = modalElement(selector);
            if (!modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function hasOpenModal() {
            return Boolean(document.querySelector('[data-reconciliation-modal].flex'));
        }

        function closeModal(selector) {
            const modal = modalElement(selector);
            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');

            if (!hasOpenModal()) {
                document.body.style.overflow = '';
            }
        }

        function csrfHeaders() {
            return {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            };
        }

        async function handleJsonResponse(response, fallbackMessage) {
            const data = await response.json().catch(() => ({}));

            if (!response.ok || data.success === false) {
                throw new Error(data.message || fallbackMessage);
            }

            return data;
        }

        function settlementMeta(id) {
            const row = reconciliationRows[id] || {};
            return row.staff && row.period ? `${row.staff} - ${row.period}` : `Settlement #${id}`;
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('reconciliationToast');
            const text = document.getElementById('reconciliationToastText');

            if (!toast || !text) {
                return;
            }

            text.textContent = message;
            toast.classList.remove('hidden', 'border-error-200', 'border-success-200', 'text-error-700', 'text-success-700');
            toast.classList.add(type === 'error' ? 'border-error-200' : 'border-success-200');

            window.clearTimeout(showToast.timer);
            showToast.timer = window.setTimeout(() => toast.classList.add('hidden'), 3200);
        }

        function setButtonLoading(button, isLoading, loadingText = 'Saving...') {
            if (!button) {
                return;
            }

            if (isLoading) {
                button.dataset.originalHtml = button.innerHTML;
                button.innerHTML = button.classList.contains('w-9')
                    ? '<span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>'
                    : esc(loadingText);
                button.disabled = true;
                button.classList.add('opacity-70', 'cursor-wait');
            } else {
                button.innerHTML = button.dataset.originalHtml || button.innerHTML;
                delete button.dataset.originalHtml;
                button.disabled = false;
                button.classList.remove('opacity-70', 'cursor-wait');
            }
        }

        function varianceState(value) {
            const variance = Number(value) || 0;

            if (Math.abs(variance) < 0.01) {
                return {
                    label: 'Cash Balanced',
                    value: peso(0),
                    className: 'border-success-100 bg-success-50 text-success-700 dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-500'
                };
            }

            if (variance < 0) {
                return {
                    label: 'Cash Shortage',
                    value: '-' + peso(Math.abs(variance)),
                    className: 'border-error-100 bg-error-50 text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-500'
                };
            }

            return {
                label: 'Cash Overage',
                value: '+' + peso(variance),
                className: 'border-brand-100 bg-brand-50 text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400'
            };
        }

        function statusLabel(status) {
            const value = String(status || 'Pending').toLowerCase();

            if (value.includes('verified') || value.includes('approved')) {
                return 'Verified';
            }

            if (value.includes('reject')) {
                return 'Rejected';
            }

            return 'Pending Verification';
        }

        function dateLabel(reading) {
            return reconciliationRows[reading.id]?.period || 'No period selected';
        }

        function showDetailsLoading(message = 'Loading settlement details...') {
            document.getElementById('detailsSubtitle').textContent = message;
            document.getElementById('detailsLoadingState').classList.remove('hidden');
            document.getElementById('detailsErrorState').classList.add('hidden');
            document.getElementById('detailsContent').classList.add('hidden');
        }

        function showDetailsError(message) {
            const errorState = document.getElementById('detailsErrorState');
            errorState.textContent = message;
            errorState.classList.remove('hidden');
            document.getElementById('detailsLoadingState').classList.add('hidden');
            document.getElementById('detailsContent').classList.add('hidden');
        }

        function showDetailsContent() {
            document.getElementById('detailsLoadingState').classList.add('hidden');
            document.getElementById('detailsErrorState').classList.add('hidden');
            document.getElementById('detailsContent').classList.remove('hidden');
        }

        function imageFor(images, dispenser, type) {
            return images.find((image) => Number(image.dispenser) === dispenser && String(image.image_type || '').toLowerCase() === type);
        }

        function imageSlot(images, dispenser, type, label) {
            const image = imageFor(images, dispenser, type);

            if (!image || !image.image_url) {
                return `
                    <div class="flex min-h-[92px] items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 text-center text-theme-xs text-gray-400 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-500">
                        No ${esc(label)} photo
                    </div>
                `;
            }

            return `
                <button type="button" class="group relative block min-h-[92px] overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800" data-lightbox-url="${esc(image.image_url)}" onclick="openLightbox(this.dataset.lightboxUrl)">
                    <img src="${esc(image.image_url)}" alt="${esc(label)} meter photo" class="h-[92px] w-full object-cover transition group-hover:scale-[1.02]">
                    <span class="absolute bottom-1 left-1 rounded bg-gray-950/70 px-2 py-0.5 text-[11px] font-medium text-white">${esc(label)}</span>
                </button>
            `;
        }

        function pumpLine(reading, pump) {
            const salesStart = Number(reading[`pump${pump}_sales_start`]) || 0;
            const salesEnd = Number(reading[`pump${pump}_sales_end`]) || 0;
            const salesDiff = Number(reading[`pump${pump}_dispensed_sales`]) || 0;
            const litersStart = Number(reading[`pump${pump}_liters_start`]) || 0;
            const litersEnd = Number(reading[`pump${pump}_liters_end`]) || 0;
            const litersDiff = Number(reading[`pump${pump}_dispensed_liters`]) || 0;

            return `
                <div class="rounded-lg border border-gray-100 p-3 dark:border-gray-800">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Pump ${pump}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">${pumpFuel(pump)}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-theme-xs">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Open Sales</p>
                            <p class="mt-0.5 font-medium tabular-nums text-gray-800 dark:text-white/90">${peso(salesStart)}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Close Sales</p>
                            <p class="mt-0.5 font-medium tabular-nums text-gray-800 dark:text-white/90">${peso(salesEnd)}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Sales</p>
                            <p class="mt-0.5 font-semibold tabular-nums text-brand-600 dark:text-brand-400">${peso(salesDiff)}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Open L</p>
                            <p class="mt-0.5 font-medium tabular-nums text-gray-800 dark:text-white/90">${fnum(litersStart)} L</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Close L</p>
                            <p class="mt-0.5 font-medium tabular-nums text-gray-800 dark:text-white/90">${fnum(litersEnd)} L</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Liters</p>
                            <p class="mt-0.5 font-semibold tabular-nums text-brand-600 dark:text-brand-400">${fnum(litersDiff)} L</p>
                        </div>
                    </div>
                </div>
            `;
        }

        function pumpFuel(pump) {
            const fuelByPump = {
                1: 'Premium',
                2: 'Unleaded',
                3: 'Premium',
                4: 'Unleaded',
                5: 'Diesel',
                6: 'Diesel'
            };

            return fuelByPump[pump] || 'Fuel';
        }

        function renderPumpCards(reading, images) {
            let html = '';

            for (let dispenser = 1; dispenser <= 3; dispenser++) {
                const pumpA = (dispenser * 2) - 1;
                const pumpB = dispenser * 2;

                html += `
                    <article class="min-w-0 rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Dispenser ${dispenser}</h4>
                            <span class="text-theme-xs text-gray-500 dark:text-gray-400">Pumps ${pumpA}-${pumpB}</span>
                        </div>
                        <div class="mb-3 grid grid-cols-2 gap-2">
                            ${imageSlot(images, dispenser, 'sales', 'Sales')}
                            ${imageSlot(images, dispenser, 'liters', 'Liters')}
                        </div>
                        <div class="space-y-3">
                            ${pumpLine(reading, pumpA)}
                            ${pumpLine(reading, pumpB)}
                        </div>
                    </article>
                `;
            }

            return html;
        }

        function saleReference(item) {
            return esc(item.reference_no || item.or_number || `TXN-${item.id || ''}`);
        }

        function saleAccount(item) {
            return esc(item.customer_display || item.company_name || item.customer_name || 'Walk-In Customer');
        }

        function tableForItems(items, columns) {
            if (!items.length) {
                return '<div class="rounded-lg bg-gray-50 px-3 py-4 text-center text-theme-xs text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">No records found.</div>';
            }

            const header = columns.map((column) => `<th class="px-3 py-2 text-left text-[11px] font-medium uppercase text-gray-500 dark:text-gray-400 ${column.align || ''}">${esc(column.label)}</th>`).join('');
            const rows = items.map((item) => `
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    ${columns.map((column) => `<td class="px-3 py-2 align-top text-theme-xs text-gray-700 dark:text-gray-300 ${column.align || ''}">${column.render(item)}</td>`).join('')}
                </tr>
            `).join('');

            return `
                <div class="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                    <table class="min-w-[520px] w-full">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr>${header}</tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        }

        function financialRow(label, amount, options = {}) {
            const amountClass = options.amountClass || 'text-gray-800 dark:text-white/90';
            const prefix = options.prefix || '';
            const labelClass = options.strong ? 'text-theme-sm font-semibold text-gray-800 dark:text-white/90' : 'text-theme-sm text-gray-500 dark:text-gray-400';
            const amountSize = options.strong ? 'text-lg font-bold' : 'text-theme-sm font-semibold';

            if (!options.detailsHtml) {
                return `
                    <div class="flex items-center justify-between gap-4 py-3">
                        <span class="${labelClass}">${esc(label)}</span>
                        <span class="${amountSize} tabular-nums ${amountClass}">${prefix}${peso(amount)}</span>
                    </div>
                `;
            }

            return `
                <details class="group">
                    <summary class="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-3 py-3 [&::-webkit-details-marker]:hidden">
                        <span class="${labelClass}">${esc(label)}</span>
                        <span class="${amountSize} tabular-nums ${amountClass}">${prefix}${peso(amount)}</span>
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition group-open:rotate-180 dark:border-gray-800 dark:text-gray-400">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="pb-3">
                        ${options.detailsHtml}
                    </div>
                </details>
            `;
        }

        function renderFinancialRows(reading, breakdowns) {
            const moneyColumn = (field, prefix = '') => ({
                label: 'Amount',
                align: 'text-right font-semibold tabular-nums',
                render: (item) => `${prefix}${peso(item[field])}`
            });
            const saleColumns = [
                { label: 'Time', render: (item) => esc(item.recorded_at) },
                { label: 'Ref', render: saleReference },
                { label: 'Account', render: saleAccount },
                { label: 'Pump', render: (item) => esc(item.pump_label || 'N/A') },
                { label: 'Product', render: (item) => esc(item.product_label || item.fuel_type || 'N/A') },
                { label: 'Liters', align: 'text-right tabular-nums', render: (item) => `${fnum(item.liters)} L` },
            ];
            const paymentColumns = [
                { label: 'Time', render: (item) => esc(item.recorded_at) },
                { label: 'Ref', render: saleReference },
                { label: 'Account', render: saleAccount },
                { label: 'Method', render: (item) => esc(item.product_label || item.fuel_type || 'Payment') },
            ];
            const cashColumns = [
                { label: 'Type', render: (item) => esc(String(item.type || '-').toUpperCase()) },
                { label: 'Denomination', render: (item) => peso(item.value) },
                { label: 'Pieces', align: 'text-right tabular-nums', render: (item) => Number(item.pieces || 0).toLocaleString('en-PH') },
                { label: 'Amount', align: 'text-right font-semibold tabular-nums', render: (item) => peso(item.amount) },
            ];
            const referenceColumns = [
                { label: 'Reference', render: (item) => esc(item.reference_no || item.cheque_no || '-') },
                moneyColumn('amount')
            ];

            const cashDenominations = Array.isArray(reading.cash_denominations)
                ? reading.cash_denominations.filter((item) => Number(item.pieces || 0) > 0 || Number(item.amount || 0) > 0)
                : [];
            const gcashEntries = Array.isArray(reading.gcash_entries) ? reading.gcash_entries : [];
            const chequeEntries = Array.isArray(reading.cheque_entries) ? reading.cheque_entries : [];

            return [
                financialRow('Total Gross Pump Sales', reading.total_pump_sales),
                financialRow('Excess Markup / Surcharge', reading.surcharge, {
                    prefix: '+',
                    amountClass: 'text-brand-600 dark:text-brand-400',
                    detailsHtml: tableForItems(breakdowns.excess_markups || [], [...saleColumns, moneyColumn('surcharge', '+')])
                }),
                financialRow('Account Payments Collected', reading.payments_collected, {
                    prefix: '+',
                    amountClass: 'text-success-600 dark:text-success-500',
                    detailsHtml: tableForItems(breakdowns.payments_collected || [], [...paymentColumns, moneyColumn('amount', '+')])
                }),
                financialRow('Gross Credit Sales', reading.gross_credit_sales, {
                    prefix: '-',
                    amountClass: 'text-error-600 dark:text-error-500',
                    detailsHtml: tableForItems(breakdowns.credit_sales || [], [...saleColumns, moneyColumn('amount')])
                }),
                financialRow('System Discounts', reading.auto_discount, {
                    prefix: '-',
                    amountClass: 'text-error-600 dark:text-error-500',
                    detailsHtml: tableForItems(breakdowns.system_discounts || [], [...saleColumns, moneyColumn('discount')])
                }),
                financialRow('Manual Discounts', reading.manual_discount, {
                    prefix: '-',
                    amountClass: 'text-error-600 dark:text-error-500',
                    detailsHtml: tableForItems(breakdowns.manual_discounts || [], [...saleColumns, moneyColumn('manual_discount')])
                }),
                `<div class="flex items-center justify-between gap-4 border-y border-gray-100 bg-gray-50 px-3 py-3 dark:border-gray-800 dark:bg-white/[0.02]">
                    <span class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Expected Collection</span>
                    <span class="text-theme-sm font-bold tabular-nums text-brand-600 dark:text-brand-400">${peso(reading.expected_collection)}</span>
                </div>`,
                financialRow('Physical Cash Remitted', reading.cash_on_hand, {
                    detailsHtml: tableForItems(cashDenominations, cashColumns)
                }),
                financialRow('GCash Received', reading.gcash_amount, {
                    amountClass: 'text-success-600 dark:text-success-500',
                    detailsHtml: tableForItems(gcashEntries, referenceColumns)
                }),
                financialRow('Cheque Received', reading.cheque_amount, {
                    amountClass: 'text-brand-600 dark:text-brand-400',
                    detailsHtml: tableForItems(chequeEntries, referenceColumns)
                }),
                financialRow('Total Actual Remittance', reading.total_remitted, {
                    strong: true
                })
            ].join('');
        }

        function fillDetails(data) {
            const reading = data.reading || {};
            const images = Array.isArray(data.meter_images) ? data.meter_images : [];
            const breakdowns = data.transaction_breakdowns || {};
            const row = reconciliationRows[reading.id] || {};
            const variance = varianceState(reading.cash_variance);
            const feedback = [reading.rejection_reason, reading.admin_remarks].filter(Boolean);

            document.getElementById('detailsSubtitle').textContent = `${statusLabel(reading.status)} - ${dateLabel(reading)}`;
            document.getElementById('viewStaff').textContent = row.staff || reading.staff_name || 'Staff';
            document.getElementById('viewDate').textContent = dateLabel(reading);
            document.getElementById('viewTotalRemitted').innerHTML = peso(reading.total_remitted);
            document.getElementById('viewTotalSales').innerHTML = `Total Sales: ${peso(reading.total_pump_sales)}`;
            document.getElementById('viewTotalLiters').textContent = `${fnum(reading.total_pump_liters)} L total dispensed`;

            const varianceBox = document.getElementById('viewVarianceBox');
            varianceBox.className = `rounded-xl border p-4 ${variance.className}`;
            varianceBox.innerHTML = `<p class="text-theme-xs font-medium uppercase">${variance.label}</p><p class="mt-1 text-lg font-bold tabular-nums">${variance.value}</p>`;

            const feedbackBox = document.getElementById('viewAdminFeedback');
            if (feedback.length) {
                feedbackBox.innerHTML = feedback.map((text) => `<p>${esc(text)}</p>`).join('');
                feedbackBox.classList.remove('hidden');
            } else {
                feedbackBox.classList.add('hidden');
            }

            document.getElementById('pumpCardsContainer').innerHTML = renderPumpCards(reading, images);
            document.getElementById('financialCalcBody').innerHTML = renderFinancialRows(reading, breakdowns);
            showDetailsContent();
        }

        function viewDetails(id, button) {
            openModal('#detailsModal');
            showDetailsLoading();
            setButtonLoading(button, true, 'Loading');

            fetch(`${detailsUrl}/${id}`, {
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then((response) => handleJsonResponse(response, 'Could not load settlement details.'))
                .then(fillDetails)
                .catch((error) => showDetailsError(error.message))
                .finally(() => setButtonLoading(button, false));
        }

        function openLightbox(url) {
            const image = document.getElementById('lightboxImage');
            image.src = url;
            openModal('#lightboxModal');
        }

        function openRemarksModal(id) {
            const row = reconciliationRows[id] || {};
            document.getElementById('remarksReadingId').value = id;
            document.getElementById('remarksSettlementMeta').textContent = settlementMeta(id);
            document.getElementById('adminRemarks').value = row.admin_remarks || '';

            const reasonBox = document.getElementById('remarksRejectedReasonBox');
            const reasonText = document.getElementById('remarksRejectedReasonText');

            if (row.rejection_reason) {
                reasonText.textContent = row.rejection_reason;
                reasonBox.classList.remove('hidden');
            } else {
                reasonBox.classList.add('hidden');
            }

            openModal('#remarksModal');
            setTimeout(() => document.getElementById('adminRemarks').focus(), 50);
        }

        function openApproveModal(id) {
            document.getElementById('approveReadingId').value = id;
            document.getElementById('approveSettlementMeta').textContent = settlementMeta(id);
            openModal('#approveModal');
        }

        function openRejectModal(id) {
            document.getElementById('rejectReadingId').value = id;
            document.getElementById('rejectSettlementMeta').textContent = settlementMeta(id);
            document.getElementById('rejectionReason').value = '';
            document.getElementById('rejectReasonError').classList.add('hidden');
            openModal('#rejectModal');
            setTimeout(() => document.getElementById('rejectionReason').focus(), 50);
        }

        function submitAction(url, payload, button, loadingText) {
            setButtonLoading(button, true, loadingText);

            return fetch(url, {
                method: 'POST',
                headers: csrfHeaders(),
                body: JSON.stringify(payload)
            })
                .then((response) => handleJsonResponse(response, 'Action failed.'))
                .finally(() => setButtonLoading(button, false));
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-close-modal]').forEach((button) => {
                button.addEventListener('click', () => closeModal(button.dataset.closeModal));
            });

            document.getElementById('remarksForm')?.addEventListener('submit', function(event) {
                event.preventDefault();
                const id = Number(document.getElementById('remarksReadingId').value);
                const adminRemarks = document.getElementById('adminRemarks').value;

                submitAction(remarksUrl, {
                    id,
                    admin_remarks: adminRemarks
                }, document.getElementById('btnSaveRemarks'), 'Saving')
                    .then((data) => {
                        reconciliationRows[id] = reconciliationRows[id] || {};
                        reconciliationRows[id].admin_remarks = adminRemarks.trim();
                        closeModal('#remarksModal');
                        showToast(data.message || 'Remarks saved.');
                    })
                    .catch((error) => showToast(error.message, 'error'));
            });

            document.getElementById('approveForm')?.addEventListener('submit', function(event) {
                event.preventDefault();
                const id = Number(document.getElementById('approveReadingId').value);

                submitAction(approveUrl, { id }, document.getElementById('btnApprove'), 'Approving')
                    .then((data) => {
                        closeModal('#approveModal');
                        showToast(data.message || 'Settlement approved.');
                        setTimeout(() => window.location.reload(), 850);
                    })
                    .catch((error) => showToast(error.message, 'error'));
            });

            document.getElementById('rejectForm')?.addEventListener('submit', function(event) {
                event.preventDefault();
                const id = Number(document.getElementById('rejectReadingId').value);
                const reason = document.getElementById('rejectionReason').value.trim();

                if (!reason) {
                    document.getElementById('rejectReasonError').classList.remove('hidden');
                    return;
                }

                submitAction(rejectUrl, {
                    id,
                    rejection_reason: reason
                }, document.getElementById('btnReject'), 'Rejecting')
                    .then((data) => {
                        closeModal('#rejectModal');
                        showToast(data.message || 'Settlement rejected.');
                        setTimeout(() => window.location.reload(), 850);
                    })
                    .catch((error) => showToast(error.message, 'error'));
            });

            document.addEventListener('keydown', function(event) {
                if (event.key !== 'Escape') {
                    return;
                }

                ['#lightboxModal', '#detailsModal', '#remarksModal', '#approveModal', '#rejectModal'].forEach(closeModal);
            });
        });
    </script>
@endpush
