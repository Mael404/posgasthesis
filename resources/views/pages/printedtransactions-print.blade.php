@php
    $documentTitle = $isPaidInFullReceipt ? 'Receipt' : 'Statement of Account';
    $customerName = trim((string) ($customer->name ?? 'Customer'));
    $batchNo = $printedBatch->batch_no ?: 'Batch #' . $printedBatch->id;
    $referenceFor = function ($sale) {
        $orNumber = trim((string) ($sale->or_number ?? ''));
        $reference = trim((string) ($sale->reference_no ?? ''));

        return $orNumber !== '' ? $orNumber : ($reference !== '' ? $reference : 'Sale #' . ($sale->id ?? '?'));
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentTitle }} - {{ $customerName }}</title>
    <style>
        @page { margin: 1cm; size: A4 portrait; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #222; margin: 0; line-height: 1.35; }
        p { margin: 0; }
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
        .meta > div { width: 50%; }
        .label { display: block; margin-bottom: 3px; color: #777; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 6px 5px; vertical-align: top; }
        th { font-size: 9px; text-align: left; text-transform: uppercase; }
        .amount { text-align: right; font-family: "Courier New", monospace; font-weight: 700; }
        .summary { display: flex; justify-content: flex-end; margin-top: 12px; }
        .summary table { width: 340px; }
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
    <button class="no-print" onclick="window.print()">Print Copy</button>

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
                            <strong style="font-size:16px; text-transform:uppercase;">{{ $documentTitle }}</strong>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="meta">
                <div>
                    <span class="label">Customer</span>
                    <h3 style="margin:0 0 2px;">{{ $customerName ?: 'N/A' }}</h3>
                    @if (! empty($customer->subtitle))
                        <p><strong>Account:</strong> {{ $customer->subtitle }}</p>
                    @endif
                    @if (! empty($customer->contact))
                        <p><strong>Contact:</strong> {{ $customer->contact }}</p>
                    @endif
                </div>
                <div>
                    <table style="border:0;">
                        <tr><td style="border:0;"><strong>Batch:</strong></td><td style="border:0; text-align:right;">{{ $batchNo }}</td></tr>
                        <tr><td style="border:0;"><strong>Printed:</strong></td><td style="border:0; text-align:right;">{{ $printedAt->format('M d, Y h:i A') }}</td></tr>
                        <tr><td style="border:0;"><strong>Staff:</strong></td><td style="border:0; text-align:right;">{{ $staff->name ?? 'Staff' }}</td></tr>
                    </table>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th width="13%">Date</th>
                        <th width="13%">Reference</th>
                        <th width="12%">Type</th>
                        <th width="12%">Fuel</th>
                        <th width="12%" class="amount">Liters</th>
                        <th width="12%" class="amount">Price</th>
                        <th width="13%" class="amount">Discount</th>
                        <th width="13%" class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $sale)
                        @php
                            $discount = (float) ($sale->discount ?? 0) + (float) ($sale->manual_discount ?? 0);
                        @endphp
                        <tr>
                            <td>{{ ! empty($sale->created_at) ? \Carbon\Carbon::parse($sale->created_at)->format('M d, Y') : 'N/A' }}</td>
                            <td>{{ $referenceFor($sale) }}</td>
                            <td>{{ $sale->payment_type ?: 'N/A' }}</td>
                            <td>{{ $sale->fuel_type ?: '-' }}</td>
                            <td class="amount">{{ (float) ($sale->liters ?? 0) > 0 ? number_format((float) $sale->liters, 2) : '-' }}</td>
                            <td class="amount">{{ (float) ($sale->price_applied ?? 0) > 0 ? number_format((float) $sale->price_applied, 2) : '-' }}</td>
                            <td class="amount">{{ $discount > 0 ? number_format($discount, 2) : '-' }}</td>
                            <td class="amount">{{ number_format((float) ($sale->amount ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="padding:20px; text-align:center; color:#777;">No transactions found for this batch.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="summary">
                <table>
                    <tr><td><strong>Fuel Amount</strong></td><td class="amount">{{ number_format((float) $totals->fuel_amount, 2) }}</td></tr>
                    @if ((float) $totals->collection_amount > 0)
                        <tr><td><strong>Collections</strong></td><td class="amount">{{ number_format((float) $totals->collection_amount, 2) }}</td></tr>
                    @endif
                    <tr><td><strong>Liters</strong></td><td class="amount">{{ number_format((float) $totals->liters, 2) }}</td></tr>
                    @if ((float) $totals->discounts > 0)
                        <tr><td><strong>Discounts</strong></td><td class="amount">{{ number_format((float) $totals->discounts, 2) }}</td></tr>
                    @endif
                    @if ((float) $totals->surcharge > 0)
                        <tr><td><strong>Surcharge</strong></td><td class="amount">{{ number_format((float) $totals->surcharge, 2) }}</td></tr>
                    @endif
                    <tr><td><strong>Total Amount</strong></td><td class="amount">&#8369;{{ number_format((float) $totals->amount, 2) }}</td></tr>
                </table>
            </div>
        </div>

        <div class="footer">System Generated {{ $documentTitle }} | JRM Fuel Station Management System | {{ now()->year }}</div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>
