<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }} - Billing Statement</title>
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
            .no-print, .edit-input, .screen-actions { display: none !important; }
            .display-value { display: inline !important; }
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .toolbar button, .signatory-actions button {
            border: 0;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
            padding: 9px 12px;
        }
        .toolbar .primary, .signatory-actions .save-btn { background: #465fff; }
        .toolbar .secondary { background: #667085; }
        .toolbar .success, .signatory-actions .print-btn { background: #039855; }
        .toolbar button:disabled, .signatory-actions button:disabled { cursor: not-allowed; opacity: 0.7; }
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
        .signatory-field input[readonly] { background: #f2f4f7; color: #667085; }
        .signatory-actions { display: flex; gap: 8px; }
        .signatory-actions button { flex: 1; }
        .signatory-status { color: #667085; font-size: 12px; margin-top: 9px; min-height: 15px; }
        .signatory-status.error { color: #b42318; }
        .signatory-status.success { color: #027a48; }
        .report-header {
            border-bottom: 3px solid #465fff;
            margin-bottom: 15px;
            padding-bottom: 10px;
        }
        .header-table, .meta-table, .data-table, .summary-table { border-collapse: collapse; width: 100%; }
        .header-table td { border: 0; padding: 0; vertical-align: middle; }
        .logo { height: auto; max-width: 130px; }
        .company-name {
            color: #465fff;
            font-size: 22px;
            font-weight: 900;
            margin: 0;
        }
        .company-details { color: #475467; margin: 2px 0 0; }
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
        .status-stamp {
            color: rgba(3, 152, 85, 0.14);
            font-size: 58px;
            font-weight: 900;
            left: 28%;
            pointer-events: none;
            position: absolute;
            top: 30%;
            transform: rotate(-30deg);
        }
        .meta-container {
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
        .bill-to p { color: #475467; margin: 0; }
        .meta-table td {
            border: 0;
            font-size: 11px;
            padding: 2px 8px;
        }
        .meta-table td:first-child { color: #667085; font-weight: 700; text-align: right; }
        .meta-table td:last-child { color: #101828; font-weight: 700; }
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
        .text-center { text-align: center; }
        .edit-input {
            display: none;
            border: 1px solid #98a2b3;
            border-radius: 4px;
            box-sizing: border-box;
            font: inherit;
            padding: 5px 6px;
            width: 100%;
        }
        textarea.edit-input { min-height: 42px; resize: vertical; }
        .amount-col .edit-input { text-align: right; }
        .screen-actions { display: none; text-align: center; }
        .delete-row-btn {
            background: #d92d20;
            border: 0;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            font-size: 10px;
            font-weight: 800;
            padding: 5px 8px;
        }
        body.is-editing .editable-ledger-row { background: #fffbeb; }
        body.is-editing .screen-actions { display: table-cell; }
        body.is-editing .display-value { display: none; }
        body.is-editing .edit-input { display: block; }
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
        .summary-table td:first-child { color: #475467; font-weight: 700; }
        .grand-total { background: #465fff; }
        .grand-total td, .advance-total td {
            border: 0 !important;
            color: #fff !important;
            font-size: 13px !important;
            font-weight: 900 !important;
            padding: 8px !important;
        }
        .advance-total { background: #039855; }
        .bottom-section { margin-top: 28px; page-break-inside: avoid; }
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
        .signature-box { text-align: center; width: 40%; }
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
    @php
        $canEditInvoice = auth()->check()
            && auth()->user()->role !== 'semiadmin'
            && $invoice->status !== 'Rolled Over';
        $preparedByValue = $printSignatories->prepared_by ?? '';
        $acknowledgedByValue = $printSignatories->acknowledged_by ?? '';
        $signedAtValue = \Carbon\Carbon::parse($signatorySignedAt ?? now());
        $displayCustomer = ! empty($customer->business_name) && strtoupper((string) $customer->business_name) !== 'N/A'
            ? $customer->business_name
            : ($customer->owner_name ?? 'Customer #' . $customer->id);
        $chargeTransactions = $filteredTransactions->filter(fn ($row) => (float) ($row->debit ?? 0) > 0
            && ! in_array($row->type ?? null, ['Payment', 'Void Payment'], true));
        $totalSavingsApplied = $chargeTransactions->sum(fn ($row) => (float) ($row->discount_amount ?? $row->discount ?? 0));
        $hasDiscountColumn = $totalSavingsApplied > 0;
        $displayPaymentsReceived = $invoicePaymentsReceived ?? $invoice->payments_received;
        $remainingBalance = (float) $invoice->total_amount_due - (float) $displayPaymentsReceived;
        $editedSaleIds = $chargeTransactions
            ->map(function ($row) {
                $referenceNo = trim((string) ($row->reference_no ?? ''));

                return preg_match('/^EDIT-(\d+)$/i', $referenceNo, $matches)
                    ? (int) $matches[1]
                    : null;
            })
            ->filter()
            ->unique()
            ->values();
        $chargeSaleLookupKeys = $chargeTransactions
            ->flatMap(function ($row) {
                return [
                    trim((string) ($row->reference_no ?? '')),
                    trim((string) ($row->or_number ?? '')),
                ];
            })
            ->filter()
            ->unique()
            ->values();
        $salesOrNumbersByEditedId = [];
        $salesOrNumbersByLookupKey = [];

        if ($editedSaleIds->isNotEmpty() || $chargeSaleLookupKeys->isNotEmpty()) {
            $matchingSales = \Illuminate\Support\Facades\DB::table('sales')
                ->select('id', 'reference_no', 'or_number')
                ->where(function ($query) use ($invoice) {
                    $query->where('customer_id', $invoice->customer_id)
                        ->orWhere('posted_customer_id', $invoice->customer_id);
                })
                ->where(function ($query) use ($editedSaleIds, $chargeSaleLookupKeys) {
                    if ($editedSaleIds->isNotEmpty()) {
                        $query->whereIn('id', $editedSaleIds);
                    }

                    if ($chargeSaleLookupKeys->isNotEmpty()) {
                        $query->orWhere(function ($query) use ($chargeSaleLookupKeys) {
                            $query->whereIn('reference_no', $chargeSaleLookupKeys)
                                ->orWhereIn('or_number', $chargeSaleLookupKeys);
                        });
                    }
                })
                ->get();

            foreach ($matchingSales as $saleRow) {
                $saleOrNumber = trim((string) ($saleRow->or_number ?? ''));

                if ($saleOrNumber === '') {
                    continue;
                }

                $salesOrNumbersByEditedId[(int) $saleRow->id] = $saleOrNumber;

                $saleReferenceNo = trim((string) ($saleRow->reference_no ?? ''));

                if ($saleReferenceNo !== '') {
                    $salesOrNumbersByLookupKey[$saleReferenceNo] = $saleOrNumber;
                }

                $salesOrNumbersByLookupKey[$saleOrNumber] = $saleOrNumber;
            }
        }
    @endphp

    <div class="no-print">
        <div class="toolbar">
            <button type="button" class="primary" onclick="window.print()">Print Invoice</button>
            @if ($canEditInvoice)
                <button type="button" class="secondary" id="editBillingBtn" onclick="enableBillingEdit()">Edit Rows</button>
                <button type="button" class="success" id="saveBillingBtn" onclick="saveBillingEdits()" style="display: none;">Save Changes</button>
                <button type="button" class="secondary" id="cancelBillingBtn" onclick="cancelBillingEdit()" style="display: none;">Cancel</button>
            @endif
        </div>
        <div class="signatory-panel">
            <div class="signatory-panel-title">Invoice Signatories</div>
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
        <div style="position: relative;">
            @if ($invoice->status === 'Paid')
                <div class="status-stamp">PAID IN FULL</div>
            @endif

            <header class="report-header">
                <table class="header-table">
                    <tr>
                        <td style="width: 20%;">
                            <img src="{{ asset('img/logojrs.png') }}" alt="JR-M Fuel Station Logo" class="logo">
                        </td>
                        <td style="width: 45%;">
                            <h1 class="company-name">JR-M FUEL STATION</h1>
                            <p class="company-details">Sitio Apad, Calongay, Pilar Sorsogon</p>
                        </td>
                        <td style="width: 35%;">
                            <div class="statement-title">Statement of Account</div>
                            <div class="statement-number">{{ $invoice->invoice_number }}</div>
                        </td>
                    </tr>
                </table>
            </header>

            <section class="meta-container">
                <div class="bill-to">
                    <span class="bill-to-label">Billed To</span>
                    <h3>{{ $displayCustomer }}</h3>
                    <p>
                        {{ $customer->owner_name ?? '' }}
                        @if (! empty($customer->contact_number))
                            | {{ $customer->contact_number }}
                        @endif
                    </p>
                </div>
                <div>
                    <table class="meta-table">
                        <tr>
                            <td>Date Issued:</td>
                            <td>{{ \Carbon\Carbon::parse($invoice->created_at)->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <td>Period Covered:</td>
                            <td>{{ \Carbon\Carbon::parse($invoice->period_start)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($invoice->period_end)->format('M d, Y') }}</td>
                        </tr>
                    </table>
                </div>
            </section>

            <div class="table-title">Transaction Breakdown</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="14%">Date</th>
                        <th width="17%">OR #</th>
                        <th width="{{ $hasDiscountColumn ? '39%' : '49%' }}">Description</th>
                        @if ($hasDiscountColumn)
                            <th width="10%" class="amount-col">Discount</th>
                        @endif
                        <th width="12%" class="amount-col">Amount</th>
                        @if ($canEditInvoice)
                            <th width="8%" class="screen-actions">Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($chargeTransactions as $row)
                        @php
                            $ref = trim((string) ($row->reference_no ?? ''));
                            $description = trim((string) ($row->description ?? ''));
                            $editedSaleId = null;
                            $isEditedReference = preg_match('/^EDIT-(\d+)$/i', $ref, $editReferenceMatches);

                            if ($isEditedReference) {
                                $editedSaleId = (int) $editReferenceMatches[1];
                            }

                            $ledgerOrNumber = trim((string) ($row->or_number ?? ''));
                            $reference = $ref !== '' ? ($salesOrNumbersByLookupKey[$ref] ?? '') : '';

                            if ($isEditedReference) {
                                $reference = $salesOrNumbersByEditedId[$editedSaleId] ?? '';
                            }

                            if ($reference === '' && $ledgerOrNumber !== '') {
                                $reference = $salesOrNumbersByLookupKey[$ledgerOrNumber] ?? $ledgerOrNumber;
                            }

                            if ($reference === '') {
                                $reference = 'N/A';
                            }

                            $discountAmount = (float) ($row->discount_amount ?? $row->discount ?? 0);
                            $excessAmount = 0;

                            if ($discountAmount == 0 && preg_match('/\((?:Total )?Discount:\s*[^\d]*([\d,.]+)\)/i', $description, $discountMatches)) {
                                $discountAmount = (float) str_replace(',', '', $discountMatches[1]);
                            }

                            if (preg_match('/\((?:Total )?Excess:\s*[^\d]*([\d,.]+)\)/i', $description, $excessMatches)) {
                                $excessAmount = (float) str_replace(',', '', $excessMatches[1]);
                            }

                            $description = trim(preg_replace('/\((?:Total )?(?:Discount|Excess):\s*[^\d]*[\d,.]+\)/i', '', $description));
                            $description = preg_replace('/\s+/', ' ', $description);

                            if ($isEditedReference) {
                                $description = trim(preg_replace('/\s+from\s+\S+$/i', '', $description));
                            }

                            if ($excessAmount > 0) {
                                $description .= ' (+)';
                            }
                        @endphp
                        <tr class="editable-ledger-row" data-ledger-id="{{ $row->id }}">
                            <td>
                                <span class="display-value">{{ \Carbon\Carbon::parse($row->transaction_date)->format('m/d/Y') }}</span>
                                <input class="edit-input edit-date" type="date" value="{{ \Carbon\Carbon::parse($row->transaction_date)->format('Y-m-d') }}">
                            </td>
                            <td>
                                <span class="display-value">{{ $reference !== '' ? $reference : 'N/A' }}</span>
                                <input class="edit-input edit-or-number" type="text" value="{{ $reference !== 'N/A' ? $reference : '' }}">
                            </td>
                            <td>
                                <span class="display-value">{{ $description !== '' ? $description : 'Credit Charge' }}</span>
                                <textarea class="edit-input edit-description" maxlength="255">{{ $row->raw_description ?? $row->description }}</textarea>
                            </td>
                            @if ($hasDiscountColumn)
                                <td class="amount-col">
                                    <span class="display-value">{{ $discountAmount > 0 ? number_format($discountAmount, 2) : '-' }}</span>
                                    <input class="edit-input edit-discount" type="number" min="0" step="0.01" value="{{ number_format($discountAmount, 2, '.', '') }}">
                                </td>
                            @endif
                            <td class="amount-col">
                                <span class="display-value">{{ number_format((float) $row->debit, 2) }}</span>
                                <input class="edit-input edit-debit" type="number" min="0" step="0.01" value="{{ number_format((float) $row->debit, 2, '.', '') }}">
                            </td>
                            @if ($canEditInvoice)
                                <td class="screen-actions">
                                    <button type="button" class="delete-row-btn" onclick="deleteBillingRow(this)">Delete</button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($hasDiscountColumn ? 5 : 4) + ($canEditInvoice ? 1 : 0) }}" class="text-center" style="padding: 15px; color: #667085;">No fuel charges recorded during this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="summary-box">
                <table class="summary-table">
                    @foreach ($rolledInvoices as $rolledInvoice)
                        <tr>
                            <td>Prev Balance ({{ $rolledInvoice->invoice_number }})</td>
                            <td class="amount-col">{{ number_format((float) $rolledInvoice->remaining_balance, 2) }}</td>
                        </tr>
                    @endforeach
                    @if ((float) $unbilledCarryOver > 0.01)
                        <tr>
                            <td>Previous Balance</td>
                            <td class="amount-col">{{ number_format((float) $unbilledCarryOver, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Current Fuel Charges</td>
                        <td class="amount-col">{{ number_format((float) $invoice->current_charges, 2) }}</td>
                    </tr>
                    @if ($totalSavingsApplied > 0)
                        <tr>
                            <td style="color: #d92d20;">Total Savings Applied</td>
                            <td class="amount-col" style="color: #d92d20;">- {{ number_format((float) $totalSavingsApplied, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="grand-total">
                        <td>TOTAL AMOUNT DUE</td>
                        <td class="amount-col">PHP {{ number_format((float) $invoice->total_amount_due, 2) }}</td>
                    </tr>
                    @if ((float) $displayPaymentsReceived > 0)
                        <tr>
                            <td>Less: Payments Made</td>
                            <td class="amount-col">({{ number_format((float) $displayPaymentsReceived, 2) }})</td>
                        </tr>
                        @if ($payments->count() > 0)
                            <tr>
                                <td colspan="2" style="font-size: 10px; color: #475467; line-height: 1.45;">
                                    <strong>Payments Applied:</strong><br>
                                    @foreach ($payments as $payment)
                                        {{ \Carbon\Carbon::parse($payment->date)->format('M d, Y') }} - PHP {{ number_format((float) $payment->amount, 2) }}
                                        @if (! empty($payment->desc))
                                            <span style="color: #667085;">({{ $payment->desc }})</span>
                                        @endif
                                        <br>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                        @if ($remainingBalance < 0)
                            <tr class="advance-total">
                                <td>ADVANCE CREDIT</td>
                                <td class="amount-col">PHP {{ number_format(abs($remainingBalance), 2) }}</td>
                            </tr>
                        @else
                            <tr class="grand-total" style="background: #344054;">
                                <td>REMAINING BALANCE</td>
                                <td class="amount-col">PHP {{ number_format($remainingBalance, 2) }}</td>
                            </tr>
                        @endif
                    @endif
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
                <p>Invoice Generated via JR-M Fuel Station Management System &copy; {{ date('Y') }}</p>
            </div>
        </section>
    </main>

    <script>
        const signatorySaveUrl = @json($signatorySaveUrl);
        const billingUpdateUrl = @json(route('admin.billing.updateRows', $invoice->id));
        const billingDeleteUrl = @json(route('admin.billing.deleteRow', $invoice->id));
        const billingCsrfToken = @json(csrf_token());
        const canEditInvoice = @json($canEditInvoice);
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
            setSignatoryStatus('Saving invoice signatories...', '');

            return fetch(signatorySaveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': billingCsrfToken
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

        function setBillingEditButtons(isEditing, isSaving = false) {
            if (!canEditInvoice) return;

            const editButton = document.getElementById('editBillingBtn');
            const saveButton = document.getElementById('saveBillingBtn');
            const cancelButton = document.getElementById('cancelBillingBtn');
            const deleteButtons = document.querySelectorAll('.delete-row-btn');

            if (editButton) editButton.style.display = isEditing ? 'none' : '';
            if (saveButton) {
                saveButton.style.display = isEditing ? '' : 'none';
                saveButton.disabled = isSaving;
                saveButton.textContent = isSaving ? 'Saving...' : 'Save Changes';
            }
            if (cancelButton) {
                cancelButton.style.display = isEditing ? '' : 'none';
                cancelButton.disabled = isSaving;
            }
            deleteButtons.forEach((button) => {
                button.disabled = isSaving;
            });
        }

        function enableBillingEdit() {
            document.body.classList.add('is-editing');
            setBillingEditButtons(true);
        }

        function cancelBillingEdit() {
            if (confirm('Discard unsaved billing edits?')) {
                window.location.reload();
            }
        }

        function saveBillingEdits() {
            const rows = Array.from(document.querySelectorAll('.editable-ledger-row')).map((row) => {
                const discountInput = row.querySelector('.edit-discount');

                return {
                    id: row.dataset.ledgerId,
                    transaction_date: row.querySelector('.edit-date').value,
                    or_number: row.querySelector('.edit-or-number').value,
                    description: row.querySelector('.edit-description').value,
                    discount: discountInput ? discountInput.value : 0,
                    debit: row.querySelector('.edit-debit').value
                };
            });

            if (rows.length === 0) {
                alert('There are no billing rows to save.');
                return;
            }

            const invalidRow = rows.find((row) => !row.transaction_date || row.debit === '' || Number(row.debit) < 0 || Number(row.discount) < 0);

            if (invalidRow) {
                alert('Please check the edited dates and amounts before saving.');
                return;
            }

            setBillingEditButtons(true, true);

            fetch(billingUpdateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': billingCsrfToken
                },
                body: JSON.stringify({ rows })
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then((result) => {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.message || 'Unable to save billing edits.');
                    }

                    alert(result.data.message);
                    window.location.reload();
                })
                .catch((error) => {
                    alert(error.message);
                    setBillingEditButtons(true, false);
                });
        }

        function deleteBillingRow(button) {
            const row = button.closest('.editable-ledger-row');

            if (!row) return;

            const reference = row.querySelector('.edit-or-number')?.value || row.querySelector('.display-value')?.textContent || 'this row';

            if (!confirm('Delete ' + reference + ' from this billing statement? It will be archived, then removed from Sales and Customer Ledger computations.')) {
                return;
            }

            button.textContent = 'Deleting...';
            setBillingEditButtons(true, true);

            fetch(billingDeleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': billingCsrfToken
                },
                body: JSON.stringify({ row_id: row.dataset.ledgerId })
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then((result) => {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.message || 'Unable to delete billing row.');
                    }

                    alert(result.data.message);
                    window.location.reload();
                })
                .catch((error) => {
                    alert(error.message);
                    button.textContent = 'Delete';
                    setBillingEditButtons(true, false);
                });
        }
    </script>
</body>
</html>
