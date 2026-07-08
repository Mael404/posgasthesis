@php
    $documentTitle = $isPaidInFullReceipt ? 'Receipt' : 'Statement of Account';
    $customerName = trim((string) ($customer->name ?? 'Customer'));
    $customerName = $customerName !== '' ? $customerName : 'Customer';
    $batchNo = trim((string) ($printedBatch->batch_no ?? ''));
    $batchNo = $batchNo !== '' ? $batchNo : 'BATCH-' . str_pad((string) ($printedBatch->id ?? 0), 4, '0', STR_PAD_LEFT);
    $printedDate = $printedAt instanceof \Carbon\Carbon
        ? $printedAt
        : \Carbon\Carbon::parse($printedAt ?? now());
    $recordCount = $transactions->count();
    $totalLabel = $isPaidInFullReceipt ? 'TOTAL AMOUNT PAID' : 'TOTAL AMOUNT DUE';
    $amountHeader = $isPaidInFullReceipt ? 'Amount' : 'Charges (+)';
    $summaryChargeLabel = $isPaidInFullReceipt ? 'Fuel Sales' : 'Current Fuel Charges (+)';
    $preparedByValue = $printSignatories->prepared_by ?? '';
    $acknowledgedByValue = $printSignatories->acknowledged_by ?? '';
    $signedAtValue = \Carbon\Carbon::parse($signatorySignedAt ?? now());

    $formatQuantity = function ($value) {
        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    };

    $isCollection = function ($sale) {
        return strtolower(trim((string) ($sale->payment_type ?? ''))) === 'collection';
    };

    $referenceFor = function ($sale) use ($staff) {
        $reference = trim((string) ($sale->or_number ?? ''));
        $reference = $reference !== '' ? $reference : trim((string) ($sale->reference_no ?? ''));
        $reference = $reference !== '' ? $reference : 'TXN-' . ($sale->id ?? '?');
        $staffName = trim((string) ($sale->staff_name ?? $staff->name ?? ''));

        return $staffName !== '' ? $reference . ' / ' . strtoupper($staffName) : $reference;
    };

    $descriptionFor = function ($sale) use ($formatQuantity, $isCollection) {
        if ($isCollection($sale)) {
            $invoice = ! empty($sale->invoice_id) ? ' for Invoice #' . $sale->invoice_id : '';

            return 'Payment / Collection' . $invoice;
        }

        $paymentType = strtolower(trim((string) ($sale->payment_type ?? '')));
        $prefix = $paymentType === 'credit' ? 'Credit Charge' : 'Fuel Sale';
        $fuelType = trim((string) ($sale->fuel_type ?? 'Fuel'));
        $fuelType = $fuelType !== '' ? $fuelType : 'Fuel';
        $liters = (float) ($sale->liters ?? 0);
        $pumpId = trim((string) ($sale->pump_id ?? ''));
        $description = $prefix . ': ' . $fuelType;

        if ($liters > 0) {
            $description .= ' - ' . $formatQuantity($liters) . 'L';
        }

        if ($pumpId !== '') {
            $description .= ' (Pump ' . str_pad($pumpId, 2, '0', STR_PAD_LEFT) . ')';
        }

        return $description;
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $batchNo }} - {{ $documentTitle }}</title>
    @include('layouts.browser-icon')
    <style>
        @page { margin: 1cm; size: A4 portrait; }
        body {
            margin: 0;
            color: #222;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        p { margin: 0; }
        @media screen {
            body { background: #eef1f5; padding: 40px; }
            .sheet {
                background: #fff;
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
                margin: 0 auto;
                max-width: 210mm;
                min-height: 277mm;
                padding: 1cm;
            }
            .no-print {
                position: fixed;
                right: 20px;
                top: 20px;
                width: 340px;
                z-index: 50;
            }
        }
        @media print {
            body { background: #fff; }
            .sheet { box-shadow: none; margin: 0; max-width: none; min-height: 100vh; padding: 0; }
            .no-print { display: none !important; }
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .toolbar button, .signatory-actions button {
            background: #039855;
            border: 0;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
            padding: 9px 12px;
        }
        .toolbar .primary, .signatory-actions .save-btn { background: #465fff; }
        .toolbar .success, .signatory-actions .print-btn { background: #039855; }
        .toolbar button:disabled, .signatory-actions button:disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }
        .signatory-panel {
            background: #fff;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14);
            padding: 14px;
        }
        .signatory-panel-title {
            color: #101828;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .signatory-field { margin-bottom: 10px; }
        .signatory-field label {
            color: #667085;
            display: block;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .signatory-field input {
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 12px;
            padding: 8px 9px;
            width: 100%;
        }
        .signatory-field input[readonly] {
            background: #f2f4f7;
            color: #667085;
        }
        .signatory-actions {
            display: flex;
            gap: 8px;
        }
        .signatory-actions button { flex: 1; }
        .signatory-status {
            color: #667085;
            font-size: 12px;
            margin-top: 9px;
            min-height: 15px;
        }
        .signatory-status.error { color: #b42318; }
        .signatory-status.success { color: #027a48; }
        .sheet {
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            min-height: 277mm;
        }
        .main-content { flex: 1; }
        .report-header {
            border-bottom: 3px solid #465fff;
            margin-bottom: 15px;
            padding-bottom: 10px;
        }
        .header-table, .meta-table, .data-table, .summary-table {
            border-collapse: collapse;
            width: 100%;
        }
        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }
        .logo {
            height: auto;
            max-width: 130px;
        }
        .company-name {
            color: #465fff;
            font-size: 22px;
            font-weight: 900;
            margin: 0;
        }
        .company-details {
            color: #475467;
            margin: 2px 0 0;
        }
        .statement-title {
            color: #98a2b3;
            font-size: 18px;
            font-weight: 900;
            text-align: right;
            text-transform: uppercase;
        }
        .statement-number {
            color: #d92d20;
            font-size: 14px;
            font-weight: 900;
            margin-top: 5px;
            text-align: right;
        }
        .meta-container {
            align-items: flex-start;
            background: #f8f9fc;
            border: 1px solid #e4e7ec;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 12px 15px;
        }
        .bill-to-label {
            color: #667085;
            display: block;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .bill-to h3 {
            color: #101828;
            font-size: 15px;
            font-weight: 900;
            margin: 0 0 3px;
            text-transform: uppercase;
        }
        .bill-to p {
            color: #475467;
            margin: 0;
        }
        .meta-table td {
            border: 0;
            font-size: 11px;
            padding: 2px 8px;
        }
        .meta-table td:first-child {
            color: #667085;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }
        .meta-table td:last-child {
            color: #101828;
            font-weight: 700;
        }
        .table-title {
            color: #465fff;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .data-table th, .data-table td {
            border: 1px solid #d0d5dd;
            padding: 6px 8px;
            vertical-align: top;
        }
        .data-table th {
            background: #f2f4f7;
            color: #344054;
            font-size: 10px;
            font-weight: 800;
            text-align: left;
            text-transform: uppercase;
        }
        .amount-col {
            font-family: "Courier New", monospace;
            font-weight: 700;
            text-align: right !important;
            white-space: nowrap;
        }
        .summary-box {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
            page-break-inside: avoid;
        }
        .summary-table { width: 340px; }
        .summary-table td {
            border: 0;
            border-bottom: 1px solid #e4e7ec;
            font-size: 11px;
            padding: 5px 8px;
        }
        .summary-table td:first-child {
            color: #475467;
            font-weight: 700;
        }
        .grand-total { background: #465fff; }
        .grand-total td {
            border: 0 !important;
            color: #fff !important;
            font-size: 13px !important;
            font-weight: 900 !important;
            padding: 8px !important;
        }
        .bottom-section {
            margin-top: auto;
            padding-top: 28px;
            page-break-inside: avoid;
        }
        .instructions {
            background: #f8f9fc;
            border-left: 4px solid #465fff;
            color: #475467;
            font-size: 10px;
            margin-bottom: 28px;
            padding: 10px 15px;
        }
        .instructions p { margin: 4px 0; }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .signature-box {
            text-align: center;
            width: 40%;
        }
        .sig-line {
            align-items: flex-end;
            border-bottom: 1px solid #101828;
            box-sizing: border-box;
            display: flex;
            height: 25px;
            justify-content: center;
            margin-bottom: 5px;
            padding: 0 4px 3px;
        }
        .signature-name {
            display: block;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }
        .sig-label {
            color: #344054;
            font-size: 11px;
            font-weight: 700;
        }
        .footer {
            border-top: 1px solid #e4e7ec;
            color: #98a2b3;
            font-size: 9px;
            margin-top: 24px;
            padding-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div class="toolbar">
            <button type="button" class="primary" onclick="window.print()">Print Copy</button>
        </div>
        <div class="signatory-panel">
            <div class="signatory-panel-title">Printed Transaction Signatories</div>
            <div class="signatory-field">
                <label for="prepared_by_input">Prepared By</label>
                <input type="text" id="prepared_by_input" value="{{ $preparedByValue }}" placeholder="JR-M Fuel Station representative">
            </div>
            <div class="signatory-field">
                <label for="acknowledged_by_input">Received / Acknowledged By</label>
                <input type="text" id="acknowledged_by_input" value="{{ $acknowledgedByValue }}" placeholder="Customer / Representative">
            </div>
            <div class="signatory-field">
                <label for="signed_at_input">Date &amp; Time</label>
                <input type="datetime-local" id="signed_at_input" value="{{ $signedAtValue->format('Y-m-d\TH:i') }}" readonly>
            </div>
            <div class="signatory-actions">
                <button type="button" class="save-btn" id="saveSignatoriesBtn">Save</button>
                <button type="button" class="print-btn" id="savePrintSignatoriesBtn">Save &amp; Print</button>
            </div>
            <div class="signatory-status" id="signatoryStatus"></div>
        </div>
    </div>

    <main class="sheet">
        <div class="main-content">
            <header class="report-header">
                <table class="header-table">
                    <tr>
                        <td style="width: 20%;">
                            <img src="{{ asset('images/logojrs.png') }}" alt="JR-M Fuel Station Logo" class="logo">
                        </td>
                        <td style="width: 45%;">
                            <h1 class="company-name">JR-M FUEL STATION</h1>
                            <p class="company-details">Sitio Apad, Calongay, Pilar Sorsogon</p>
                        </td>
                        <td style="width: 35%;">
                            <div class="statement-title">{{ $documentTitle }}</div>
                            <div class="statement-number">{{ $batchNo }}</div>
                        </td>
                    </tr>
                </table>
            </header>

            <section class="meta-container">
                <div class="bill-to">
                    <span class="bill-to-label">Billed To</span>
                    <h3>{{ $customerName }}</h3>
                    <p>
                        {{ $customer->subtitle ?? '' }}
                        @if (! empty($customer->contact))
                            @if (! empty($customer->subtitle))
                                |
                            @endif
                            {{ $customer->contact }}
                        @endif
                    </p>
                </div>
                <div>
                    <table class="meta-table">
                        <tr>
                            <td>Date Issued:</td>
                            <td>{{ $printedDate->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <td>Selected Records:</td>
                            <td>{{ $recordCount }}</td>
                        </tr>
                    </table>
                </div>
            </section>

            <div class="table-title">Transaction Breakdown</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="15%">Date</th>
                        <th width="20%">Ref / OR #</th>
                        <th width="47%">Description</th>
                        <th width="18%" class="amount-col">{{ $amountHeader }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $sale)
                        <tr>
                            <td>{{ ! empty($sale->created_at) ? \Carbon\Carbon::parse($sale->created_at)->format('m/d/Y') : 'N/A' }}</td>
                            <td>{{ $referenceFor($sale) }}</td>
                            <td>{{ $descriptionFor($sale) }}</td>
                            <td class="amount-col">{{ number_format((float) ($sale->amount ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding: 15px; text-align: center; color: #667085;">No transactions found for this batch.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="summary-box">
                <table class="summary-table">
                    @if ((float) $totals->fuel_amount > 0)
                        <tr>
                            <td>{{ $summaryChargeLabel }}</td>
                            <td class="amount-col">{{ number_format((float) $totals->fuel_amount, 2) }}</td>
                        </tr>
                    @endif
                    @if ((float) $totals->collection_amount > 0)
                        <tr>
                            <td>Collections / Payments</td>
                            <td class="amount-col">{{ number_format((float) $totals->collection_amount, 2) }}</td>
                        </tr>
                    @endif
                    @if ((float) $totals->discounts > 0)
                        <tr>
                            <td style="color: #d92d20;">Total Savings Applied</td>
                            <td class="amount-col" style="color: #d92d20;">- {{ number_format((float) $totals->discounts, 2) }}</td>
                        </tr>
                    @endif
                    @if ((float) $totals->surcharge > 0)
                        <tr>
                            <td>Excess Markup (+)</td>
                            <td class="amount-col">{{ number_format((float) $totals->surcharge, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="grand-total">
                        <td>{{ $totalLabel }}</td>
                        <td class="amount-col">PHP {{ number_format((float) $totals->amount, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <section class="bottom-section">
            <div class="instructions">
                <p><strong>Note:</strong> All transactions stated above are acknowledged as true and correct. Please notify us immediately if there are discrepancies.</p>
                <p>Payment is expected promptly upon receipt of this statement. Failure to settle may result in account restrictions.</p>
            </div>

            <div class="signatures">
                <div class="signature-box">
                    <p class="sig-line"><span class="signature-name" id="print_prepared_by">{{ $preparedByValue }}</span></p>
                    <p class="sig-label">Prepared By (JR-M Fuel Station)</p>
                </div>
                <div class="signature-box">
                    <p class="sig-line"><span class="signature-name" id="print_acknowledged_by">{{ $acknowledgedByValue }}</span></p>
                    <p class="sig-label">Received / Acknowledged By</p>
                </div>
            </div>

            <div class="footer">
                <p>{{ $documentTitle }} Generated via JR-M Fuel Station Management System &copy; {{ date('Y') }}</p>
            </div>
        </section>
    </main>

    <script>
        const signatorySaveUrl = @json($signatorySaveUrl);
        const signatoryCsrfToken = @json(csrf_token());
        const saveSignatoriesButton = document.getElementById('saveSignatoriesBtn');
        const savePrintSignatoriesButton = document.getElementById('savePrintSignatoriesBtn');
        const signatoryStatus = document.getElementById('signatoryStatus');
        const preparedByInput = document.getElementById('prepared_by_input');
        const acknowledgedByInput = document.getElementById('acknowledged_by_input');
        const signedAtInput = document.getElementById('signed_at_input');
        const printPreparedBy = document.getElementById('print_prepared_by');
        const printAcknowledgedBy = document.getElementById('print_acknowledged_by');

        function setSignatoryStatus(message, type) {
            signatoryStatus.textContent = message || '';
            signatoryStatus.className = 'signatory-status' + (type ? ' ' + type : '');
        }

        function setSignatoryButtons(disabled) {
            saveSignatoriesButton.disabled = disabled;
            savePrintSignatoriesButton.disabled = disabled;
        }

        function saveSignatories(shouldPrint) {
            setSignatoryButtons(true);
            setSignatoryStatus('Saving printed transaction signatories...', '');

            return fetch(signatorySaveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': signatoryCsrfToken
                },
                body: JSON.stringify({
                    prepared_by: preparedByInput.value,
                    acknowledged_by: acknowledgedByInput.value
                })
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then((result) => {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.message || 'Unable to save signatories.');
                    }

                    printPreparedBy.textContent = result.data.prepared_by || '';
                    printAcknowledgedBy.textContent = result.data.acknowledged_by || '';
                    signedAtInput.value = result.data.signed_at_input || signedAtInput.value;
                    setSignatoryStatus(result.data.message || 'Saved.', 'success');

                    if (shouldPrint) {
                        window.print();
                    }
                })
                .catch((error) => {
                    setSignatoryStatus(error.message || 'Unable to save signatories.', 'error');
                })
                .finally(() => {
                    setSignatoryButtons(false);
                });
        }

        saveSignatoriesButton.addEventListener('click', () => saveSignatories(false));
        savePrintSignatoriesButton.addEventListener('click', () => saveSignatories(true));
    </script>
</body>
</html>
