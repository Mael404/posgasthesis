@extends('layouts.app')

@section('content')
    @php
        $cardClass = 'min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]';
        $titleClass = 'text-theme-sm font-semibold text-gray-800 dark:text-white/90';
        $valueClass = 'mt-4 break-words text-2xl font-semibold tracking-tight tabular-nums text-gray-900 dark:text-white/90';
        $captionClass = 'mt-2 text-theme-xs leading-5 text-gray-500 dark:text-gray-400';
        $metaClass = 'mt-4 border-t border-gray-100 pt-3 text-theme-xs leading-5 text-gray-500 dark:border-gray-800 dark:text-gray-400';
        $inputClass = 'h-10 w-full min-w-0 rounded-lg border border-gray-300 bg-transparent px-3 text-theme-sm text-gray-700 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-gray-300 dark:[color-scheme:dark]';
        $buttonClass = 'inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-brand-500 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]';
        $negativeInventory = $warnings['negative_inventory'];
        $initialDashboardData = compact('filter', 'stats', 'inventory', 'chartLabels', 'chartData', 'warnings', 'lastUpdatedAt');
    @endphp

    <div class="min-w-0 space-y-5" id="adminDashboard">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Admin Dashboard</h1>
                <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" id="last-updated">Live as of {{ \Carbon\Carbon::parse($lastUpdatedAt)->format('M d, Y h:i A') }}</p>
            </div>
            <a href="{{ route('admin.backup.db') }}" class="{{ $buttonClass }}">Backup Database</a>
        </div>

        <form method="GET" action="{{ url()->current() }}" class="{{ $cardClass }}" id="dashboardDateFilter" aria-label="Dashboard date filter">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="{{ $titleClass }}">Overview</p>
                    <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" id="dashboardRangeLabel">{{ $filter['label'] }}</p>
                </div>
                <div class="inline-flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-900" role="group" aria-label="Dashboard period">
                    @foreach (['today' => 'Today', 'weekly' => 'Week', 'monthly' => 'Month', 'custom' => 'Custom'] as $period => $label)
                        <button type="{{ $period === 'custom' ? 'button' : 'submit' }}" name="period" value="{{ $period }}" data-dashboard-period="{{ $period }}" aria-pressed="{{ $filter['period'] === $period ? 'true' : 'false' }}" @if($period === 'custom') aria-controls="dashboardCustomRange" aria-expanded="{{ $filter['period'] === 'custom' ? 'true' : 'false' }}" @endif class="rounded-md px-3 py-2 text-theme-xs font-medium transition focus-visible:outline-2 focus-visible:outline-brand-500 disabled:opacity-50 {{ $filter['period'] === $period ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div id="dashboardCustomRange" @if($filter['period'] !== 'custom') hidden @endif>
                <div class="mt-4 grid grid-cols-1 items-end gap-3 border-t border-gray-100 pt-4 dark:border-gray-800 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                    <label class="min-w-0 text-theme-xs font-medium text-gray-500 dark:text-gray-400">From
                        <input type="date" name="from" value="{{ $filter['from'] }}" class="{{ $inputClass }} mt-1.5" aria-label="Start date">
                    </label>
                    <label class="min-w-0 text-theme-xs font-medium text-gray-500 dark:text-gray-400">To
                        <input type="date" name="to" value="{{ $filter['to'] }}" class="{{ $inputClass }} mt-1.5" aria-label="End date">
                    </label>
                    <button type="submit" name="period" value="custom" class="{{ $buttonClass }}">Apply range</button>
                </div>
            </div>
            <p class="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">Activity follows the selected dates. Receivables and inventory show current balances.</p>
        </form>

        <p id="dashboardError" hidden role="alert" class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400"></p>

        <div class="space-y-2" aria-live="polite" aria-atomic="true">
            <p id="negativeInventoryWarning" @if(count($negativeInventory) === 0) hidden @endif class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400"><strong>Negative inventory balance:</strong> <span id="negativeInventoryNames">{{ collect($negativeInventory)->map(fn ($tank) => $tank['name'] . ' (' . number_format($tank['current_volume'], 2) . ' L)')->implode(', ') }}</span>. Review inventory records.</p>
            <p id="pendingReadingsWarning" @if($warnings['pending_readings'] === 0) hidden @endif class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-theme-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400"><strong id="pendingReadingsCount">{{ number_format($warnings['pending_readings']) }} pending shift reading(s)</strong> in the selected period. Pending readings are excluded from verified totals.</p>
        </div>

        <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4" id="dashboardSummary" aria-label="Dashboard summary">
            <article class="{{ $cardClass }}" aria-labelledby="netSalesTitle">
                <h2 class="{{ $titleClass }}" id="netSalesTitle">Net Sales</h2>
                <p class="{{ $valueClass }}" id="netSalesValue">&#8369;{{ number_format($stats['shift_reading_verified_net_sales_with_credit'], 2) }}</p>
                <p class="{{ $captionClass }}">Pump totals less discounts</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <label class="sr-only" for="netSalesStatus">Net sales reading status</label>
                    <select id="netSalesStatus" class="{{ $inputClass }} flex-1" style="height: 2rem; font-size: 0.75rem;">
                        <option value="verified">Verified</option>
                        <option value="pending">Pending</option>
                        <option value="all">Pending &amp; verified</option>
                    </select>
                    <label class="sr-only" for="netSalesCreditMode">Net sales credit inclusion</label>
                    <select id="netSalesCreditMode" class="{{ $inputClass }} flex-1" style="height: 2rem; font-size: 0.75rem;">
                        <option value="with_credit">Include credits</option>
                        <option value="without_credit">Walk-in only</option>
                    </select>
                </div>
                <p class="{{ $metaClass }}" id="netSalesMeta">{{ number_format($stats['shift_reading_verified_count']) }} verified reading(s)</p>
            </article>

            <article class="{{ $cardClass }}" aria-labelledby="creditReceivablesTitle">
                <h2 class="{{ $titleClass }}" id="creditReceivablesTitle">Credit Receivables</h2>
                <p class="{{ $valueClass }}" id="creditReceivablesValue">&#8369;{{ number_format($stats['expected_credit_receivables'], 2) }}</p>
                <p class="{{ $captionClass }}">Current outstanding credit balance</p>
                <p class="{{ $metaClass }}" id="creditReceivablesMeta">Credit sales in period: &#8369;{{ number_format($stats['expected_credit_sales'], 2) }}</p>
            </article>

            <article class="{{ $cardClass }}" aria-labelledby="creditPaymentsTitle">
                <h2 class="{{ $titleClass }}" id="creditPaymentsTitle">Credit Payments Received</h2>
                <p class="{{ $valueClass }}" id="creditPaymentsValue">&#8369;{{ number_format($stats['credit_payments_received'], 2) }}</p>
                <p class="{{ $captionClass }}">Posted payments from credit customers</p>
                <p class="{{ $metaClass }}" id="creditPaymentsMeta">{{ number_format($stats['credit_payment_count']) }} posted payment(s)</p>
            </article>

            <article class="{{ $cardClass }}" aria-labelledby="litersMovementTitle">
                <h2 class="{{ $titleClass }}" id="litersMovementTitle">Liters Movement</h2>
                <p class="{{ $valueClass }}" id="litersMovementValue">{{ number_format($stats['shift_reading_verified_liters'], 2) }} L</p>
                <p class="{{ $captionClass }}">Pump liters from shift readings</p>
                <label class="sr-only" for="litersMovementStatus">Liters movement reading status</label>
                <select id="litersMovementStatus" class="{{ $inputClass }} mt-3" style="height: 2rem; font-size: 0.75rem;">
                    <option value="verified">Verified</option>
                    <option value="unverified">Unverified</option>
                </select>
                <p class="{{ $metaClass }}" id="litersMovementMeta">{{ number_format($stats['shift_reading_verified_liters_count']) }} verified reading(s)</p>
            </article>
        </div>

        <div class="grid min-w-0 grid-cols-1 gap-4 xl:grid-cols-5">
            <article class="{{ $cardClass }} xl:col-span-2" aria-labelledby="fuelInventoryTitle">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="{{ $titleClass }}" id="fuelInventoryTitle">Fuel Inventory Status</h2>
                    <span class="text-theme-xs text-gray-500 dark:text-gray-400">Current balances</span>
                </div>
                <div class="mt-5 space-y-5" id="fuelInventoryItems">
                    @forelse ($inventory as $tank)
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-start justify-between gap-2 text-theme-sm">
                                <div class="min-w-0"><p class="break-words font-medium text-gray-800 dark:text-white/90">{{ $tank['name'] }}</p><p class="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">{{ $tank['fuel_type'] }}</p></div>
                                <div class="text-right"><p class="font-semibold tabular-nums {{ $tank['current_volume'] < 0 ? 'text-error-600 dark:text-error-400' : 'text-gray-800 dark:text-white/90' }}">{{ number_format($tank['current_volume'], 2) }} L</p><p class="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">of {{ number_format($tank['capacity'], 2) }} L</p></div>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" role="meter" aria-label="{{ $tank['name'] }} fill level" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $tank['percentage'] }}" aria-valuetext="{{ number_format($tank['current_volume'], 2) }} liters">
                                <div class="h-full rounded-full {{ $tank['current_volume'] < 0 ? 'bg-error-500' : 'bg-brand-500' }}" style="width: {{ $tank['percentage'] }}%"></div>
                            </div>
                            @if ($tank['current_volume'] < 0)
                                <p class="mt-1 text-theme-xs font-medium text-error-600 dark:text-error-400">Negative balance</p>
                            @elseif ($tank['current_volume'] == 0)
                                <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Empty</p>
                            @endif
                        </div>
                    @empty
                        <p class="py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No fuel tanks available.</p>
                    @endforelse
                </div>
            </article>

            <article class="{{ $cardClass }} xl:col-span-3" aria-labelledby="salesTrendTitle">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="{{ $titleClass }}" id="salesTrendTitle">Sales Trend</h2>
                    <span class="text-theme-xs text-gray-500 dark:text-gray-400" id="salesTrendRange">{{ $filter['label'] }}</span>
                </div>
                <p class="{{ $captionClass }}">Daily net fuel sales, including credits, from recorded transactions.</p>
                <div class="mt-4 min-w-0" id="salesTrendChart" style="min-height: 280px;" role="img" aria-label="Daily net fuel sales for {{ $filter['label'] }}"></div>
                <p class="text-theme-xs text-gray-500 dark:text-gray-400" id="salesTrendTotal">Period total: &#8369;{{ number_format(array_sum($chartData), 2) }}</p>
            </article>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let dashboardData = @json($initialDashboardData);
            const automaticBackupDownloadUrl = @json(session('admin_auto_backup_download_url'));
            const form = document.getElementById('dashboardDateFilter');
            const customRange = document.getElementById('dashboardCustomRange');
            const error = document.getElementById('dashboardError');
            const numberFormatter = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const integerFormatter = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 });
            const money = value => '₱' + numberFormatter.format(Number(value) || 0);
            const liters = value => numberFormatter.format(Number(value) || 0) + ' L';
            const setText = (id, value) => { document.getElementById(id).textContent = value; };
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
            let busy = false;
            let chart = null;
            let chartReady = Promise.resolve();

            function updateSummary() {
                const stats = dashboardData.stats;
                const status = document.getElementById('netSalesStatus').value;
                const creditMode = document.getElementById('netSalesCreditMode').value;
                const statusLabel = { verified: 'verified', pending: 'pending', all: 'pending & verified' }[status];
                setText('netSalesValue', money(stats['shift_reading_' + status + '_net_sales_' + creditMode]));
                setText('netSalesMeta', integerFormatter.format(stats['shift_reading_' + status + '_count']) + ' ' + statusLabel + ' reading(s)');
                setText('creditReceivablesValue', money(stats.expected_credit_receivables));
                setText('creditReceivablesMeta', 'Credit sales in period: ' + money(stats.expected_credit_sales));
                setText('creditPaymentsValue', money(stats.credit_payments_received));
                setText('creditPaymentsMeta', integerFormatter.format(stats.credit_payment_count) + ' posted payment(s)');
                const litersStatus = document.getElementById('litersMovementStatus').value;
                setText('litersMovementValue', liters(stats['shift_reading_' + litersStatus + '_liters']));
                setText('litersMovementMeta', integerFormatter.format(stats['shift_reading_' + litersStatus + '_liters_count']) + ' ' + litersStatus + ' reading(s)');
            }

            function updateWarnings() {
                const warnings = dashboardData.warnings;
                document.getElementById('pendingReadingsWarning').hidden = warnings.pending_readings === 0;
                setText('pendingReadingsCount', integerFormatter.format(warnings.pending_readings) + ' pending shift reading(s)');
                document.getElementById('negativeInventoryWarning').hidden = warnings.negative_inventory.length === 0;
                setText('negativeInventoryNames', warnings.negative_inventory.map(tank => tank.name + ' (' + liters(tank.current_volume) + ')').join(', '));
            }

            function renderInventory() {
                document.getElementById('fuelInventoryItems').innerHTML = dashboardData.inventory.map(tank => {
                    const negative = Number(tank.current_volume) < 0;
                    const percentage = Math.max(0, Math.min(100, Number(tank.percentage) || 0));
                    return `<div class="min-w-0">
                        <div class="flex flex-wrap items-start justify-between gap-2 text-theme-sm">
                            <div class="min-w-0"><p class="break-words font-medium text-gray-800 dark:text-white/90">${escapeHtml(tank.name)}</p><p class="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">${escapeHtml(tank.fuel_type)}</p></div>
                            <div class="text-right"><p class="font-semibold tabular-nums ${negative ? 'text-error-600 dark:text-error-400' : 'text-gray-800 dark:text-white/90'}">${liters(tank.current_volume)}</p><p class="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">of ${liters(tank.capacity)}</p></div>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" role="meter" aria-label="${escapeHtml(tank.name)} fill level" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percentage}" aria-valuetext="${liters(tank.current_volume)}">
                            <div class="h-full rounded-full ${negative ? 'bg-error-500' : 'bg-brand-500'}" style="width: ${percentage}%"></div>
                        </div>
                        ${negative ? '<p class="mt-1 text-theme-xs font-medium text-error-600 dark:text-error-400">Negative balance</p>' : Number(tank.current_volume) === 0 ? '<p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Empty</p>' : ''}
                    </div>`;
                }).join('') || '<p class="py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No fuel tanks available.</p>';
            }

            function chartSeries() {
                return [{ name: 'Net Sales', data: dashboardData.chartData.map(value => Number(value) || 0) }];
            }

            function updateDisplay() {
                updateSummary();
                updateWarnings();
                renderInventory();
                setText('dashboardRangeLabel', dashboardData.filter.label);
                setText('salesTrendRange', dashboardData.filter.label);
                setText('salesTrendTotal', 'Period total: ' + money(dashboardData.chartData.reduce((total, value) => total + Number(value), 0)));
                setText('last-updated', 'Live as of ' + new Date(dashboardData.lastUpdatedAt.replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }));
                document.getElementById('salesTrendChart').setAttribute('aria-label', 'Daily net fuel sales for ' + dashboardData.filter.label);
                document.querySelectorAll('[data-dashboard-period]').forEach(button => {
                    const active = button.dataset.dashboardPeriod === dashboardData.filter.period;
                    button.setAttribute('aria-pressed', String(active));
                    ['bg-white', 'text-gray-900', 'shadow-theme-xs', 'dark:bg-gray-800', 'dark:text-white'].forEach(name => button.classList.toggle(name, active));
                    ['text-gray-500', 'dark:text-gray-400'].forEach(name => button.classList.toggle(name, !active));
                });
            }

            function showCustomRange(show) {
                customRange.hidden = !show;
                document.querySelector('[data-dashboard-period="custom"]').setAttribute('aria-expanded', String(show));
            }

            function currentParams() {
                const params = new URLSearchParams({ period: dashboardData.filter.period });
                if (dashboardData.filter.period === 'custom') {
                    params.set('from', dashboardData.filter.from);
                    params.set('to', dashboardData.filter.to);
                }
                return params;
            }

            async function requestData(params, changeFilter = false) {
                if (busy) return;
                busy = true;
                document.getElementById('adminDashboard').setAttribute('aria-busy', 'true');
                form.querySelectorAll('button').forEach(button => { button.disabled = true; });
                try {
                    const response = await fetch(@json(route('dashboard.admin.data')) + '?' + params.toString(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                    if (!response.ok) {
                        const body = await response.json().catch(() => ({}));
                        throw new Error(response.status === 422 ? Object.values(body.errors || {}).flat().join(' ') || 'Choose a valid date range.' : 'Dashboard could not refresh. Displaying the last loaded values.');
                    }
                    dashboardData = await response.json();
                    updateDisplay();
                    if (changeFilter) {
                        history.replaceState(null, '', window.location.pathname + '?' + currentParams().toString() + window.location.hash);
                        form.elements.from.value = dashboardData.filter.from;
                        form.elements.to.value = dashboardData.filter.to;
                        showCustomRange(dashboardData.filter.period === 'custom');
                    }
                    if (chart) await chartReady.then(() => chart.updateOptions({ series: chartSeries(), xaxis: { categories: dashboardData.chartLabels }, markers: { size: dashboardData.chartLabels.length === 1 ? 4 : 0 } }, false, false));
                    error.hidden = true;
                } catch (failure) {
                    error.textContent = failure.message || 'Dashboard could not refresh. Displaying the last loaded values.';
                    error.hidden = false;
                } finally {
                    busy = false;
                    document.getElementById('adminDashboard').setAttribute('aria-busy', 'false');
                    form.querySelectorAll('button').forEach(button => { button.disabled = false; });
                }
            }

            form.addEventListener('submit', event => {
                event.preventDefault();
                const period = event.submitter?.value || 'custom';
                const params = new URLSearchParams({ period });
                if (period === 'custom') {
                    params.set('from', form.elements.from.value);
                    params.set('to', form.elements.to.value);
                }
                requestData(params, true);
            });
            document.querySelector('[data-dashboard-period="custom"]').addEventListener('click', () => {
                showCustomRange(customRange.hidden);
                if (!customRange.hidden) form.elements.from.focus();
            });
            ['netSalesStatus', 'netSalesCreditMode', 'litersMovementStatus'].forEach(id => document.getElementById(id).addEventListener('change', updateSummary));

            if (window.ApexCharts) {
                chart = new window.ApexCharts(document.getElementById('salesTrendChart'), {
                    chart: { type: 'area', height: 280, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', foreColor: '#6b7280' },
                    series: chartSeries(),
                    colors: [getComputedStyle(document.documentElement).getPropertyValue('--color-brand-500').trim() || '#15803d'],
                    stroke: { curve: 'straight', width: 2 },
                    fill: { type: 'gradient', gradient: { opacityFrom: 0.2, opacityTo: 0.02 } },
                    markers: { size: dashboardData.chartLabels.length === 1 ? 4 : 0 },
                    dataLabels: { enabled: false },
                    grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
                    xaxis: {
                        type: 'category', categories: dashboardData.chartLabels, tickPlacement: 'on',
                        labels: { hideOverlappingLabels: true, formatter: value => new Date(String(value) + 'T00:00:00Z').toLocaleDateString('en-PH', { month: 'short', day: '2-digit', timeZone: 'UTC' }) },
                        axisBorder: { show: false }, axisTicks: { show: false }
                    },
                    yaxis: { min: minimum => Math.min(0, minimum), labels: { formatter: money } },
                    tooltip: { theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light', x: { formatter: (value, context) => dashboardData.chartLabels[context.dataPointIndex] || value }, y: { formatter: money } },
                    noData: { text: 'No sales recorded for this period.' }
                });
                chartReady = chart.render();
            } else {
                setText('salesTrendChart', 'Sales chart is unavailable. The period total is shown below.');
            }
            updateSummary();
            if (automaticBackupDownloadUrl) window.setTimeout(() => { window.location.href = automaticBackupDownloadUrl; }, 500);
            const refreshTimer = window.setInterval(() => { if (!document.hidden) requestData(currentParams()); }, 10000);
            window.addEventListener('pagehide', () => window.clearInterval(refreshTimer), { once: true });
        });
    </script>
@endpush
