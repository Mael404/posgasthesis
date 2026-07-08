<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Print Transaction History' }} | JRM Fuel Station</title>
    @include('layouts.browser-icon')
    <style>
        @page {
            margin: 1cm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #1f2937;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .no-print {
            position: fixed;
            right: 20px;
            top: 20px;
            z-index: 10;
            border: 0;
            border-radius: 6px;
            background: #465fff;
            color: #fff;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            padding: 10px 16px;
        }

        .report-header {
            border-bottom: 2px solid #465fff;
            margin-bottom: 16px;
            padding-bottom: 12px;
        }

        .header-grid {
            display: table;
            width: 100%;
        }

        .header-left,
        .header-right {
            display: table-cell;
            vertical-align: top;
        }

        .header-right {
            text-align: right;
        }

        .company-name {
            color: #465fff;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: .3px;
            margin: 0;
            text-transform: uppercase;
        }

        .company-detail {
            color: #6b7280;
            margin: 2px 0 0;
        }

        .report-title {
            color: #9ca3af;
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .meta-grid {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            display: table;
            margin-bottom: 16px;
            padding: 10px 12px;
            width: 100%;
        }

        .meta-item {
            display: table-cell;
            padding-right: 12px;
            vertical-align: top;
        }

        .meta-label {
            color: #6b7280;
            font-size: 9px;
            font-weight: 700;
            margin: 0 0 2px;
            text-transform: uppercase;
        }

        .meta-value {
            color: #111827;
            font-size: 12px;
            font-weight: 700;
            margin: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        th {
            background: #465fff;
            border: 1px solid #465fff;
            color: #fff;
            font-size: 9px;
            padding: 7px 6px;
            text-align: left;
            text-transform: uppercase;
        }

        td {
            border: 1px solid #e5e7eb;
            font-size: 10.5px;
            padding: 6px;
            vertical-align: top;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: 700;
        }

        .muted {
            color: #6b7280;
            font-size: 9.5px;
        }

        .mono {
            font-family: "Courier New", monospace;
        }

        .receipt-missing {
            color: #b91c1c;
            font-weight: 700;
        }

        .summary-wrap {
            display: table;
            margin-top: 14px;
            width: 100%;
        }

        .summary {
            border-collapse: collapse;
            display: table;
            margin-left: auto;
            width: 330px;
        }

        .summary-row {
            display: table-row;
        }

        .summary-cell {
            border-bottom: 1px solid #e5e7eb;
            display: table-cell;
            padding: 6px;
        }

        .summary-label {
            color: #6b7280;
            font-weight: 700;
        }

        .summary-value {
            font-family: "Courier New", monospace;
            font-weight: 700;
            text-align: right;
        }

        .grand-total .summary-cell {
            background: #465fff;
            border-bottom: 0;
            color: #fff;
            font-size: 13px;
            padding: 9px 6px;
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 9px;
            margin-top: 24px;
            padding-top: 8px;
            text-align: center;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    @php
        $transactions = collect($transactions ?? []);
        $summary = array_merge([
            'records' => 0,
            'amount' => 0,
            'liters' => 0,
            'no_receipt' => 0,
        ], $summary ?? []);
        $filters = array_merge([
            'date_label' => 'Today',
            'start' => now()->startOfDay(),
            'end' => now()->endOfDay(),
            'payment_label' => 'All Payments',
            'receipt_label' => 'All Receipts',
        ], $filters ?? []);
        $staff = $staff ?? auth()->user();

        $cleanText = function ($value, $fallback = 'N/A') {
            $value = trim((string) $value);

            return $value !== '' && strtoupper($value) !== 'N/A' ? $value : $fallback;
        };

        $paymentLabel = function ($type) {
            $type = strtolower(trim((string) $type));

            if ($type === 'credit') {
                return 'Credit';
            }

            if ($type === 'collection') {
                return 'Collection';
            }

            return 'Cash';
        };
    @endphp

    <button type="button" onclick="window.print()" class="no-print">Print Report</button>

    <header class="report-header">
        <div class="header-grid">
            <div class="header-left">
                <h1 class="company-name">JRM Fuel Station</h1>
                <p class="company-detail">Fuel Station Management System</p>
                <p class="company-detail">Transaction History Report</p>
            </div>
            <div class="header-right">
                <div class="report-title">Transaction Log</div>
                <div class="muted">Generated: {{ now()->format('F d, Y h:i A') }}</div>
            </div>
        </div>
    </header>

    <section class="meta-grid">
        <div class="meta-item">
            <p class="meta-label">Generated By</p>
            <p class="meta-value">{{ $staff->name ?? 'Staff' }}</p>
        </div>
        <div class="meta-item">
            <p class="meta-label">Reporting Period</p>
            <p class="meta-value">{{ $filters['start']->format('M d, Y') }} to {{ $filters['end']->format('M d, Y') }}</p>
        </div>
        <div class="meta-item">
            <p class="meta-label">Payment Filter</p>
            <p class="meta-value">{{ $filters['payment_label'] }}</p>
        </div>
        <div class="meta-item">
            <p class="meta-label">Receipt Filter</p>
            <p class="meta-value">{{ $filters['receipt_label'] }}</p>
        </div>
        <div class="meta-item">
            <p class="meta-label">Record Count</p>
            <p class="meta-value">{{ number_format((int) $summary['records']) }}</p>
        </div>
    </section>

    <table>
        <thead>
            <tr>
                <th width="13%">Date / Time</th>
                <th width="11%">Receipt</th>
                <th width="21%">Customer / Company</th>
                <th width="8%">Pump</th>
                <th width="12%">Product</th>
                <th width="9%" class="text-center">Payment</th>
                <th width="8%" class="text-right">Liters</th>
                <th width="8%" class="text-right">Price</th>
                <th width="10%" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $transaction)
                @php
                    $businessName = $cleanText($transaction->customer_business_name ?? '', '');
                    $customerName = $cleanText($transaction->customer_display_name ?? '', '');
                    $saleCustomerName = $cleanText($transaction->customer_name ?? '', '');
                    $customerName = $customerName !== '' ? $customerName : ($saleCustomerName !== '' ? $saleCustomerName : 'Walk-In Customer');
                    $businessName = $businessName !== '' ? $businessName : 'No company';
                    $paymentType = strtolower(trim((string) ($transaction->payment_type ?? '')));
                    $isCollection = $paymentType === 'collection';
                    $receipt = trim((string) ($transaction->or_number ?? ''));
                    $product = $isCollection ? 'Account Payment' : $cleanText($transaction->fuel_type ?? '', 'N/A');
                @endphp
                <tr>
                    <td>
                        <div class="font-bold">{{ $transaction->created_at ? \Carbon\Carbon::parse($transaction->created_at)->format('Y-m-d') : 'N/A' }}</div>
                        <div class="muted">{{ $transaction->created_at ? \Carbon\Carbon::parse($transaction->created_at)->format('h:i A') : '' }}</div>
                    </td>
                    <td class="mono">
                        @if ($receipt !== '')
                            {{ $receipt }}
                        @else
                            <span class="receipt-missing">No Receipt</span>
                        @endif
                    </td>
                    <td>
                        <div class="font-bold">{{ $customerName }}</div>
                        <div class="muted">{{ $businessName }}</div>
                    </td>
                    <td class="text-center">{{ $isCollection || empty($transaction->pump_id) ? 'N/A' : 'P-' . $transaction->pump_id }}</td>
                    <td>{{ $product }}</td>
                    <td class="text-center">{{ $paymentLabel($transaction->payment_type ?? '') }}</td>
                    <td class="text-right mono">{{ $isCollection ? '-' : number_format((float) ($transaction->liters ?? 0), 2) }}</td>
                    <td class="text-right mono">{{ $isCollection ? '-' : number_format((float) ($transaction->price_applied ?? 0), 2) }}</td>
                    <td class="text-right mono font-bold">{{ number_format((float) ($transaction->amount ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center muted" style="padding: 28px;">
                        No transaction records found for the current filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <section class="summary-wrap">
        <div class="summary">
            <div class="summary-row">
                <div class="summary-cell summary-label">Total Liters Dispensed</div>
                <div class="summary-cell summary-value">{{ number_format((float) $summary['liters'], 2) }} L</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell summary-label">Total Transaction Count</div>
                <div class="summary-cell summary-value">{{ number_format((int) $summary['records']) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell summary-label">No Receipt Count</div>
                <div class="summary-cell summary-value">{{ number_format((int) $summary['no_receipt']) }}</div>
            </div>
            <div class="summary-row grand-total">
                <div class="summary-cell summary-label">Total Sales</div>
                <div class="summary-cell summary-value">&#8369;{{ number_format((float) $summary['amount'], 2) }}</div>
            </div>
        </div>
    </section>

    <footer class="footer">
        This is a system-generated report from JRM Fuel Station Management System.
    </footer>
</body>
</html>
