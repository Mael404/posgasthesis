<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ledger - {{ $customer->business_name }}</title>
    <style>
        @page { margin: 1cm; size: A4 portrait; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #222; line-height: 1.35; margin: 0; }
        .no-print { display: none; }
        @media screen {
            body { padding: 40px; background: #f3f4f6; }
            .sheet { background: #fff; max-width: 210mm; min-height: 277mm; margin: 0 auto; padding: 1cm; box-shadow: 0 10px 30px rgba(0,0,0,.12); }
            .no-print { display: block; position: fixed; top: 20px; right: 20px; padding: 10px 18px; background: #15803d; color: #fff; border: 0; border-radius: 6px; font-weight: 700; cursor: pointer; }
        }
        .sheet { display: flex; flex-direction: column; min-height: 277mm; }
        .main-content { flex: 1; }
        .report-header { border-bottom: 2px solid #222; padding-bottom: 8px; margin-bottom: 14px; }
        .company-name { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; }
        .company-details { margin: 2px 0 0; color: #555; }
        .meta { display: flex; justify-content: space-between; gap: 20px; margin-bottom: 16px; }
        .label { display: block; margin-bottom: 3px; color: #777; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        h3 { margin: 0 0 2px; font-size: 14px; }
        p { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 6px 5px; vertical-align: top; }
        th { font-size: 9px; text-align: left; text-transform: uppercase; }
        .amount { text-align: right; font-family: "Courier New", monospace; font-weight: 700; }
        .title { margin: 10px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #ccc; font-size: 13px; font-weight: 800; text-transform: uppercase; }
        .summary { display: flex; justify-content: flex-end; margin-top: 12px; }
        .summary table { width: 310px; }
        .summary td { border: 0; border-bottom: 1px solid #ddd; padding: 5px; }
        .summary tr:last-child td { border-top: 2px solid #111; border-bottom: 0; font-size: 12px; }
        .bottom { margin-top: auto; padding-top: 24px; }
        .signatures { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .signature-box { width: 40%; text-align: center; }
        .sig-line { height: 24px; border-bottom: 1px solid #111; margin-bottom: 6px; }
        .instructions { border: 1px solid #111; padding: 8px 10px; font-size: 9px; }
        .footer { margin-top: 10px; border-top: 1px solid #ddd; padding-top: 8px; text-align: center; color: #666; font-size: 8px; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; padding: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print Ledger</button>

    <div class="sheet">
        <div class="main-content">
            <div class="report-header">
                <table style="border:0;">
                    <tr>
                        <td style="border:0; width:25%; padding:0 0 8px;">
                            <img src="{{ asset('images/logojrs.png') }}" alt="JRM Fuel Station" style="max-width:150px; height:auto;">
                        </td>
                        <td style="border:0; width:50%; padding:0; text-align:center;">
                            <h1 class="company-name">JRM Fuel Station</h1>
                            <p class="company-details">Sitio Apad, Calongay, Pilar Sorsogon</p>
                        </td>
                        <td style="border:0; width:25%; padding:0; text-align:right; vertical-align:top;">
                            <strong style="font-size:16px; text-transform:uppercase;">Customer Ledger</strong>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="meta">
                <div>
                    <span class="label">Billed To</span>
                    <h3>{{ $customer->business_name && strtoupper($customer->business_name) !== 'N/A' ? $customer->business_name : $customer->owner_name }}</h3>
                    <p><strong>Owner:</strong> {{ $customer->owner_name ?: 'N/A' }}</p>
                    <p><strong>Contact:</strong> {{ $customer->contact_number ?: 'N/A' }}</p>
                </div>
                <div>
                    <table style="border:0;">
                        <tr><td style="border:0;"><strong>Print Date:</strong></td><td style="border:0; text-align:right;">{{ now()->format('F d, Y') }}</td></tr>
                        <tr><td style="border:0;"><strong>Status:</strong></td><td style="border:0; text-align:right;">{{ strtoupper($customer->status ?? 'Active') }}</td></tr>
                        <tr><td style="border:0;"><strong>Account ID:</strong></td><td style="border:0; text-align:right;">CUST-{{ str_pad($customer->id, 4, '0', STR_PAD_LEFT) }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="title">Complete Transaction History</div>
            <table>
                <thead>
                    <tr>
                        <th width="12%">Date</th>
                        <th width="13%">Reference</th>
                        <th width="10%">OR #</th>
                        <th width="25%">Description</th>
                        <th width="10%" class="amount">Discount</th>
                        <th width="10%" class="amount">Charge</th>
                        <th width="10%" class="amount">Payment</th>
                        <th width="10%" class="amount">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filteredLedger as $entry)
                        @php
                            $referenceNo = trim((string) ($entry->reference_no ?? ''));
                            $orNumber = trim((string) ($entry->or_number ?? ''));
                            $isSystemReference = preg_match('/^(POS|PAY|VOID|EDIT|TXN)-[A-Z0-9-]+$/i', $referenceNo);
                            $isLegacySaleOr = $orNumber === '' && $referenceNo !== '' && ! $isSystemReference && (float) $entry->debit > 0 && $entry->type !== 'Payment';

                            if ($isLegacySaleOr) {
                                $orNumber = $referenceNo;
                                $referenceNo = '';
                            }
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($entry->transaction_date)->format('M d, Y') }}</td>
                            <td>{{ $referenceNo !== '' ? $referenceNo : 'N/A' }}</td>
                            <td>{{ $orNumber !== '' ? $orNumber : 'N/A' }}</td>
                            <td>{{ $entry->description }}</td>
                            <td class="amount">{{ (float) $entry->discount > 0 ? number_format((float) $entry->discount, 2) : '-' }}</td>
                            <td class="amount">{{ (float) $entry->debit > 0 ? number_format((float) $entry->debit, 2) : '-' }}</td>
                            <td class="amount">{{ (float) $entry->credit > 0 ? number_format((float) $entry->credit, 2) : '-' }}</td>
                            <td class="amount">{{ number_format((float) $entry->print_running_balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="padding:20px; text-align:center; color:#777;">No transaction history available.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="summary">
                <table>
                    <tr><td><strong>Overall Charges (+)</strong></td><td class="amount">{{ number_format($cleanTotalDebits, 2) }}</td></tr>
                    <tr><td><strong>Overall Payments (-)</strong></td><td class="amount">({{ number_format($cleanTotalCredits, 2) }})</td></tr>
                    @if ($totalDiscount > 0)
                        <tr><td><strong>Overall Savings</strong></td><td class="amount">&#8369;{{ number_format($totalDiscount, 2) }}</td></tr>
                    @endif
                    <tr><td><strong>Current Outstanding Balance</strong></td><td class="amount">&#8369;{{ number_format($currentBalance, 2) }}</td></tr>
                </table>
            </div>
        </div>

        <div class="bottom">
            <div class="signatures">
                <div class="signature-box"><div class="sig-line"></div><strong>Prepared By</strong></div>
                <div class="signature-box"><div class="sig-line"></div><strong>Confirmed By</strong></div>
            </div>
            <div class="instructions">
                <p><strong>Note:</strong> This document serves as the complete transaction history for the stated account. Please notify us immediately for any discrepancy.</p>
                <p>Kindly settle your account via cash, bank transfer, or check payable to <strong>JRM Fuel Station</strong>.</p>
            </div>
            <div class="footer">System Generated Ledger Report | JRM Fuel Station Management System | {{ now()->year }}</div>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>
