<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statement of Account - {{ $customer->business_name }}</title>
    @include('layouts.browser-icon')
    <style>
        @page { margin: 1cm; size: A4 portrait; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #222; margin: 0; line-height: 1.35; }
        .no-print { display: none; }
        @media screen {
            body { padding: 40px; background: #f3f4f6; }
            .sheet { background: #fff; max-width: 210mm; min-height: 277mm; margin: 0 auto; padding: 1cm; box-shadow: 0 10px 30px rgba(0,0,0,.12); }
            .no-print { display: block; position: fixed; top: 20px; right: 20px; padding: 10px 18px; background: #15803d; color: #fff; border: 0; border-radius: 6px; font-weight: 700; cursor: pointer; }
        }
        .sheet { min-height: 277mm; display: flex; flex-direction: column; }
        .main-content { flex: 1; }
        .header { border-bottom: 2px solid #222; padding-bottom: 8px; margin-bottom: 14px; }
        .company { margin: 0; font-size: 20px; font-weight: 800; text-transform: uppercase; }
        .muted { color: #555; }
        .meta { display: flex; justify-content: space-between; gap: 20px; margin-bottom: 16px; }
        .label { display: block; margin-bottom: 3px; color: #777; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 6px 5px; vertical-align: top; }
        th { font-size: 9px; text-align: left; text-transform: uppercase; }
        .amount { text-align: right; font-family: "Courier New", monospace; font-weight: 700; }
        .summary { display: flex; justify-content: flex-end; margin-top: 12px; }
        .summary table { width: 330px; }
        .summary td { border: 0; border-bottom: 1px solid #ddd; }
        .summary tr:last-child td { border-top: 2px solid #111; border-bottom: 0; font-size: 12px; }
        .footer { margin-top: auto; padding-top: 18px; border-top: 1px solid #ddd; color: #666; font-size: 8px; text-align: center; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; padding: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print SOA</button>

    <div class="sheet">
        <div class="main-content">
            <div class="header">
                <table style="border:0;">
                    <tr>
                        <td style="border:0; width:25%; padding:0 0 8px;">
                            <img src="{{ asset('images/logojrs.png') }}" alt="JRM Fuel Station" style="max-width:150px; height:auto;">
                        </td>
                        <td style="border:0; width:50%; padding:0; text-align:center;">
                            <h1 class="company">JRM Fuel Station</h1>
                            <p class="muted" style="margin:2px 0 0;">Sitio Apad, Calongay, Pilar Sorsogon</p>
                        </td>
                        <td style="border:0; width:25%; padding:0; text-align:right; vertical-align:top;">
                            <strong style="font-size:16px; text-transform:uppercase;">Statement of Account</strong>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="meta">
                <div>
                    <span class="label">Account</span>
                    <h3 style="margin:0 0 2px;">{{ $customer->business_name && strtoupper($customer->business_name) !== 'N/A' ? $customer->business_name : $customer->owner_name }}</h3>
                    <p><strong>Owner:</strong> {{ $customer->owner_name ?: 'N/A' }}</p>
                    <p><strong>Contact:</strong> {{ $customer->contact_number ?: 'N/A' }}</p>
                </div>
                <div>
                    <table style="border:0;">
                        <tr><td style="border:0;"><strong>Period:</strong></td><td style="border:0; text-align:right;">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</td></tr>
                        <tr><td style="border:0;"><strong>Print Date:</strong></td><td style="border:0; text-align:right;">{{ now()->format('F d, Y') }}</td></tr>
                        <tr><td style="border:0;"><strong>Account ID:</strong></td><td style="border:0; text-align:right;">CUST-{{ str_pad($customer->id, 4, '0', STR_PAD_LEFT) }}</td></tr>
                    </table>
                </div>
            </div>

            @php
                $runningBalance = $previousBalance;
                $periodDebit = 0;
                $periodCredit = 0;
            @endphp

            <table>
                <thead>
                    <tr>
                        <th width="13%">Date</th>
                        <th width="12%">Type</th>
                        <th width="15%">Reference</th>
                        <th width="30%">Description</th>
                        <th width="10%" class="amount">Charge</th>
                        <th width="10%" class="amount">Payment</th>
                        <th width="10%" class="amount">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6"><strong>Previous Balance</strong></td>
                        <td class="amount">{{ number_format($previousBalance, 2) }}</td>
                    </tr>
                    @forelse ($transactions as $entry)
                        @php
                            $periodDebit += (float) ($entry->debit ?? 0);
                            $periodCredit += (float) ($entry->credit ?? 0);
                            $runningBalance += (float) ($entry->debit ?? 0) - (float) ($entry->credit ?? 0);
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($entry->transaction_date)->format('M d, Y') }}</td>
                            <td>{{ $entry->type }}</td>
                            <td>{{ $entry->reference_no ?: 'N/A' }}</td>
                            <td>{{ $entry->description ?: '-' }}</td>
                            <td class="amount">{{ (float) $entry->debit > 0 ? number_format((float) $entry->debit, 2) : '-' }}</td>
                            <td class="amount">{{ (float) $entry->credit > 0 ? number_format((float) $entry->credit, 2) : '-' }}</td>
                            <td class="amount">{{ number_format($runningBalance, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="padding:20px; text-align:center; color:#777;">No transactions found for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="summary">
                <table>
                    <tr><td><strong>Previous Balance</strong></td><td class="amount">{{ number_format($previousBalance, 2) }}</td></tr>
                    <tr><td><strong>Period Charges</strong></td><td class="amount">{{ number_format($periodDebit, 2) }}</td></tr>
                    <tr><td><strong>Period Payments</strong></td><td class="amount">({{ number_format($periodCredit, 2) }})</td></tr>
                    @if ($totalDiscount > 0)
                        <tr><td><strong>Period Discounts</strong></td><td class="amount">&#8369;{{ number_format($totalDiscount, 2) }}</td></tr>
                    @endif
                    <tr><td><strong>Amount Due</strong></td><td class="amount">&#8369;{{ number_format($runningBalance, 2) }}</td></tr>
                </table>
            </div>
        </div>

        <div class="footer">System Generated Statement of Account | JRM Fuel Station Management System | {{ now()->year }}</div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>
