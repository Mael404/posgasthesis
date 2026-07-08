<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acknowledgement Receipt - {{ $invoice->invoice_number }}</title>
    @include('layouts.browser-icon')
    <style>
        @page { margin: 1.2cm; size: A4 portrait; }
        body {
            margin: 0;
            background: #f3f4f6;
            color: #111;
            font-family: "Times New Roman", Times, serif;
            font-size: 14px;
            line-height: 1.45;
        }
        .no-print {
            position: fixed;
            right: 18px;
            top: 18px;
            width: 340px;
            z-index: 10;
        }
        .signatory-panel {
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.14);
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            padding: 14px;
        }
        .signatory-panel-title {
            color: #111827;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .signatory-field { margin-bottom: 10px; }
        .signatory-field label {
            color: #4b5563;
            display: block;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .signatory-field input {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            font-size: 13px;
            padding: 8px 9px;
            width: 100%;
        }
        .signatory-field input[readonly] {
            background: #f3f4f6;
            color: #4b5563;
        }
        .signatory-actions {
            display: flex;
            gap: 8px;
        }
        .print-btn, .save-btn {
            border: 0;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            flex: 1;
            font-family: Arial, sans-serif;
            font-weight: 700;
            padding: 9px 16px;
        }
        .save-btn { background: #4b5563; }
        .print-btn { background: #198754; }
        .print-btn:disabled, .save-btn:disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }
        .signatory-status {
            color: #4b5563;
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin-top: 9px;
            min-height: 15px;
        }
        .signatory-status.error { color: #b91c1c; }
        .signatory-status.success { color: #047857; }
        .sheet {
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
            box-sizing: border-box;
            margin: 24px auto;
            min-height: 270mm;
            padding: 20mm 16mm;
            width: 190mm;
        }
        .receipt-header {
            align-items: start;
            column-gap: 24px;
            display: grid;
            grid-template-columns: 1fr 230px;
            margin-bottom: 18px;
        }
        .company-title {
            font-size: 16px;
            font-weight: 700;
            margin-top: 42px;
        }
        .receipt-title {
            font-style: italic;
            font-weight: 700;
            margin-top: 4px;
        }
        .logo {
            display: block;
            height: auto;
            width: 230px;
        }
        .payment-type {
            margin: 10px 0 20px;
            text-align: center;
        }
        .check-row {
            align-items: center;
            display: inline-flex;
            gap: 12px;
        }
        .box {
            align-items: center;
            border: 1px solid #111;
            display: inline-flex;
            font-family: Arial, sans-serif;
            font-size: 9px;
            height: 11px;
            justify-content: center;
            line-height: 1;
            vertical-align: -1px;
            width: 11px;
        }
        .box.checked::after {
            content: "X";
            font-weight: 700;
        }
        .meta-line { margin: 0 0 34px; }
        .label { font-weight: 700; }
        .underline {
            border-bottom: 1px solid #111;
            font-weight: 700;
            padding: 0 3px;
        }
        .received-line { margin: 0 0 18px; }
        .receipt-body {
            max-width: 95%;
            text-align: justify;
        }
        .details-table {
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 24px;
            width: 100%;
        }
        .details-table th, .details-table td {
            border: 1px solid #222;
            padding: 7px 8px;
            vertical-align: top;
        }
        .details-table th {
            background: #f4f4f4;
            font-weight: 700;
            text-align: left;
        }
        .amount-col {
            text-align: right;
            white-space: nowrap;
        }
        .total-row td { font-weight: 700; }
        .receipt-footer { margin-top: 64px; }
        .footer-line { margin: 0 0 2px; }
        .signature-value {
            border-bottom: 1px solid #111;
            display: inline-block;
            font-weight: 700;
            min-width: 220px;
            padding: 0 3px;
        }
        .footer-helper { margin: 0 0 18px; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .sheet {
                box-shadow: none;
                margin: 0;
                min-height: auto;
                padding: 0;
                width: auto;
            }
        }
    </style>
</head>
<body>
    @php
        $businessName = trim((string) ($customer->business_name ?? ''));
        $ownerName = trim((string) ($customer->owner_name ?? ''));
        $hasBusinessName = $businessName !== '' && strtoupper($businessName) !== 'N/A';
        $hasOwnerName = $ownerName !== '' && strtoupper($ownerName) !== 'N/A';
        $receivedFrom = $hasBusinessName ? $businessName : ($hasOwnerName ? $ownerName : 'Customer #' . $customer->id);
        $ownerLine = $hasBusinessName && $hasOwnerName && strcasecmp($businessName, $ownerName) !== 0 ? $ownerName : null;
        $receivedByValue = $receiptSignatories->received_by ?? '';
        $paidByValue = $receiptSignatories->paid_by ?? '';
        $signedAtValue = \Carbon\Carbon::parse($receiptSignedAt ?? now());
    @endphp

    <div class="no-print">
        <div class="signatory-panel">
            <div class="signatory-panel-title">Receipt Signatories</div>
            <div class="signatory-field">
                <label for="received_by_input">Received By</label>
                <input type="text" id="received_by_input" value="{{ $receivedByValue }}" placeholder="Cashier / Authorized Personnel">
            </div>
            <div class="signatory-field">
                <label for="paid_by_input">Paid By</label>
                <input type="text" id="paid_by_input" value="{{ $paidByValue }}" placeholder="Customer / Representative">
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
        <header class="receipt-header">
            <div>
                <div class="company-title">JR-M FUEL STATION</div>
                <div class="receipt-title">Acknowledgement Receipt</div>
            </div>
            <img src="{{ asset('img/logojrs.png') }}" alt="JR-M Fuel Station Logo" class="logo">
        </header>

        <div class="payment-type">
            <span class="check-row">
                <span><span class="box {{ ! $isFullPayment ? 'checked' : '' }}"></span> Partial</span>
                <span><span class="box {{ $isFullPayment ? 'checked' : '' }}"></span> Full</span>
            </span>
        </div>

        <p class="meta-line">
            <span class="label">Date:</span>
            <span class="underline">{{ \Carbon\Carbon::parse($receiptDate)->format('F d, Y') }}</span>
        </p>

        <p class="received-line">
            Received from:
            <span class="underline">{{ $receivedFrom }}</span>
            @if ($ownerLine)
                <span> / {{ $ownerLine }}</span>
            @endif
        </p>

        <section class="receipt-body">
            The amount of <strong>PHP {{ number_format($receiptAmount, 2) }}</strong> is acknowledged as
            <strong>{{ $isFullPayment ? 'full' : 'partial' }} payment</strong> for billing statement
            <strong>{{ $invoice->invoice_number }}</strong>, covering fuel purchases from
            <strong>{{ \Carbon\Carbon::parse($invoice->period_start)->format('F d, Y') }}</strong>
            to
            <strong>{{ \Carbon\Carbon::parse($invoice->period_end)->format('F d, Y') }}</strong>.
            This payment shall be applied to the outstanding billed amount of
            <strong>PHP {{ number_format((float) $invoice->total_amount_due, 2) }}</strong>,
            leaving a remaining balance of
            <strong>PHP {{ number_format($remainingBalance, 2) }}</strong>.
        </section>

        <table class="details-table">
            <thead>
                <tr>
                    <th width="18%">Date</th>
                    <th>Particulars</th>
                    <th width="20%" class="amount-col">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($payment->transaction_date)->format('m/d/Y') }}</td>
                        <td>{{ $payment->receipt_description ?: 'Payment for Invoice ' . $invoice->invoice_number }}</td>
                        <td class="amount-col">{{ number_format((float) $payment->credit, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($receiptDate)->format('m/d/Y') }}</td>
                        <td>Payment for Invoice {{ $invoice->invoice_number }}</td>
                        <td class="amount-col">{{ number_format($receiptAmount, 2) }}</td>
                    </tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="2" class="amount-col">TOTAL RECEIVED</td>
                    <td class="amount-col">PHP {{ number_format($receiptAmount, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="amount-col">{{ $isFullPayment ? 'PAYMENT STATUS' : 'REMAINING BALANCE' }}</td>
                    <td class="amount-col">
                        {{ $isFullPayment ? 'FULLY PAID' : 'PHP ' . number_format($remainingBalance, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="receipt-footer">
            <p class="footer-line">Received by: <span class="signature-value" id="print_received_by">{{ $receivedByValue }}</span></p>
            <p class="footer-helper">(Signature over Printed Name - Cashier/Authorized Personnel)</p>

            <p class="footer-line">Paid by: <span class="signature-value" id="print_paid_by">{{ $paidByValue }}</span></p>
            <p class="footer-helper">(Signature over Printed Name - Customer/Representative)</p>

            <p class="footer-line">Date &amp; Time: <span class="signature-value" id="print_signed_at">{{ $signedAtValue->format('F d, Y h:i A') }}</span></p>
        </div>
    </main>

    <script>
        const signatorySaveUrl = @json($signatorySaveUrl);
        const signatoryCsrfToken = @json(csrf_token());
        const saveButton = document.getElementById('saveSignatoriesBtn');
        const savePrintButton = document.getElementById('savePrintSignatoriesBtn');
        const signatoryStatus = document.getElementById('signatoryStatus');
        const receivedByInput = document.getElementById('received_by_input');
        const paidByInput = document.getElementById('paid_by_input');
        const signedAtInput = document.getElementById('signed_at_input');
        const printReceivedBy = document.getElementById('print_received_by');
        const printPaidBy = document.getElementById('print_paid_by');
        const printSignedAt = document.getElementById('print_signed_at');

        function setSignatoryStatus(message, type) {
            signatoryStatus.textContent = message || '';
            signatoryStatus.className = 'signatory-status' + (type ? ' ' + type : '');
        }

        function setSignatoryButtons(disabled) {
            saveButton.disabled = disabled;
            savePrintButton.disabled = disabled;
        }

        function saveSignatories(shouldPrint) {
            setSignatoryButtons(true);
            setSignatoryStatus('Saving receipt signatories...', '');

            return fetch(signatorySaveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': signatoryCsrfToken
                },
                body: JSON.stringify({
                    received_by: receivedByInput.value,
                    paid_by: paidByInput.value
                })
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then((result) => {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.message || 'Unable to save signatories.');
                    }

                    printReceivedBy.textContent = result.data.received_by || '';
                    printPaidBy.textContent = result.data.paid_by || '';
                    printSignedAt.textContent = result.data.signed_at || '';
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

        saveButton.addEventListener('click', () => saveSignatories(false));
        savePrintButton.addEventListener('click', () => saveSignatories(true));
    </script>
</body>
</html>
