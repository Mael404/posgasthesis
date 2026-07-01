@extends('layouts.app')

@section('content')
    @php
        $cardDefaultPeriods = $cardDefaultPeriods ?? [];
        $customMetricStats = $customMetricStats ?? [];
        $customPeriodLabels = $customPeriodLabels ?? [];
        $metricDateFilters = $metricDateFilters ?? [];
        $dashboardPeriodLabels = $periodLabels ?? [
            'today' => 'Today',
            'weekly' => 'This Week',
            'monthly' => 'This Month',
            'custom' => 'Custom',
        ];
        $metricStats = function ($target) use ($stats, $customMetricStats, $cardDefaultPeriods) {
            $period = $cardDefaultPeriods[$target] ?? 'today';

            if ($period === 'custom') {
                return $customMetricStats[$target] ?? ($stats['today'] ?? []);
            }

            return $stats[$period] ?? ($stats['today'] ?? []);
        };
        $metricPeriodLabel = function ($target) use ($dashboardPeriodLabels, $customPeriodLabels, $cardDefaultPeriods) {
            $period = $cardDefaultPeriods[$target] ?? 'today';

            if ($period === 'custom') {
                return $customPeriodLabels[$target] ?? 'Custom';
            }

            return $dashboardPeriodLabels[$period] ?? 'Today';
        };
        $metricFilter = fn ($target) => $metricDateFilters[$target] ?? ['prefix' => $target, 'from' => null, 'to' => null];
        $netSalesStats = $metricStats('net-sales');
        $expectedCreditStats = $metricStats('expected-credit');
        $creditPaymentsStats = $metricStats('credit-payments');
        $advanceReceivablesStats = $metricStats('advance-receivables');
        $totalExcessStats = $metricStats('total-excess');
        $litersMovementStats = $metricStats('liters-movement');
        $cardClass = 'min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $cardHeaderClass = 'mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between';
        $cardTitleClass = 'text-lg font-semibold text-gray-800 dark:text-white/90';
        $segmentGroupClass = 'segment-group inline-flex w-full min-w-0 flex-wrap items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900 sm:w-fit';
        $segmentButtonClass = 'segment-button min-w-0 rounded-md px-3 py-2 text-theme-xs font-medium transition hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:text-white';
        $dateFormClass = 'mt-4 grid min-w-0 grid-cols-1 items-end gap-3 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.03] sm:grid-cols-2 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]';
        $dateLabelClass = 'flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400';
        $dateInputClass = 'h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pl-4 pr-11 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $dateActionClass = 'flex min-w-0 flex-col gap-2 sm:col-span-2 sm:flex-row sm:justify-end 2xl:col-span-1';
        $primaryButtonClass = 'inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600';
        $secondaryButtonClass = 'inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $dateIcon = '<span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="size-6"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z" fill="currentColor"></path></svg></span>';
        $metricValueClass = 'mt-5 break-words text-title-sm font-bold text-gray-800 dark:text-white/90';
        $captionClass = 'mt-1 text-theme-sm text-gray-500 dark:text-gray-400';
        $metaClass = 'mt-4 border-t border-gray-100 pt-4 text-theme-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400';
        $tableClass = 'w-full table-auto';
        $thClass = 'px-4 py-3 text-left sm:px-5';
        $thTextClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-4 py-3 text-right sm:px-5';
    @endphp

    <x-common.page-breadcrumb pageTitle="Admin Dashboard" />

    <div class="space-y-6">
        @if (session('error'))
            <x-ui.alert
                variant="error"
                title="Database Backup Failed"
                :message="session('error')" />
        @endif

        <div class="{{ $cardClass }}">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Admin Dashboard</h1>
                    <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400" id="last-updated">
                        Live as of {{ \Carbon\Carbon::parse($lastUpdatedAt)->format('M d, Y h:i A') }}
                    </p>
                </div>
                <a href="{{ route('admin.backup.db') }}" class="{{ $secondaryButtonClass }}">Backup Database</a>
            </div>
        </div>

        <div class="grid min-w-0 grid-cols-12 gap-4 md:gap-6">
            <article class="{{ $cardClass }} col-span-12">
                <div class="{{ $cardHeaderClass }}">
                    <div>
                        <h2 class="{{ $cardTitleClass }}">Net Sales</h2>
                    </div>
                    <div class="flex w-full min-w-0 flex-col gap-3 sm:w-auto sm:items-end">
                        <div class="{{ $segmentGroupClass }}" aria-label="Net sales source">
                            <button type="button" class="{{ $segmentButtonClass }}" data-net-source="sales_table">Sales Table</button>
                            <button type="button" class="{{ $segmentButtonClass }}" data-net-source="shift_reading">Shift Readings</button>
                        </div>
                        <div class="{{ $segmentGroupClass }}" aria-label="Net sales credit filter">
                            <button type="button" class="{{ $segmentButtonClass }}" data-net-credit-mode="with_credit">Include Credits</button>
                            <button type="button" class="{{ $segmentButtonClass }}" data-net-credit-mode="without_credit">Walk-In Only</button>
                        </div>
                        <div class="{{ $segmentGroupClass }}" aria-label="Net sales period">
                            <button type="button" class="{{ $segmentButtonClass }}" data-period-target="net-sales" data-period="today">Today</button>
                            <button type="button" class="{{ $segmentButtonClass }}" data-period-target="net-sales" data-period="weekly">Week</button>
                            <button type="button" class="{{ $segmentButtonClass }}" data-period-target="net-sales" data-period="monthly">Month</button>
                            <button type="button" class="{{ $segmentButtonClass }}" data-period-target="net-sales" data-period="custom">Custom</button>
                        </div>
                    </div>
                </div>
                        @php($netSalesFilter = $metricFilter('net-sales'))
                        <form method="GET" action="{{ url()->current() }}" class="{{ $dateFormClass }}" data-date-filter-form>
                            <label class="{{ $dateLabelClass }}">
                                <span>From</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $netSalesFilter['prefix'] }}_from" value="{{ $netSalesFilter['from'] ?? request($netSalesFilter['prefix'] . '_from') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <label class="{{ $dateLabelClass }}">
                                <span>To</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $netSalesFilter['prefix'] }}_to" value="{{ $netSalesFilter['to'] ?? request($netSalesFilter['prefix'] . '_to') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <div class="{{ $dateActionClass }}">
                                <button type="submit" class="{{ $primaryButtonClass }}">Apply</button>
                                <button type="button" class="{{ $secondaryButtonClass }}" data-date-reset="{{ $netSalesFilter['prefix'] }}">Clear</button>
                            </div>
                        </form>
                        <div class="{{ $metricValueClass }}" id="netSalesValue">
                            &#8369;{{ number_format($netSalesStats['sales_table_net_sales_with_credit'] ?? 0, 2) }}
                        </div>
                        <p class="{{ $captionClass }}" id="netSalesCaption">Sales table: sales less discounts, including credits</p>
                        <div class="{{ $metaClass }}" id="netSalesMeta">
                            Period: {{ $metricPeriodLabel('net-sales') }}
                        </div>
                    </article>

                    <article class="{{ $cardClass }} col-span-12">
                        <div class="{{ $cardHeaderClass }}">
                            <h2 class="{{ $cardTitleClass }}">Net Sales Per Tank</h2>
                            <div class="flex w-full min-w-0 flex-col gap-3 sm:w-auto sm:items-end">
                                <div class="{{ $segmentGroupClass }}" aria-label="Net sales per tank period">
                                    <button type="button" class="{{ $segmentButtonClass }}" data-period-target="net-sales-tank" data-period="today">Today</button>
                                    <button type="button" class="{{ $segmentButtonClass }}" data-period-target="net-sales-tank" data-period="weekly">Week</button>
                                    <button type="button" class="{{ $segmentButtonClass }}" data-period-target="net-sales-tank" data-period="monthly">Month</button>
                                    <button type="button" class="{{ $segmentButtonClass }}" data-period-target="net-sales-tank" data-period="custom">Custom</button>
                                </div>
                                <div class="{{ $metaClass }} mt-0 border-t-0 pt-0 text-left sm:text-right" id="netSalesTankMeta">
                                    Sales table | Include credits | Period: {{ $metricPeriodLabel('net-sales-tank') }}
                                </div>
                            </div>
                        </div>
                        @php($netSalesTankFilter = $metricFilter('net-sales-tank'))
                        <form method="GET" action="{{ url()->current() }}" class="{{ $dateFormClass }}" data-date-filter-form>
                            <label class="{{ $dateLabelClass }}">
                                <span>From</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $netSalesTankFilter['prefix'] }}_from" value="{{ $netSalesTankFilter['from'] ?? request($netSalesTankFilter['prefix'] . '_from') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <label class="{{ $dateLabelClass }}">
                                <span>To</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $netSalesTankFilter['prefix'] }}_to" value="{{ $netSalesTankFilter['to'] ?? request($netSalesTankFilter['prefix'] . '_to') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <div class="{{ $dateActionClass }}">
                                <button type="submit" class="{{ $primaryButtonClass }}">Apply</button>
                                <button type="button" class="{{ $secondaryButtonClass }}" data-date-reset="{{ $netSalesTankFilter['prefix'] }}">Clear</button>
                            </div>
                        </form>
                        <div class="min-w-0 max-w-full overflow-x-auto custom-scrollbar">
                            <table class="{{ $tableClass }}">
                                <thead>
                                    <tr class="border-y border-gray-100 dark:border-gray-800">
                                        <th class="{{ $thClass }}"><p class="{{ $thTextClass }}">Tank</p></th>
                                        <th class="{{ $thClass }}"><p class="{{ $thTextClass }}">Fuel</p></th>
                                        <th class="{{ $thClass }}"><p class="{{ $thTextClass }}">Pumps</p></th>
                                        <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Liters</p></th>
                                        <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Net Sales</p></th>
                                    </tr>
                                </thead>
                                <tbody id="netSalesTankRows"></tbody>
                            </table>
                        </div>
                    </article>

                    <article class="{{ $cardClass }} col-span-12 xl:col-span-6">
                        <div class="{{ $cardHeaderClass }}">
                            <h2 class="{{ $cardTitleClass }}">Expected Credit Receivables</h2>
                            <div class="{{ $segmentGroupClass }}" aria-label="Expected credit sales period">
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="expected-credit" data-period="today">Today</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="expected-credit" data-period="weekly">Week</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="expected-credit" data-period="monthly">Month</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="expected-credit" data-period="custom">Custom</button>
                            </div>
                        </div>
                        @php($expectedCreditFilter = $metricFilter('expected-credit'))
                        <form method="GET" action="{{ url()->current() }}" class="{{ $dateFormClass }}" data-date-filter-form>
                            <label class="{{ $dateLabelClass }}">
                                <span>From</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $expectedCreditFilter['prefix'] }}_from" value="{{ $expectedCreditFilter['from'] ?? request($expectedCreditFilter['prefix'] . '_from') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <label class="{{ $dateLabelClass }}">
                                <span>To</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $expectedCreditFilter['prefix'] }}_to" value="{{ $expectedCreditFilter['to'] ?? request($expectedCreditFilter['prefix'] . '_to') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <div class="{{ $dateActionClass }}">
                                <button type="submit" class="{{ $primaryButtonClass }}">Apply</button>
                                <button type="button" class="{{ $secondaryButtonClass }}" data-date-reset="{{ $expectedCreditFilter['prefix'] }}">Clear</button>
                            </div>
                        </form>
                        <div class="{{ $metricValueClass }}" id="expectedCreditValue">
                            &#8369;{{ number_format($expectedCreditStats['expected_credit_receivables'] ?? 0, 2) }}
                        </div>
                        <p class="{{ $captionClass }}">Total outstanding credit balance</p>
                        <div class="{{ $metaClass }}" id="expectedCreditMeta">
                            Credit sales added {{ $metricPeriodLabel('expected-credit') }}:
                            &#8369;{{ number_format($expectedCreditStats['expected_credit_sales'] ?? 0, 2) }}
                        </div>
                    </article>

                    <article class="{{ $cardClass }} col-span-12 xl:col-span-6">
                        <div class="{{ $cardHeaderClass }}">
                            <h2 class="{{ $cardTitleClass }}">Credit Payments Received</h2>
                            <div class="{{ $segmentGroupClass }}" aria-label="Credit payments period">
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="credit-payments" data-period="today">Today</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="credit-payments" data-period="weekly">Week</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="credit-payments" data-period="monthly">Month</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="credit-payments" data-period="custom">Custom</button>
                            </div>
                        </div>
                        @php($creditPaymentsFilter = $metricFilter('credit-payments'))
                        <form method="GET" action="{{ url()->current() }}" class="{{ $dateFormClass }}" data-date-filter-form>
                            <label class="{{ $dateLabelClass }}">
                                <span>From</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $creditPaymentsFilter['prefix'] }}_from" value="{{ $creditPaymentsFilter['from'] ?? request($creditPaymentsFilter['prefix'] . '_from') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <label class="{{ $dateLabelClass }}">
                                <span>To</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $creditPaymentsFilter['prefix'] }}_to" value="{{ $creditPaymentsFilter['to'] ?? request($creditPaymentsFilter['prefix'] . '_to') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <div class="{{ $dateActionClass }} 2xl:col-span-1">
                                <button type="submit" class="{{ $primaryButtonClass }}">Apply</button>
                                <button type="button" class="{{ $secondaryButtonClass }}" data-date-reset="{{ $creditPaymentsFilter['prefix'] }}">Clear</button>
                            </div>
                        </form>
                        <div class="{{ $metricValueClass }}" id="creditPaymentsValue">
                            &#8369;{{ number_format($creditPaymentsStats['credit_payments_received'] ?? 0, 2) }}
                        </div>
                        <p class="{{ $captionClass }}">Posted payments from credit customers</p>
                        <div class="{{ $metaClass }}" id="creditPaymentsMeta">
                            {{ $creditPaymentsStats['credit_payment_count'] ?? 0 }} posted payment(s)
                        </div>
                    </article>

                    <article class="{{ $cardClass }} col-span-12 xl:col-span-6">
                        <div class="{{ $cardHeaderClass }}">
                            <h2 class="{{ $cardTitleClass }}">Advance Receivables Received</h2>
                            <div class="{{ $segmentGroupClass }}" aria-label="Advance receivables period">
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="advance-receivables" data-period="today">Today</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="advance-receivables" data-period="weekly">Week</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="advance-receivables" data-period="monthly">Month</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="advance-receivables" data-period="custom">Custom</button>
                            </div>
                        </div>
                        @php($advanceReceivablesFilter = $metricFilter('advance-receivables'))
                        <form method="GET" action="{{ url()->current() }}" class="{{ $dateFormClass }}" data-date-filter-form>
                            <label class="{{ $dateLabelClass }}">
                                <span>From</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $advanceReceivablesFilter['prefix'] }}_from" value="{{ $advanceReceivablesFilter['from'] ?? request($advanceReceivablesFilter['prefix'] . '_from') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <label class="{{ $dateLabelClass }}">
                                <span>To</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $advanceReceivablesFilter['prefix'] }}_to" value="{{ $advanceReceivablesFilter['to'] ?? request($advanceReceivablesFilter['prefix'] . '_to') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <div class="{{ $dateActionClass }} 2xl:col-span-1">
                                <button type="submit" class="{{ $primaryButtonClass }}">Apply</button>
                                <button type="button" class="{{ $secondaryButtonClass }}" data-date-reset="{{ $advanceReceivablesFilter['prefix'] }}">Clear</button>
                            </div>
                        </form>
                        <div class="{{ $metricValueClass }}" id="advanceReceivablesValue">
                            &#8369;{{ number_format($advanceReceivablesStats['advance_receivables_received'] ?? 0, 2) }}
                        </div>
                        <p class="{{ $captionClass }}">Overpayments credited as advance</p>
                        <div class="{{ $metaClass }}" id="advanceReceivablesMeta">
                            Current advance balance:
                            &#8369;{{ number_format($advanceReceivablesStats['overall_advance_balance'] ?? 0, 2) }}
                        </div>
                    </article>

                    <article class="{{ $cardClass }} col-span-12 xl:col-span-6">
                        <div class="{{ $cardHeaderClass }}">
                            <h2 class="{{ $cardTitleClass }}">Total Excess</h2>
                            <div class="{{ $segmentGroupClass }}" aria-label="Total excess period">
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="total-excess" data-period="today">Today</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="total-excess" data-period="weekly">Week</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="total-excess" data-period="monthly">Month</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="total-excess" data-period="custom">Custom</button>
                            </div>
                        </div>
                        @php($totalExcessFilter = $metricFilter('total-excess'))
                        <form method="GET" action="{{ url()->current() }}" class="{{ $dateFormClass }}" data-date-filter-form>
                            <label class="{{ $dateLabelClass }}">
                                <span>From</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $totalExcessFilter['prefix'] }}_from" value="{{ $totalExcessFilter['from'] ?? request($totalExcessFilter['prefix'] . '_from') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <label class="{{ $dateLabelClass }}">
                                <span>To</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $totalExcessFilter['prefix'] }}_to" value="{{ $totalExcessFilter['to'] ?? request($totalExcessFilter['prefix'] . '_to') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <div class="{{ $dateActionClass }} 2xl:col-span-1">
                                <button type="submit" class="{{ $primaryButtonClass }}">Apply</button>
                                <button type="button" class="{{ $secondaryButtonClass }}" data-date-reset="{{ $totalExcessFilter['prefix'] }}">Clear</button>
                            </div>
                        </form>
                        <div class="{{ $metricValueClass }}" id="totalExcessValue">
                            &#8369;{{ number_format($totalExcessStats['total_excess'] ?? 0, 2) }}
                        </div>
                        <p class="{{ $captionClass }}">Fuel transaction excess only</p>
                        <div class="{{ $metaClass }}" id="totalExcessMeta">{{ $metricPeriodLabel('total-excess') }}</div>
                    </article>

                    <article class="{{ $cardClass }} col-span-12">
                        <div class="{{ $cardHeaderClass }}">
                            <div class="min-w-0">
                                <h2 class="{{ $cardTitleClass }}">Liters Movement</h2>
                                <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Shift-reading pump liters by status, tank, and pump.</p>
                            </div>
                            <div class="flex w-full min-w-0 flex-col gap-3 sm:w-auto sm:items-end">
                                <div class="{{ $segmentGroupClass }}" aria-label="Liters movement status">
                                    <button type="button" class="{{ $segmentButtonClass }}" data-liters-status="verified">Verified</button>
                                    <button type="button" class="{{ $segmentButtonClass }}" data-liters-status="unverified">Unverified</button>
                                </div>
                                <div class="{{ $segmentGroupClass }}" aria-label="Liters movement period">
                                    <button type="button" class="{{ $segmentButtonClass }}" data-period-target="liters-movement" data-period="today">Today</button>
                                    <button type="button" class="{{ $segmentButtonClass }}" data-period-target="liters-movement" data-period="weekly">Week</button>
                                    <button type="button" class="{{ $segmentButtonClass }}" data-period-target="liters-movement" data-period="monthly">Month</button>
                                    <button type="button" class="{{ $segmentButtonClass }}" data-period-target="liters-movement" data-period="custom">Custom</button>
                                </div>
                            </div>
                        </div>
                        @php($litersMovementFilter = $metricFilter('liters-movement'))
                        <form method="GET" action="{{ url()->current() }}" class="{{ $dateFormClass }}" data-date-filter-form>
                            <label class="{{ $dateLabelClass }}">
                                <span>From</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $litersMovementFilter['prefix'] }}_from" value="{{ $litersMovementFilter['from'] ?? request($litersMovementFilter['prefix'] . '_from') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <label class="{{ $dateLabelClass }}">
                                <span>To</span>
                                <span class="relative block custom-datepicker">
                                    <input type="text" class="{{ $dateInputClass }}" name="{{ $litersMovementFilter['prefix'] }}_to" value="{{ $litersMovementFilter['to'] ?? request($litersMovementFilter['prefix'] . '_to') }}" placeholder="Select date" autocomplete="off" data-dashboard-datepicker>
                                    {!! $dateIcon !!}
                                </span>
                            </label>
                            <div class="{{ $dateActionClass }} 2xl:col-span-1">
                                <button type="submit" class="{{ $primaryButtonClass }}">Apply</button>
                                <button type="button" class="{{ $secondaryButtonClass }}" data-date-reset="{{ $litersMovementFilter['prefix'] }}">Clear</button>
                            </div>
                        </form>
                        <div class="{{ $metricValueClass }}" id="litersMovementValue">
                            {{ number_format($litersMovementStats['shift_reading_verified_liters'] ?? 0, 2) }} L
                        </div>
                        <p class="{{ $captionClass }}" id="litersMovementCaption">Verified shift readings pump liters</p>
                        <div class="{{ $metaClass }}" id="litersMovementMeta">
                            {{ $litersMovementStats['shift_reading_verified_liters_count'] ?? 0 }} verified reading(s) | {{ $metricPeriodLabel('liters-movement') }}
                        </div>
                        <div class="mt-5 grid min-w-0 grid-cols-1 gap-5 xl:grid-cols-2">
                            <div class="min-w-0">
                                <h3 class="mb-3 text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Per Tank</h3>
                                <div class="min-w-0 max-w-full overflow-x-auto custom-scrollbar">
                                    <table class="{{ $tableClass }}">
                                        <thead>
                                            <tr class="border-y border-gray-100 dark:border-gray-800">
                                                <th class="{{ $thClass }}"><p class="{{ $thTextClass }}">Tank</p></th>
                                                <th class="{{ $thClass }}"><p class="{{ $thTextClass }}">Fuel</p></th>
                                                <th class="{{ $thClass }}"><p class="{{ $thTextClass }}">Pumps</p></th>
                                                <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Liters</p></th>
                                            </tr>
                                        </thead>
                                        <tbody id="litersMovementTankRows"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <h3 class="mb-3 text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Per Pump</h3>
                                <div class="min-w-0 max-w-full overflow-x-auto custom-scrollbar">
                                    <table class="{{ $tableClass }}">
                                        <thead>
                                            <tr class="border-y border-gray-100 dark:border-gray-800">
                                                <th class="{{ $thClass }}"><p class="{{ $thTextClass }}">Pump</p></th>
                                                <th class="{{ $thClass }}"><p class="{{ $thTextClass }}">Fuel</p></th>
                                                <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Liters</p></th>
                                            </tr>
                                        </thead>
                                        <tbody id="litersMovementPumpRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </article>

                    <section class="{{ $cardClass }} col-span-12">
                        <div class="{{ $cardHeaderClass }}">
                            <div>
                                <h2 class="{{ $cardTitleClass }}">Inventory Movement Per Tank</h2>
                                <p class="mt-1 {{ $captionClass }}">Submitted pending and verified shift readings only; voided and rejected readings are excluded.</p>
                            </div>
                            <div class="{{ $segmentGroupClass }}" aria-label="Tank movement period">
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="tank-movements" data-period="today">Today</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="tank-movements" data-period="weekly">Week</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="tank-movements" data-period="monthly">Month</button>
                            </div>
                        </div>
                        <div class="min-w-0 max-w-full overflow-x-auto custom-scrollbar">
                            <table class="{{ $tableClass }}">
                                <thead>
                                    <tr class="border-y border-gray-100 dark:border-gray-800">
                                        <th class="{{ $thClass }}"><p class="{{ $thTextClass }}">Tank</p></th>
                                        <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Current</p></th>
                                        <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Delivered</p></th>
                                        <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Consumed</p></th>
                                        <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Net</p></th>
                                    </tr>
                                </thead>
                                <tbody id="tankMovementRows"></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="{{ $cardClass }} col-span-12">
                        <div class="{{ $cardHeaderClass }}">
                            <h2 class="{{ $cardTitleClass }}">Pump Sales</h2>
                            <div class="{{ $segmentGroupClass }}" aria-label="Pump sales period">
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="pump-sales" data-period="today">Today</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="pump-sales" data-period="weekly">Week</button>
                                <button type="button" class="{{ $segmentButtonClass }}" data-period-target="pump-sales" data-period="monthly">Month</button>
                            </div>
                        </div>
                        <div class="min-w-0 max-w-full overflow-x-auto custom-scrollbar">
                            <table class="{{ $tableClass }}">
                                <thead>
                                    <tr class="border-y border-gray-100 dark:border-gray-800">
                                        <th class="{{ $thClass }}"><p class="{{ $thTextClass }}">Pump</p></th>
                                        <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Liters</p></th>
                                        <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Net Sales</p></th>
                                        <th class="{{ $thNumberClass }}"><p class="{{ $thTextClass }}">Txns</p></th>
                                    </tr>
                                </thead>
                                <tbody id="pumpSalesRows"></tbody>
                            </table>
                        </div>
                    </section>

                <section class="{{ $cardClass }} col-span-12">
                    <div class="{{ $cardHeaderClass }}">
                        <h2 class="{{ $cardTitleClass }}">Sales Trend</h2>
                    </div>
                    <div class="min-h-[320px] min-w-0 max-w-full">
                        <div id="salesTrendChart" class="w-full"></div>
                    </div>
                </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
        let cardDefaultPeriods = @json($cardDefaultPeriods);
        let customMetricStats = @json($customMetricStats);
        let customPeriodLabels = @json($customPeriodLabels);
        let metricDateFilterNames = @json($metricDateFilterNames);

        const dashboardState = {
            netSalesSource: 'sales_table',
            netSalesCreditMode: 'with_credit',
            netSalesPeriod: cardDefaultPeriods['net-sales'] || 'today',
            netSalesTankPeriod: cardDefaultPeriods['net-sales-tank'] || 'today',
            expectedCreditPeriod: cardDefaultPeriods['expected-credit'] || 'today',
            creditPaymentsPeriod: cardDefaultPeriods['credit-payments'] || 'today',
            advanceReceivablesPeriod: cardDefaultPeriods['advance-receivables'] || 'today',
            totalExcessPeriod: cardDefaultPeriods['total-excess'] || 'today',
            litersMovementStatus: 'verified',
            litersMovementPeriod: cardDefaultPeriods['liters-movement'] || 'today',
            tankMovementsPeriod: 'today',
            pumpSalesPeriod: 'today'
        };

        const periodStateKeys = {
            'net-sales': 'netSalesPeriod',
            'net-sales-tank': 'netSalesTankPeriod',
            'expected-credit': 'expectedCreditPeriod',
            'credit-payments': 'creditPaymentsPeriod',
            'advance-receivables': 'advanceReceivablesPeriod',
            'total-excess': 'totalExcessPeriod',
            'liters-movement': 'litersMovementPeriod',
            'tank-movements': 'tankMovementsPeriod',
            'pump-sales': 'pumpSalesPeriod'
        };

        let periodLabels = @json($dashboardPeriodLabels);

        let dashboardData = {
            stats: @json($stats),
            tankMovements: @json($tankMovements),
            pumpSales: @json($pumpSales),
            chartLabels: @json($chartLabels),
            chartData: @json($chartData),
            periodLabels: periodLabels,
            customMetricStats: customMetricStats,
            customPeriodLabels: customPeriodLabels,
            lastVerifiedAt: @json($lastVerifiedAt),
            lastUpdatedAt: @json($lastUpdatedAt)
        };

        const numberFormatter = new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        const integerFormatter = new Intl.NumberFormat('en-PH', {
            maximumFractionDigits: 0
        });

        const activeSegmentClasses = ['bg-white', 'text-gray-900', 'shadow-theme-xs', 'dark:bg-gray-800', 'dark:text-white'];
        const inactiveSegmentClasses = ['text-gray-500', 'dark:text-gray-400'];
        const rowClass = 'border-b border-gray-100 last:border-b-0 dark:border-gray-800';
        const primaryCellClass = 'px-5 py-4 sm:px-6';
        const numberCellClass = 'px-5 py-4 text-right text-theme-sm font-medium tabular-nums text-gray-700 dark:text-gray-300 sm:px-6';
        const emptyCellClass = 'px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400 sm:px-6';
        const positiveAmountClass = 'text-success-600 dark:text-success-500';
        const negativeAmountClass = 'text-error-600 dark:text-error-500';

        function initDashboardDatePickers() {
            if (!window.flatpickr) {
                return;
            }

            document.querySelectorAll('[data-dashboard-datepicker]').forEach(function(input) {
                if (input._dashboardDatePicker) {
                    return;
                }

                input._dashboardDatePicker = window.flatpickr(input, {
                    static: true,
                    monthSelectorType: 'static',
                    dateFormat: 'Y-m-d',
                    defaultDate: input.value || null,
                    allowInput: false,
                    disableMobile: true
                });
            });
        }

        function money(value) {
            return '\u20b1' + numberFormatter.format(Number(value) || 0);
        }

        function liters(value) {
            return numberFormatter.format(Number(value) || 0) + ' L';
        }

        function signedLiters(value) {
            const amount = Number(value) || 0;
            const prefix = amount > 0 ? '+' : '';

            return prefix + liters(amount);
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[char];
            });
        }

        function fuelClasses(value) {
            const fuel = String(value ?? '').toLowerCase();

            if (fuel.includes('premium')) {
                return {
                    badge: 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400',
                    dot: 'bg-red-500',
                    progress: 'accent-red-500'
                };
            }

            if (fuel.includes('unleaded') || fuel.includes('regular')) {
                return {
                    badge: 'bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-400',
                    dot: 'bg-green-500',
                    progress: 'accent-green-500'
                };
            }

            if (fuel.includes('diesel')) {
                return {
                    badge: 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400',
                    dot: 'bg-yellow-500',
                    progress: 'accent-yellow-500'
                };
            }

            return {
                badge: 'bg-gray-50 text-gray-600 dark:bg-white/[0.03] dark:text-gray-400',
                dot: 'bg-gray-500',
                progress: 'accent-gray-500'
            };
        }

        function formatDateTime(value) {
            if (!value) {
                return 'None yet';
            }

            const parsed = new Date(String(value).replace(' ', 'T'));

            if (Number.isNaN(parsed.getTime())) {
                return value;
            }

            return parsed.toLocaleString('en-PH', {
                month: 'short',
                day: '2-digit',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        function currentStats(target, period) {
            if (period === 'custom') {
                return dashboardData.customMetricStats[target] || dashboardData.stats.today || {};
            }

            return dashboardData.stats[period] || dashboardData.stats.today || {};
        }

        function periodLabel(target, period) {
            if (period === 'custom') {
                return dashboardData.customPeriodLabels[target] || 'Custom';
            }

            return periodLabels[period] || 'Today';
        }

        function setText(id, value) {
            const el = document.getElementById(id);

            if (el) {
                el.textContent = value;
            }
        }

        function setSegmentButtonState(button, isActive) {
            button.classList.remove(...activeSegmentClasses, ...inactiveSegmentClasses);
            button.classList.add(...(isActive ? activeSegmentClasses : inactiveSegmentClasses));
        }

        function updateMetricCards() {
            const netPeriod = dashboardState.netSalesPeriod;
            const netStats = currentStats('net-sales', netPeriod);
            const netKey = dashboardState.netSalesSource + '_net_sales_' + dashboardState.netSalesCreditMode;
            const netSourceLabel = dashboardState.netSalesSource === 'shift_reading'
                ? 'Verified shift readings: pump totals less discounts'
                : 'Sales table: sales less discounts';
            const netCreditLabel = dashboardState.netSalesCreditMode === 'with_credit'
                ? 'including credits'
                : 'walk-in/cash only';

            setText('netSalesValue', money(netStats[netKey]));
            setText('netSalesCaption', netSourceLabel + ', ' + netCreditLabel);

            const netMeta = 'Period: ' + periodLabel('net-sales', netPeriod);
            const readingCount = Number(netStats.shift_reading_count) || 0;
            setText('netSalesMeta', dashboardState.netSalesSource === 'shift_reading'
                ? netMeta + ' | Verified readings: ' + integerFormatter.format(readingCount)
                : netMeta
            );
            const netTankStats = currentStats('net-sales-tank', dashboardState.netSalesTankPeriod);
            renderNetSalesByTank(netTankStats);

            const expectedStats = currentStats('expected-credit', dashboardState.expectedCreditPeriod);
            setText('expectedCreditValue', money(expectedStats.expected_credit_receivables));
            setText(
                'expectedCreditMeta',
                'Credit sales added ' + periodLabel('expected-credit', dashboardState.expectedCreditPeriod) + ': ' +
                    money(expectedStats.expected_credit_sales)
            );

            const paymentStats = currentStats('credit-payments', dashboardState.creditPaymentsPeriod);
            const paymentCount = Number(paymentStats.credit_payment_count) || 0;
            setText('creditPaymentsValue', money(paymentStats.credit_payments_received));
            setText('creditPaymentsMeta', integerFormatter.format(paymentCount) + ' posted payment' + (paymentCount === 1 ? '' : 's'));

            const advanceStats = currentStats('advance-receivables', dashboardState.advanceReceivablesPeriod);
            setText('advanceReceivablesValue', money(advanceStats.advance_receivables_received));
            setText('advanceReceivablesMeta', 'Current advance balance: ' + money(advanceStats.overall_advance_balance));

            const excessStats = currentStats('total-excess', dashboardState.totalExcessPeriod);
            setText('totalExcessValue', money(excessStats.total_excess));
            setText('totalExcessMeta', periodLabel('total-excess', dashboardState.totalExcessPeriod));

            const litersStats = currentStats('liters-movement', dashboardState.litersMovementPeriod);
            const litersStatus = dashboardState.litersMovementStatus;
            const litersKey = litersStatus === 'verified'
                ? 'shift_reading_verified_liters'
                : 'shift_reading_unverified_liters';
            const litersCountKey = litersStatus === 'verified'
                ? 'shift_reading_verified_liters_count'
                : 'shift_reading_unverified_liters_count';
            const litersCount = Number(litersStats[litersCountKey]) || 0;
            const litersLabel = litersStatus === 'verified' ? 'Verified' : 'Unverified';

            setText('litersMovementValue', liters(litersStats[litersKey]));
            setText('litersMovementCaption', litersLabel + ' shift readings pump liters');
            setText(
                'litersMovementMeta',
                integerFormatter.format(litersCount) + ' ' + litersStatus + ' reading' + (litersCount === 1 ? '' : 's') +
                    ' | ' + periodLabel('liters-movement', dashboardState.litersMovementPeriod)
            );
            renderLitersMovementBreakdowns(litersStats, litersStatus);
        }

        function renderNetSalesByTank(stats) {
            const rows = stats[dashboardState.netSalesSource + '_net_sales_by_tank_' + dashboardState.netSalesCreditMode] || [];
            const tbody = document.getElementById('netSalesTankRows');
            const sourceLabel = dashboardState.netSalesSource === 'shift_reading' ? 'Shift readings' : 'Sales table';
            const creditLabel = dashboardState.netSalesCreditMode === 'with_credit' ? 'Include credits' : 'Walk-in only';

            setText(
                'netSalesTankMeta',
                sourceLabel + ' | ' + creditLabel + ' | Period: ' + periodLabel('net-sales-tank', dashboardState.netSalesTankPeriod)
            );

            if (!tbody) {
                return;
            }

            if (!rows.length) {
                tbody.innerHTML = `<tr><td class="${emptyCellClass}" colspan="5">No tank net sales found.</td></tr>`;
                return;
            }

            tbody.innerHTML = rows.map(function(row) {
                const fuelColorClasses = fuelClasses(row.fuel_type);
                const netSales = Number(row.net_sales) || 0;
                const netClass = netSales < 0 ? negativeAmountClass : '';

                return `
                    <tr class="${rowClass}">
                        <td class="${primaryCellClass}">
                            <span class="block text-theme-sm font-medium text-gray-800 dark:text-white/90">${escapeHtml(row.name)}</span>
                        </td>
                        <td class="${primaryCellClass}" data-label="Fuel">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-theme-xs font-medium ${fuelColorClasses.badge}">
                                <span class="h-1.5 w-1.5 rounded-full ${fuelColorClasses.dot}"></span>
                                ${escapeHtml(row.fuel_type)}
                            </span>
                        </td>
                        <td class="${primaryCellClass}" data-label="Pumps">${escapeHtml(row.connected_pumps_display)}</td>
                        <td class="${numberCellClass}" data-label="Liters">${liters(row.liters)}</td>
                        <td class="${numberCellClass} ${netClass}" data-label="Net Sales">${money(row.net_sales)}</td>
                    </tr>
                `;
            }).join('');
        }

        function updateButtonStates() {
            Object.entries(periodStateKeys).forEach(function([target, stateKey]) {
                document.querySelectorAll('[data-period-target="' + target + '"]').forEach(function(button) {
                    if (button.dataset.period === 'custom') {
                        button.disabled = target in dashboardData.customMetricStats ? false : !['tank-movements', 'pump-sales'].includes(target);
                    }

                    setSegmentButtonState(button, button.dataset.period === dashboardState[stateKey]);
                });
            });

            document.querySelectorAll('[data-net-source]').forEach(function(button) {
                setSegmentButtonState(button, button.dataset.netSource === dashboardState.netSalesSource);
            });

            document.querySelectorAll('[data-net-credit-mode]').forEach(function(button) {
                setSegmentButtonState(button, button.dataset.netCreditMode === dashboardState.netSalesCreditMode);
            });

            document.querySelectorAll('[data-liters-status]').forEach(function(button) {
                setSegmentButtonState(button, button.dataset.litersStatus === dashboardState.litersMovementStatus);
            });
        }

        function renderLitersMovementBreakdowns(stats, status) {
            const tankRows = status === 'verified'
                ? (stats.shift_reading_verified_tanks || [])
                : (stats.shift_reading_unverified_tanks || []);
            const pumpRows = status === 'verified'
                ? (stats.shift_reading_verified_pumps || [])
                : (stats.shift_reading_unverified_pumps || []);
            const tankBody = document.getElementById('litersMovementTankRows');
            const pumpBody = document.getElementById('litersMovementPumpRows');

            if (tankBody) {
                if (!tankRows.length) {
                    tankBody.innerHTML = `<tr><td class="${emptyCellClass}" colspan="4">No tank liters found.</td></tr>`;
                } else {
                    tankBody.innerHTML = tankRows.map(function(row) {
                        const fuelColorClasses = fuelClasses(row.fuel_type);

                        return `
                            <tr class="${rowClass}">
                                <td class="${primaryCellClass}">
                                    <span class="block text-theme-sm font-medium text-gray-800 dark:text-white/90">${escapeHtml(row.name)}</span>
                                </td>
                                <td class="${primaryCellClass}" data-label="Fuel">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-theme-xs font-medium ${fuelColorClasses.badge}">
                                        <span class="h-1.5 w-1.5 rounded-full ${fuelColorClasses.dot}"></span>
                                        ${escapeHtml(row.fuel_type)}
                                    </span>
                                </td>
                                <td class="${primaryCellClass}" data-label="Pumps">${escapeHtml(row.connected_pumps_display)}</td>
                                <td class="${numberCellClass}" data-label="Liters">${liters(row.liters)}</td>
                            </tr>
                        `;
                    }).join('');
                }
            }

            if (pumpBody) {
                if (!pumpRows.length) {
                    pumpBody.innerHTML = `<tr><td class="${emptyCellClass}" colspan="3">No pump liters found.</td></tr>`;
                } else {
                    pumpBody.innerHTML = pumpRows.map(function(row) {
                        const fuelColorClasses = fuelClasses(row.fuel_type);

                        return `
                            <tr class="${rowClass}">
                                <td class="${primaryCellClass}">
                                    <span class="block text-theme-sm font-medium text-gray-800 dark:text-white/90">${escapeHtml(row.pump_label)}</span>
                                </td>
                                <td class="${primaryCellClass}" data-label="Fuel">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-theme-xs font-medium ${fuelColorClasses.badge}">
                                        <span class="h-1.5 w-1.5 rounded-full ${fuelColorClasses.dot}"></span>
                                        ${escapeHtml(row.fuel_type)}
                                    </span>
                                </td>
                                <td class="${numberCellClass}" data-label="Liters">${liters(row.liters)}</td>
                            </tr>
                        `;
                    }).join('');
                }
            }
        }

        function renderTankMovements() {
            const rows = dashboardData.tankMovements[dashboardState.tankMovementsPeriod] || [];
            const tbody = document.getElementById('tankMovementRows');

            if (!tbody) {
                return;
            }

            if (!rows.length) {
                tbody.innerHTML = `<tr><td class="${emptyCellClass}" colspan="5">No tank movement found.</td></tr>`;
                return;
            }

            tbody.innerHTML = rows.map(function(row) {
                const percentage = Math.max(0, Math.min(100, Number(row.percentage) || 0));
                const netLiters = Number(row.net_liters) || 0;
                const netClass = netLiters < 0 ? negativeAmountClass : positiveAmountClass;
                const fuelColorClasses = fuelClasses(row.fuel_type);

                return `
                    <tr class="${rowClass}">
                        <td class="${primaryCellClass}">
                            <span class="block text-theme-sm font-medium text-gray-800 dark:text-white/90">${escapeHtml(row.name)}</span>
                            <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-theme-xs font-medium ${fuelColorClasses.badge}">
                                <span class="h-1.5 w-1.5 rounded-full ${fuelColorClasses.dot}"></span>
                                ${escapeHtml(row.fuel_type)} | ${escapeHtml(row.connected_pumps_display)} | ${percentage.toFixed(1)}%
                            </span>
                            <progress value="${percentage}" max="100" class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100 ${fuelColorClasses.progress} dark:bg-gray-800"></progress>
                        </td>
                        <td class="${numberCellClass}" data-label="Current">${liters(row.current_volume)}</td>
                        <td class="${numberCellClass}" data-label="Delivered">${liters(row.delivered_liters)}</td>
                        <td class="${numberCellClass}" data-label="Consumed">${liters(row.consumed_liters ?? row.sold_liters)}</td>
                        <td class="${numberCellClass} ${netClass}" data-label="Net">${signedLiters(row.net_liters)}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderPumpSales() {
            const rows = dashboardData.pumpSales[dashboardState.pumpSalesPeriod] || [];
            const tbody = document.getElementById('pumpSalesRows');

            if (!tbody) {
                return;
            }

            if (!rows.length) {
                tbody.innerHTML = `<tr><td class="${emptyCellClass}" colspan="4">No pump sales found.</td></tr>`;
                return;
            }

            tbody.innerHTML = rows.map(function(row) {
                const fuelColorClasses = fuelClasses(row.fuel_type);

                return `
                    <tr class="${rowClass}">
                        <td class="${primaryCellClass}">
                            <span class="block text-theme-sm font-medium text-gray-800 dark:text-white/90">${escapeHtml(row.pump_label)}</span>
                            <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-theme-xs font-medium ${fuelColorClasses.badge}">
                                <span class="h-1.5 w-1.5 rounded-full ${fuelColorClasses.dot}"></span>
                                ${escapeHtml(row.fuel_type)}
                            </span>
                        </td>
                        <td class="${numberCellClass}" data-label="Liters">${liters(row.liters)}</td>
                        <td class="${numberCellClass}" data-label="Net Sales">${money(row.net_sales)}</td>
                        <td class="${numberCellClass}" data-label="Txns">${integerFormatter.format(Number(row.transactions) || 0)}</td>
                    </tr>
                `;
            }).join('');
        }

        function updateAll() {
            updateButtonStates();
            updateMetricCards();
            renderTankMovements();
            renderPumpSales();
        }

        document.querySelectorAll('[data-period-target]').forEach(function(button) {
            button.addEventListener('click', function() {
                const stateKey = periodStateKeys[button.dataset.periodTarget];

                if (!stateKey) {
                    return;
                }

                dashboardState[stateKey] = button.dataset.period;
                updateAll();
            });
        });

        document.querySelectorAll('[data-net-source]').forEach(function(button) {
            button.addEventListener('click', function() {
                dashboardState.netSalesSource = button.dataset.netSource;
                updateAll();
            });
        });

        document.querySelectorAll('[data-net-credit-mode]').forEach(function(button) {
            button.addEventListener('click', function() {
                dashboardState.netSalesCreditMode = button.dataset.netCreditMode;
                updateAll();
            });
        });

        document.querySelectorAll('[data-liters-status]').forEach(function(button) {
            button.addEventListener('click', function() {
                dashboardState.litersMovementStatus = button.dataset.litersStatus;
                updateAll();
            });
        });

        document.querySelectorAll('[data-date-filter-form]').forEach(function(form) {
            form.addEventListener('submit', function() {
                const params = new URLSearchParams(window.location.search);
                const formNames = new Set(Array.from(form.elements)
                    .filter((element) => element.name)
                    .map((element) => element.name));

                form.querySelectorAll('[data-preserved-param]').forEach((input) => input.remove());

                params.forEach(function(value, key) {
                    if (key === 't' || formNames.has(key)) {
                        return;
                    }

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    input.dataset.preservedParam = 'true';
                    form.appendChild(input);
                });
            });
        });

        document.querySelectorAll('[data-date-reset]').forEach(function(button) {
            button.addEventListener('click', function() {
                const prefix = button.dataset.dateReset;
                const params = new URLSearchParams(window.location.search);

                params.delete(prefix + '_from');
                params.delete(prefix + '_to');
                params.delete('t');

                const query = params.toString();
                window.location.href = window.location.pathname + (query ? '?' + query : '');
            });
        });

        const chartElement = document.getElementById('salesTrendChart');
        const salesTrendChart = chartElement && window.ApexCharts ? new window.ApexCharts(chartElement, {
            chart: {
                type: 'line',
                height: 320,
                toolbar: { show: false },
                zoom: { enabled: false },
                fontFamily: 'Roboto, Segoe UI, Helvetica Neue, Arial, sans-serif'
            },
            series: [{
                name: 'Net Sales',
                data: dashboardData.chartData.map((value) => Number(value) || 0)
            }],
            colors: ['#111827'],
            stroke: {
                curve: 'smooth',
                width: 2
            },
            markers: {
                size: 3,
                colors: ['#ffffff'],
                strokeColors: '#111827',
                strokeWidth: 2
            },
            dataLabels: {
                enabled: false
            },
            grid: {
                borderColor: '#e5e7eb',
                strokeDashArray: 0
            },
            xaxis: {
                categories: dashboardData.chartLabels,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: {
                    style: { colors: '#6b7280' }
                }
            },
            yaxis: {
                labels: {
                    style: { colors: '#6b7280' },
                    formatter: function(value) {
                        return money(value);
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function(value) {
                        return money(value);
                    }
                }
            }
        }) : null;

        if (salesTrendChart) {
            salesTrendChart.render();
        }

        function refreshDashboard() {
            const refreshParams = new URLSearchParams(window.location.search);
            refreshParams.set('t', Date.now());

            fetch("{{ url('/api/dashboard-data') }}?" + refreshParams.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    dashboardData = {
                        stats: data.stats ?? dashboardData.stats,
                        tankMovements: data.tankMovements ?? dashboardData.tankMovements,
                        pumpSales: data.pumpSales ?? dashboardData.pumpSales,
                        chartLabels: data.chartLabels ?? dashboardData.chartLabels,
                        chartData: data.chartData ?? dashboardData.chartData,
                        periodLabels: data.periodLabels ?? dashboardData.periodLabels,
                        customMetricStats: data.customMetricStats ?? dashboardData.customMetricStats,
                        customPeriodLabels: data.customPeriodLabels ?? dashboardData.customPeriodLabels,
                        lastVerifiedAt: Object.prototype.hasOwnProperty.call(data, 'lastVerifiedAt')
                            ? data.lastVerifiedAt
                            : dashboardData.lastVerifiedAt,
                        lastUpdatedAt: data.lastUpdatedAt ?? dashboardData.lastUpdatedAt
                    };
                    periodLabels = dashboardData.periodLabels;
                    customMetricStats = dashboardData.customMetricStats;
                    customPeriodLabels = dashboardData.customPeriodLabels;

                    setText('last-updated', 'Live as of ' + formatDateTime(dashboardData.lastUpdatedAt));

                    if (salesTrendChart) {
                        salesTrendChart.updateOptions({
                            xaxis: {
                                categories: dashboardData.chartLabels
                            }
                        }, false, false);
                        salesTrendChart.updateSeries([{
                            name: 'Net Sales',
                            data: dashboardData.chartData.map((value) => Number(value) || 0)
                        }]);
                    }

                    updateAll();
                })
                .catch(function(error) {
                    console.error(error);
                });
        }

        initDashboardDatePickers();
        updateAll();
        setInterval(refreshDashboard, 10000);
        });
    </script>
@endpush
