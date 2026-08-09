@php($editingOfficialReceipt = (bool) ($editingOfficialReceipt ?? false))

@once
    <style>
        .sales-invoice-paper {
            font-family: Arial, Helvetica, sans-serif;
        }

        .sales-invoice-input {
            width: 100%;
            min-width: 0;
            border: 0;
            outline: 0;
            background: transparent;
            padding: 2px 4px;
            font: inherit;
            font-weight: 700;
        }

        .sales-invoice-input:focus {
            background: #fffde7;
            box-shadow: inset 0 0 0 2px rgba(18, 183, 106, 0.28);
        }

        .sales-invoice-line-input {
            border-bottom: 1px solid #000;
        }

        .sales-invoice-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .sales-invoice-table th,
        .sales-invoice-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
        }

        .sales-invoice-readonly {
            color: #111827;
            background: #f9fafb;
        }

        .sales-invoice-date-calendar {
            z-index: 100080 !important;
        }

        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        @media print {
            body * {
                visibility: hidden !important;
            }

            .sales-invoice-print-root,
            .sales-invoice-print-root * {
                visibility: visible !important;
            }

            .sales-invoice-print-root {
                position: absolute !important;
                inset: 0 auto auto 0 !important;
                display: block !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
                padding: 0 !important;
                background: #fff !important;
            }

            .sales-invoice-modal-panel,
            .sales-invoice-modal-scroll {
                display: block !important;
                width: 100% !important;
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
                padding: 0 !important;
            }

            .sales-invoice-paper {
                width: 100% !important;
                max-width: none !important;
                min-height: auto !important;
                margin: 0 !important;
                box-shadow: none !important;
            }

            [data-sales-invoice-no-print],
            .sales-invoice-modal-backdrop {
                display: none !important;
            }

            .sales-invoice-input:focus {
                background: transparent !important;
                box-shadow: none !important;
            }
        }
    </style>
@endonce

<template x-teleport="body">
    <div
        x-show="salesInvoiceModalOpen"
        x-cloak
        x-on:keydown.escape.window="if (!savingSalesInvoice) closeSalesInvoiceModal()"
        class="sales-invoice-print-root fixed inset-0 flex items-center justify-center overflow-hidden p-2 sm:p-4"
        style="z-index: 100020;">
        <div class="sales-invoice-modal-backdrop fixed inset-0 bg-gray-950/80 backdrop-blur-md" x-on:click="if (!savingSalesInvoice) closeSalesInvoiceModal()"></div>

        <form
            x-on:submit.prevent="submitSalesInvoice(false)"
            class="sales-invoice-modal-panel relative flex h-[96dvh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-gray-100 shadow-2xl dark:border-gray-800 dark:bg-gray-950">
            <div data-sales-invoice-no-print class="flex shrink-0 items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900 sm:px-5">
                <div>
                    <p class="text-theme-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">{{ $editingOfficialReceipt ? 'Admin correction' : 'New document' }}</p>
                    <h2 class="mt-0.5 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $editingOfficialReceipt ? 'Edit Sales Invoice' : 'Sales Invoice' }}</h2>
                </div>
                <button type="button" x-on:click="closeSalesInvoiceModal" class="inline-flex size-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400" aria-label="Close sales invoice">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
                </button>
            </div>

            <div class="sales-invoice-modal-scroll min-h-0 flex-1 overflow-auto p-2 sm:p-5">
                <section class="sales-invoice-paper mx-auto min-h-[270mm] w-[210mm] max-w-full border border-black bg-white text-black shadow-xl">
                    <header class="px-3 pt-3 text-center leading-tight">
                        <h1 class="text-[20px] font-black tracking-wide">JR-M FUEL STATION</h1>
                        <p class="text-[16px] font-bold">Sitio Apad, Calongay, Pilar, Sorsogon</p>
                        <p class="text-[15px] font-bold">Ronillo B. Miranda Jr. - Prop. VAT Reg. TIN: 681-490-993-00000</p>
                    </header>

                    <div class="grid grid-cols-2 items-end gap-6 px-3 pt-1">
                        <div>
                            <div class="text-[17px] font-black tracking-[0.55em]">SALES</div>
                            <div class="pl-5 text-[18px] font-black">INVOICE</div>
                        </div>
                        <label class="flex items-center justify-end gap-2 text-[16px] font-bold">
                            <span>Date:</span>
                            <span class="relative inline-flex w-[250px] max-w-full items-center">
                                <input
                                    x-model="salesInvoice.invoice_date"
                                    x-init="
                                        $nextTick(() => {
                                            if (!window.flatpickr) {
                                                $el.removeAttribute('readonly');
                                                return;
                                            }

                                            if ($el._salesInvoiceDatePicker) {
                                                return;
                                            }

                                            $el._salesInvoiceDatePicker = window.flatpickr($el, {
                                                dateFormat: 'Y-m-d',
                                                defaultDate: salesInvoice.invoice_date || null,
                                                allowInput: false,
                                                disableMobile: true,
                                                clickOpens: true,
                                                onReady: (selectedDates, dateStr, instance) => {
                                                    instance.calendarContainer.classList.add('sales-invoice-date-calendar');
                                                },
                                                onChange: (selectedDates, dateStr) => {
                                                    salesInvoice.invoice_date = dateStr;
                                                }
                                            });

                                            $watch('salesInvoice.invoice_date', (value) => {
                                                if (!$el._salesInvoiceDatePicker) {
                                                    return;
                                                }

                                                if (($el._salesInvoiceDatePicker.input.value || '') !== (value || '')) {
                                                    $el._salesInvoiceDatePicker.setDate(value || null, false);
                                                }
                                            });
                                        })
                                    "
                                    x-on:click="$el._salesInvoiceDatePicker?.open()"
                                    x-on:focus="$el._salesInvoiceDatePicker?.open()"
                                    class="sales-invoice-input sales-invoice-line-input cursor-pointer pr-9 text-center"
                                    type="text"
                                    placeholder="YYYY-MM-DD"
                                    readonly
                                    aria-label="Invoice date">
                                <button
                                    type="button"
                                    data-sales-invoice-no-print
                                    x-on:click="$el.previousElementSibling._salesInvoiceDatePicker?.open()"
                                    class="absolute right-0 top-1/2 inline-flex size-8 -translate-y-1/2 items-center justify-center text-gray-700 transition hover:text-brand-600"
                                    aria-label="Open invoice date calendar">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M7.75 3.75v3M16.25 3.75v3M4.75 8.75h14.5M6.75 5.25h10.5A2.75 2.75 0 0 1 20 8v9.25A2.75 2.75 0 0 1 17.25 20H6.75A2.75 2.75 0 0 1 4 17.25V8a2.75 2.75 0 0 1 2.75-2.75Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </span>
                        </label>
                    </div>

                    <div class="h-16 sm:h-20"></div>

                    <div class="border-t border-black text-[15px] font-bold">
                        <label class="flex items-center border-b border-black px-1 py-1">
                            <span class="shrink-0">Sold to:</span>
                            <input x-model="salesInvoice.sold_to" x-on:input="salesInvoice.sold_to = $event.target.value.toUpperCase()" class="sales-invoice-input ml-2 uppercase" type="text" maxlength="255" placeholder="No Name Inputted">
                        </label>

                        <div class="grid grid-cols-[42%_58%] border-b border-black">
                            <label class="flex items-center border-r border-black px-1 py-1">
                                <span class="shrink-0">TIN:</span>
                                <input x-model="salesInvoice.tin" class="sales-invoice-input sales-invoice-line-input ml-1" type="text" maxlength="255" placeholder="No TIN Inputted">
                            </label>
                            <label class="flex items-center px-1 py-1">
                                <span class="shrink-0">Terms:</span>
                                <input x-model="salesInvoice.terms" class="sales-invoice-input sales-invoice-line-input ml-1" type="text" maxlength="255" placeholder="No Terms Inputted">
                            </label>
                        </div>

                        <label class="flex items-center border-b border-black px-1 py-1">
                            <span class="shrink-0">Address:</span>
                            <input x-model="salesInvoice.address" x-on:input="salesInvoice.address = $event.target.value.toUpperCase()" class="sales-invoice-input ml-2 uppercase" type="text" maxlength="1000" placeholder="No Address Inputted">
                        </label>

                        <div class="grid grid-cols-[70%_30%] border-b border-black">
                            <label class="flex items-center border-r border-black px-1 py-1">
                                <span class="shrink-0">SC/PWD/NAAC/MOV/SP ID No.:</span>
                                <input x-model="salesInvoice.discount_id_no" class="sales-invoice-input sales-invoice-line-input ml-1" type="text" maxlength="255" placeholder="No ID Number Inputted">
                            </label>
                            <label class="flex items-center px-1 py-1">
                                <span class="shrink-0">Signature:</span>
                                <input x-model="salesInvoice.signature" class="sales-invoice-input sales-invoice-line-input ml-1" type="text" maxlength="255" placeholder="No Signature Inputted">
                            </label>
                        </div>
                    </div>

                    <table class="sales-invoice-table text-[14px]">
                        <colgroup>
                            <col style="width: 43%">
                            <col style="width: 14%">
                            <col style="width: 21%">
                            <col style="width: 22%">
                        </colgroup>
                        <thead>
                            <tr class="text-left text-[15px] font-black">
                                <th>Item Description / Nature of Service</th>
                                <th class="text-center">QTY.</th>
                                <th class="text-center">PRICE</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in salesInvoice.items" :key="index">
                                <tr class="h-12">
                                    <td>
                                        <select
                                            x-model="item.item_type"
                                            class="sales-invoice-input cursor-pointer"
                                            @unless ($editingOfficialReceipt)
                                                x-on:change="salesInvoiceItemError = ''"
                                                :class="{ '!bg-red-50 ring-2 ring-inset ring-red-500': salesInvoiceItemError && !salesInvoiceItemTypeIsValid(item.item_type) && (invoiceItemHasInput(item) || !salesInvoiceHasValidItem()) }"
                                                :aria-invalid="salesInvoiceItemError && !salesInvoiceItemTypeIsValid(item.item_type) ? 'true' : 'false'"
                                                aria-describedby="sales-invoice-item-error"
                                            @endunless
                                            :aria-label="'Item type row ' + (index + 1)">
                                            <option value="">Select item</option>
                                            <option value="Diesel">Diesel</option>
                                            <option value="Premium">Premium</option>
                                            <option value="Unleaded">{{ \App\Models\SalesInvoiceItem::UNLEADED_DISPLAY_NAME }}</option>
                                            <option value="Others">Others</option>
                                        </select>
                                        <input
                                            x-show="item.item_type === 'Others'"
                                            x-cloak
                                            x-model="item.other_description"
                                            class="sales-invoice-input sales-invoice-line-input mt-1"
                                            type="text"
                                            maxlength="255"
                                            placeholder="Enter other item/service"
                                            :aria-label="'Other item description row ' + (index + 1)">
                                    </td>
                                    <td>
                                        <input
                                            x-model="item.quantity"
                                            class="sales-invoice-input text-center tabular-nums"
                                            type="number"
                                            min="{{ $editingOfficialReceipt ? '0' : '0.001' }}"
                                            step="0.001"
                                            @unless ($editingOfficialReceipt)
                                                x-on:input="salesInvoiceItemError = ''"
                                                :class="{ '!bg-red-50 ring-2 ring-inset ring-red-500': salesInvoiceItemFieldInvalid(item, 'quantity') }"
                                                :aria-invalid="salesInvoiceItemFieldInvalid(item, 'quantity') ? 'true' : 'false'"
                                                aria-describedby="sales-invoice-item-error"
                                            @endunless
                                            :aria-label="'Quantity row ' + (index + 1)">
                                    </td>
                                    <td>
                                        <input
                                            x-model="item.price"
                                            class="sales-invoice-input text-right tabular-nums"
                                            type="number"
                                            min="{{ $editingOfficialReceipt ? '0' : '0.01' }}"
                                            step="0.01"
                                            @unless ($editingOfficialReceipt)
                                                x-on:input="salesInvoiceItemError = ''"
                                                :class="{ '!bg-red-50 ring-2 ring-inset ring-red-500': salesInvoiceItemFieldInvalid(item, 'price') }"
                                                :aria-invalid="salesInvoiceItemFieldInvalid(item, 'price') ? 'true' : 'false'"
                                                aria-describedby="sales-invoice-item-error"
                                            @endunless
                                            :aria-label="'Price row ' + (index + 1)">
                                    </td>
                                    <td>
                                        <input
                                            x-model="item.amount"
                                            class="sales-invoice-input text-right tabular-nums"
                                            type="number"
                                            min="{{ $editingOfficialReceipt ? '0' : '0.01' }}"
                                            step="0.01"
                                            @unless ($editingOfficialReceipt)
                                                x-on:input="salesInvoiceItemError = ''"
                                                :class="{ '!bg-red-50 ring-2 ring-inset ring-red-500': salesInvoiceItemFieldInvalid(item, 'amount') }"
                                                :aria-invalid="salesInvoiceItemFieldInvalid(item, 'amount') ? 'true' : 'false'"
                                                aria-describedby="sales-invoice-item-error"
                                            @endunless
                                            :aria-label="'Amount row ' + (index + 1)">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    @unless ($editingOfficialReceipt)
                        <p
                            id="sales-invoice-item-error"
                            data-sales-invoice-no-print
                            x-show="salesInvoiceItemError"
                            x-cloak
                            class="border-x border-b border-red-500 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700"
                            role="alert"
                            x-text="salesInvoiceItemError"></p>
                    @endunless

                    <div class="grid grid-cols-[42%_58%] text-[14px] font-bold">
                        <div class="border-r border-black">
                            <div class="grid grid-cols-[58%_42%] border-b border-black">
                                <div class="border-r border-black px-1 py-1">VATable Sales</div>
                                <output class="sales-invoice-readonly px-2 py-1 text-right tabular-nums" x-text="invoiceMoney(invoiceVatableSales())"></output>
                            </div>
                            <div class="grid grid-cols-[58%_42%] border-b border-black">
                                <div class="border-r border-black px-1 py-1">VAT-Exempt Sales</div>
                                <input x-model="salesInvoice.vat_exempt_sales" class="sales-invoice-input text-right tabular-nums" type="number" min="0" step="0.01" placeholder="0.00">
                            </div>
                            <div class="grid grid-cols-[58%_42%] border-b border-black">
                                <div class="border-r border-black px-1 py-1">Zero Rated Sales</div>
                                <input x-model="salesInvoice.zero_rated_sales" class="sales-invoice-input text-right tabular-nums" type="number" min="0" step="0.01" placeholder="0.00">
                            </div>
                            <div class="grid grid-cols-[58%_42%] border-b border-black">
                                <div class="border-r border-black px-1 py-1">VAT Amount</div>
                                <output class="sales-invoice-readonly px-2 py-1 text-right tabular-nums" x-text="invoiceMoney(invoiceVatAmount())"></output>
                            </div>
                            <div class="flex min-h-[92px] items-start gap-3 px-3 py-3">
                                <span class="pt-1 text-[16px] font-black">SI NO.</span>
                                <output
                                    class="inline-flex min-w-28 items-center justify-center rounded-lg border-2 border-red-500 bg-red-50 px-4 py-1.5 text-[24px] font-black leading-none tracking-wider text-red-600 tabular-nums"
                                    x-text="formatSiNumber(salesInvoice.si_number)"
                                    aria-label="Automatically assigned sales invoice number"></output>
                            </div>
                        </div>

                        <div>
                            <div class="grid grid-cols-[62%_38%] border-b border-black">
                                <div class="border-r border-black px-1 py-1">Total Sales (VAT Inclusive)</div>
                                <output class="sales-invoice-readonly px-2 py-1 text-right tabular-nums" x-text="invoiceMoney(invoiceTotalSales())"></output>
                            </div>
                            <div class="grid grid-cols-[62%_38%] border-b border-black">
                                <div class="border-r border-black px-1 py-1">Less: VAT</div>
                                <output class="sales-invoice-readonly px-2 py-1 text-right tabular-nums" x-text="invoiceMoney(invoiceLessVat())"></output>
                            </div>
                            <div class="grid grid-cols-[62%_38%] border-b border-black">
                                <div class="border-r border-black px-1 py-1">Amount net of VAT</div>
                                <output class="sales-invoice-readonly px-2 py-1 text-right tabular-nums" x-text="invoiceMoney(invoiceAmountNetOfVat())"></output>
                            </div>
                            <div class="grid grid-cols-[62%_38%] border-b border-black">
                                <div class="border-r border-black px-1 py-1">LESS: SC / PWD/NAAC/MOV/SP Discount</div>
                                <input x-model="salesInvoice.sc_pwd_discount" class="sales-invoice-input text-right tabular-nums" type="number" min="0" step="0.01" placeholder="0.00">
                            </div>
                            <div class="grid grid-cols-[62%_38%] border-b border-black">
                                <div class="border-r border-black px-1 py-1">Add: VAT</div>
                                <output class="sales-invoice-readonly px-2 py-1 text-right tabular-nums" x-text="invoiceMoney(invoiceAddVat())"></output>
                            </div>
                            <div class="grid grid-cols-[62%_38%] border-b border-black">
                                <div class="border-r border-black px-1 py-1">Less: Withholding tax</div>
                                <input x-model="salesInvoice.withholding_tax" class="sales-invoice-input text-right tabular-nums" type="number" min="0" step="0.01" placeholder="0.00">
                            </div>
                            <div class="grid grid-cols-[62%_38%] border-b border-black text-[15px] font-black">
                                <div class="border-r border-black px-1 py-1">TOTAL AMOUNT DUE</div>
                                <output class="sales-invoice-readonly px-2 py-1 text-right font-black tabular-nums" x-text="invoiceMoney(invoiceTotalAmountDue())"></output>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10 min-h-[90px] px-1 py-2 text-[15px] font-black">
                        <div class="w-80 max-w-full">
                            <output
                                class="sales-invoice-readonly block min-h-7 w-full border-b border-black px-1 py-0.5 text-center"
                                x-text="salesInvoice.authorized_representative"
                                aria-label="Authorized representative from the logged-in user"></output>
                            <div class="pt-1 text-center">Authorized Representative</div>
                        </div>
                    </div>
                </section>
            </div>

            <div data-sales-invoice-no-print class="flex shrink-0 flex-col-reverse gap-2 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                @if ($editingOfficialReceipt)
                    <p class="text-theme-xs text-gray-500 dark:text-gray-400">SI number and cancellation status cannot be changed here.</p>
                @else
                    <button type="button" x-on:click="resetSalesInvoice" class="{{ $secondaryButtonClass }} !h-10">Clear Fields</button>
                @endif
                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                    <button type="button" x-on:click="closeSalesInvoiceModal" class="{{ $secondaryButtonClass }} !h-10">Cancel</button>
                    <button type="submit" class="{{ $primaryButtonClass }} !h-10" :disabled="savingSalesInvoice">
                        <span x-text="savingSalesInvoice ? 'Saving...' : '{{ $editingOfficialReceipt ? 'Save Changes' : 'Save Invoice' }}'"></span>
                    </button>
                    @unless ($editingOfficialReceipt)
                        <button type="button" x-on:click="submitSalesInvoice(true)" class="{{ $primaryButtonClass }} !h-10 bg-slate-900 hover:bg-slate-700" :disabled="savingSalesInvoice">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 8V4.75C7 4.336 7.336 4 7.75 4h8.5c.414 0 .75.336.75.75V8M7 17.5H5.75A2.75 2.75 0 0 1 3 14.75v-3A2.75 2.75 0 0 1 5.75 9h12.5A2.75 2.75 0 0 1 21 11.75v3a2.75 2.75 0 0 1-2.75 2.75H17M7.75 14h8.5c.414 0 .75.336.75.75v4.5a.75.75 0 0 1-.75.75h-8.5a.75.75 0 0 1-.75-.75v-4.5c0-.414.336-.75.75-.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <span x-text="savingSalesInvoice ? 'Saving...' : 'Save & Print'"></span>
                        </button>
                    @endunless
                </div>
            </div>
        </form>
    </div>
</template>
