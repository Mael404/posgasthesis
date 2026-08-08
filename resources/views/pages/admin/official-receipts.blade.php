@extends('layouts.app')

@section('content')
    @php
        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $selectClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $smallButtonClass = 'inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-xs font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03]';
        $dangerButtonClass = 'inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-error-200 bg-white px-3 py-2 text-theme-xs font-medium text-error-600 shadow-theme-xs transition hover:bg-error-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-error-500/30 dark:bg-gray-900 dark:text-error-400 dark:hover:bg-error-500/10';
        $thClass = 'whitespace-nowrap px-4 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'whitespace-nowrap px-4 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-4 py-4 align-top text-theme-sm text-gray-700 dark:text-gray-300';
        $tdNumberClass = 'whitespace-nowrap px-4 py-4 text-right align-top text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
        $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
        $isStaff = auth()->user()?->role === \App\Models\User::ROLE_STAFF;
    @endphp

    <style>[x-cloak] { display: none !important; }</style>

    <x-common.page-breadcrumb pageTitle="Official Receipts" />

    <div
        x-data="officialReceiptsModule({
            cancelUrlTemplate: @js(route('admin.official-receipts.cancel', ['receipt' => '__RECEIPT__'])),
            updateUrlTemplate: @js(route('admin.official-receipts.update', ['receipt' => '__RECEIPT__'])),
            authorizedRepresentative: @js(auth()->user()?->name ?? ''),
            csrfToken: @js(csrf_token()),
            flashMessage: @js(session('success'))
        })"
        x-init="init()"
        x-effect="document.body.classList.toggle('overflow-hidden', cancellation.open || salesInvoiceModalOpen)"
        class="space-y-6">

        <div
            x-show="toast.open"
            x-cloak
            x-transition
            class="fixed right-4 top-20 z-[100200] w-[calc(100%-2rem)] max-w-sm rounded-xl p-4 text-white shadow-theme-lg sm:right-6"
            :class="toast.type === 'error' ? 'bg-error-600' : 'bg-success-700'"
            role="status">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.25 4.25L19 7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" /></svg>
                <p class="min-w-0 flex-1 text-theme-sm font-medium" x-text="toast.message"></p>
                <button type="button" x-on:click="closeToast" class="text-white/80 hover:text-white" aria-label="Close message">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
                </button>
            </div>
        </div>

        @unless ($isStaff)
            <section class="{{ $cardClass }}">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Official Receipt Register</h1>
                        <p class="mt-1 {{ $captionClass }}">VAT receipt records patterned after the workbook register. Canceled receipts remain visible.</p>
                    </div>
                    <div class="rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 text-theme-xs font-medium text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                        Financial totals always exclude canceled receipts.
                    </div>
                </div>
            </section>
        @endunless

        <section class="{{ $cardClass }}">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Automatic Filters</h2>
                    <p class="mt-1 {{ $captionClass }}">Changing any field refreshes the register automatically.</p>
                </div>
                @if ($hasActiveFilters)
                    <a href="{{ route('admin.official-receipts.index') }}" class="{{ $secondaryButtonClass }} !h-10">Reset Filters</a>
                @endif
            </div>

            <form x-ref="filterForm" method="GET" action="{{ route('admin.official-receipts.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
                <label class="block">
                    <span class="{{ $labelClass }}">From Date</span>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}" x-on:change="$refs.filterForm.requestSubmit()" class="{{ $inputClass }} mt-1.5">
                </label>
                <label class="block">
                    <span class="{{ $labelClass }}">To Date</span>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}" x-on:change="$refs.filterForm.requestSubmit()" class="{{ $inputClass }} mt-1.5">
                </label>
                <label class="block">
                    <span class="{{ $labelClass }}">Product / Item</span>
                    <input type="text" name="product" value="{{ $filters['product'] }}" list="official-receipt-products" placeholder="Diesel, Premium..." x-on:input.debounce.450ms="$refs.filterForm.requestSubmit()" class="{{ $inputClass }} mt-1.5">
                    <datalist id="official-receipt-products">
                        @foreach ($productOptions as $product)
                            <option value="{{ $product }}"></option>
                        @endforeach
                    </datalist>
                </label>
                <label class="block">
                    <span class="{{ $labelClass }}">Customer Name</span>
                    <input type="search" name="name" value="{{ $filters['name'] }}" placeholder="Search name" x-on:input.debounce.450ms="$refs.filterForm.requestSubmit()" class="{{ $inputClass }} mt-1.5">
                </label>
                <label class="block">
                    <span class="{{ $labelClass }}">SI No. From</span>
                    <input type="number" name="si_from" value="{{ $filters['si_from'] }}" min="1" placeholder="1" x-on:input.debounce.450ms="$refs.filterForm.requestSubmit()" class="{{ $inputClass }} mt-1.5">
                </label>
                <label class="block">
                    <span class="{{ $labelClass }}">SI No. To</span>
                    <input type="number" name="si_to" value="{{ $filters['si_to'] }}" min="1" placeholder="100" x-on:input.debounce.450ms="$refs.filterForm.requestSubmit()" class="{{ $inputClass }} mt-1.5">
                </label>
                <label class="block">
                    <span class="{{ $labelClass }}">Status</span>
                    <select name="status" x-on:change="$refs.filterForm.requestSubmit()" class="{{ $selectClass }} mt-1.5">
                        <option value="all" @selected($filters['status'] === 'all')>All Receipts</option>
                        <option value="Active" @selected($filters['status'] === 'Active')>Active</option>
                        <option value="Canceled" @selected($filters['status'] === 'Canceled')>Canceled</option>
                    </select>
                </label>
            </form>
        </section>

        @unless ($isStaff)
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article class="{{ $cardClass }} min-w-0">
                    <p class="{{ $labelClass }}">Active Receipts</p>
                    <p class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">{{ number_format((int) ($totals->active_receipts ?? 0)) }}</p>
                </article>
                <article class="{{ $cardClass }} min-w-0">
                    <p class="{{ $labelClass }}">Total Sales (VAT Inc.)</p>
                    <p class="mt-2 break-words text-title-sm font-bold text-gray-800 dark:text-white/90">&#8369;{{ number_format((float) ($totals->total_sales_vat_inclusive ?? 0), 2) }}</p>
                </article>
                <article class="{{ $cardClass }} min-w-0">
                    <p class="{{ $labelClass }}">VATable Sales</p>
                    <p class="mt-2 break-words text-title-sm font-bold text-gray-800 dark:text-white/90">&#8369;{{ number_format((float) ($totals->vatable_sales ?? 0), 2) }}</p>
                </article>
                <article class="{{ $cardClass }} min-w-0">
                    <p class="{{ $labelClass }}">VAT Amount</p>
                    <p class="mt-2 break-words text-title-sm font-bold text-gray-800 dark:text-white/90">&#8369;{{ number_format((float) ($totals->vat_amount ?? 0), 2) }}</p>
                </article>
                <article class="{{ $cardClass }} min-w-0 border-l-4 border-l-brand-500">
                    <p class="{{ $labelClass }}">Total Amount Due</p>
                    <p class="mt-2 break-words text-title-sm font-bold text-brand-600 dark:text-brand-400">&#8369;{{ number_format((float) ($totals->total_amount_due ?? 0), 2) }}</p>
                </article>
            </section>
        @endunless

        <section class="{{ $cardClass }}">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">VAT Receipt Data</h2>
                <p class="mt-1 {{ $captionClass }}">{{ $receipts->total() }} receipt{{ $receipts->total() === 1 ? '' : 's' }} found.</p>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="w-full min-w-[1900px]">
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">SI No.</th>
                                <th class="{{ $thClass }}">Date</th>
                                <th class="{{ $thClass }}">Name / TIN / Address</th>
                                <th class="{{ $thClass }}">Item Description</th>
                                <th class="{{ $thNumberClass }}">QTY</th>
                                <th class="{{ $thNumberClass }}">Unit Price</th>
                                <th class="{{ $thNumberClass }}">Total Sales (VAT Inc.)</th>
                                <th class="{{ $thNumberClass }}">VATable Sales</th>
                                <th class="{{ $thNumberClass }}">VAT Amount</th>
                                <th class="{{ $thNumberClass }}">Net of VAT</th>
                                <th class="{{ $thNumberClass }}">Total Amount Due</th>
                                <th class="{{ $thClass }}">Status</th>
                                <th class="{{ $thClass }}">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($receipts as $receipt)
                                @php
                                    $isCanceled = $receipt->status === \App\Models\SalesInvoice::STATUS_CANCELED;
                                    $editReceiptPayload = [
                                        'id' => (int) $receipt->id,
                                        'invoice_date' => $receipt->invoice_date?->format('Y-m-d'),
                                        'sold_to' => $receipt->sold_to,
                                        'tin' => $receipt->tin,
                                        'terms' => $receipt->terms,
                                        'address' => $receipt->address,
                                        'discount_id_no' => $receipt->discount_id_no,
                                        'signature' => $receipt->signature,
                                        'si_number' => (string) $receipt->si_number,
                                        'vat_exempt_sales' => (float) $receipt->vat_exempt_sales,
                                        'zero_rated_sales' => (float) $receipt->zero_rated_sales,
                                        'sc_pwd_discount' => (float) $receipt->sc_pwd_discount,
                                        'withholding_tax' => (float) $receipt->withholding_tax,
                                        'items' => $receipt->items->map(fn ($item) => [
                                            'item_type' => $item->item_type,
                                            'other_description' => $item->item_type === 'Others' ? $item->description : '',
                                            'quantity' => (float) $item->quantity,
                                            'price' => (float) $item->price,
                                            'amount' => (float) $item->amount,
                                        ])->values()->all(),
                                    ];
                                @endphp
                                <tr class="{{ $isCanceled ? 'bg-error-50/50 dark:bg-error-500/[0.04]' : '' }}">
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex min-w-16 items-center justify-center rounded-lg border px-3 py-1.5 text-base font-black tabular-nums {{ $isCanceled ? 'border-error-200 bg-error-50 text-error-600 line-through dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400' : 'border-brand-200 bg-brand-50 text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400' }}">
                                            {{ $receipt->formattedSiNumber() }}
                                        </span>
                                    </td>
                                    <td class="{{ $tdClass }} whitespace-nowrap">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $receipt->invoice_date?->format('M d, Y') }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $receipt->created_at?->format('h:i A') }}</div>
                                    </td>
                                    <td class="{{ $tdClass }} min-w-72">
                                        <div class="font-semibold text-gray-800 dark:text-white/90">{{ $receipt->sold_to }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">TIN: {{ $receipt->tin }}</div>
                                        <div class="mt-1 max-w-80 text-theme-xs leading-5 text-gray-500 dark:text-gray-400">{{ $receipt->address }}</div>
                                    </td>
                                    <td class="{{ $tdClass }} min-w-48">
                                        @forelse ($receipt->items as $item)
                                            <div class="{{ $loop->first ? '' : 'mt-2 border-t border-gray-100 pt-2 dark:border-gray-800' }} font-medium text-gray-800 dark:text-white/90">{{ $item->description }}</div>
                                        @empty
                                            <span class="text-gray-400">No items</span>
                                        @endforelse
                                    </td>
                                    <td class="{{ $tdNumberClass }}">
                                        @forelse ($receipt->items as $item)
                                            <div class="{{ $loop->first ? '' : 'mt-2 border-t border-gray-100 pt-2 dark:border-gray-800' }}">{{ $quantity($item->quantity) }}</div>
                                        @empty
                                            0
                                        @endforelse
                                    </td>
                                    <td class="{{ $tdNumberClass }}">
                                        @forelse ($receipt->items as $item)
                                            <div class="{{ $loop->first ? '' : 'mt-2 border-t border-gray-100 pt-2 dark:border-gray-800' }}">&#8369;{{ number_format((float) $item->price, 2) }}</div>
                                        @empty
                                            &#8369;0.00
                                        @endforelse
                                    </td>
                                    <td class="{{ $tdNumberClass }} font-semibold">&#8369;{{ number_format((float) $receipt->total_sales_vat_inclusive, 2) }}</td>
                                    <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $receipt->vatable_sales, 2) }}</td>
                                    <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $receipt->vat_amount, 2) }}</td>
                                    <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $receipt->amount_net_of_vat, 2) }}</td>
                                    <td class="{{ $tdNumberClass }} font-bold">&#8369;{{ number_format((float) $receipt->total_amount_due, 2) }}</td>
                                    <td class="{{ $tdClass }} min-w-44">
                                        @if ($isCanceled)
                                            <span class="inline-flex rounded-full bg-error-50 px-2.5 py-1 text-theme-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Canceled</span>
                                            @if ($receipt->canceled_at)
                                                <div class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400">{{ $receipt->canceled_at->format('M d, Y h:i A') }}</div>
                                            @endif
                                            @if ($receipt->canceledBy)
                                                <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">By {{ $receipt->canceledBy->name }}</div>
                                            @endif
                                            @if ($receipt->cancellation_reason)
                                                <div class="mt-1 max-w-52 text-theme-xs leading-5 text-gray-500 dark:text-gray-400">{{ $receipt->cancellation_reason }}</div>
                                            @endif
                                        @else
                                            <span class="inline-flex rounded-full bg-success-50 px-2.5 py-1 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">Active</span>
                                        @endif
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="flex min-w-36 flex-col items-start gap-2">
                                            <button
                                                type="button"
                                                x-on:click="openReceiptEdit(@js($editReceiptPayload))"
                                                class="{{ $smallButtonClass }} whitespace-nowrap">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m14.75 5.25 4 4M5 19l2.75-.5L18.25 8a1.768 1.768 0 0 0-2.5-2.5L5.25 16 5 19Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                Edit Receipt
                                            </button>
                                            @if ($isCanceled)
                                                <span class="text-theme-xs font-medium text-gray-400">Canceled receipt retained</span>
                                            @else
                                                <button
                                                    type="button"
                                                    x-on:click="openCancellation({{ $receipt->id }}, @js((string) $receipt->si_number), @js($receipt->sold_to))"
                                                    class="{{ $dangerButtonClass }} whitespace-nowrap">
                                                    Cancel Receipt
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="px-5 py-12 text-center">
                                        <p class="text-theme-sm font-medium text-gray-800 dark:text-white/90">No official receipts found.</p>
                                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Try clearing or changing the automatic filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($receipts->hasPages())
                <div class="mt-5">{{ $receipts->links() }}</div>
            @endif
        </section>

        @include('pages.staff.partials.sales-invoice-modal', ['editingOfficialReceipt' => true])

        <template x-teleport="body">
            <div x-show="cancellation.open" x-cloak x-on:keydown.escape.window="closeCancellation" class="fixed inset-0 z-[100300] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-950/75 backdrop-blur-sm" x-on:click="closeCancellation"></div>
                <form x-on:submit.prevent="confirmCancellation" class="relative w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8v5M12 16.5h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Cancel Official Receipt</h2>
                            <p class="mt-2 text-theme-sm leading-6 text-gray-500 dark:text-gray-400">
                                SI No. <strong x-text="formatSiNumber(cancellation.siNumber)"></strong> for <strong x-text="cancellation.customer"></strong> will remain in the register, but will be excluded from every financial total.
                            </p>
                        </div>
                    </div>

                    <label class="mt-5 block">
                        <span class="{{ $labelClass }}">Cancellation Reason (Optional)</span>
                        <textarea x-model="cancellation.reason" maxlength="255" rows="3" placeholder="Why is this receipt being canceled?" class="mt-1.5 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-error-300 focus:outline-hidden focus:ring-3 focus:ring-error-500/15 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                    </label>

                    <p x-show="cancellation.error" x-cloak class="mt-3 text-theme-sm text-error-600 dark:text-error-400" x-text="cancellation.error"></p>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" x-on:click="closeCancellation" class="{{ $secondaryButtonClass }}" :disabled="cancellation.loading">Keep Receipt Active</button>
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-error-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60" :disabled="cancellation.loading">
                            <span x-text="cancellation.loading ? 'Canceling...' : 'Cancel Receipt'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </div>
@endsection

@push('scripts')
    <script>
        function officialReceiptsModule(config) {
            return {
                toast: { open: false, type: 'success', message: '', timer: null },
                salesInvoiceModalOpen: false,
                savingSalesInvoice: false,
                salesInvoice: {},
                cancellation: {
                    open: false,
                    id: null,
                    siNumber: '',
                    customer: '',
                    reason: '',
                    error: '',
                    loading: false
                },
                init() {
                    this.resetSalesInvoice();
                    let message = config.flashMessage || '';

                    try {
                        message = window.sessionStorage.getItem('official-receipts-toast') || message;
                        window.sessionStorage.removeItem('official-receipts-toast');
                    } catch (error) {
                        // Session storage can be unavailable in privacy-restricted browsers.
                    }

                    if (message) {
                        this.$nextTick(() => this.showToast(message));
                    }
                },
                blankSalesInvoiceItem() {
                    return {
                        item_type: '',
                        other_description: '',
                        quantity: '',
                        price: '',
                        amount: ''
                    };
                },
                resetSalesInvoice() {
                    this.salesInvoice = {
                        id: null,
                        invoice_date: '',
                        sold_to: '',
                        tin: '',
                        terms: '',
                        address: '',
                        discount_id_no: '',
                        signature: '',
                        si_number: '',
                        authorized_representative: String(config.authorizedRepresentative || ''),
                        vat_exempt_sales: '',
                        zero_rated_sales: '',
                        sc_pwd_discount: '',
                        withholding_tax: '',
                        items: Array.from({ length: 7 }, () => this.blankSalesInvoiceItem())
                    };
                    this.savingSalesInvoice = false;
                },
                openReceiptEdit(receipt) {
                    this.resetSalesInvoice();
                    const items = Array.isArray(receipt.items)
                        ? receipt.items.slice(0, 7).map(item => ({
                            item_type: item.item_type || '',
                            other_description: item.other_description || '',
                            quantity: item.quantity ?? '',
                            price: item.price ?? '',
                            amount: item.amount ?? ''
                        }))
                        : [];

                    while (items.length < 7) {
                        items.push(this.blankSalesInvoiceItem());
                    }

                    this.salesInvoice = {
                        id: receipt.id,
                        invoice_date: receipt.invoice_date || '',
                        sold_to: receipt.sold_to || '',
                        tin: receipt.tin || '',
                        terms: receipt.terms || '',
                        address: receipt.address || '',
                        discount_id_no: receipt.discount_id_no || '',
                        signature: receipt.signature || '',
                        si_number: String(receipt.si_number || ''),
                        authorized_representative: String(config.authorizedRepresentative || ''),
                        vat_exempt_sales: receipt.vat_exempt_sales ?? '',
                        zero_rated_sales: receipt.zero_rated_sales ?? '',
                        sc_pwd_discount: receipt.sc_pwd_discount ?? '',
                        withholding_tax: receipt.withholding_tax ?? '',
                        items
                    };
                    this.salesInvoiceModalOpen = true;
                },
                closeSalesInvoiceModal() {
                    if (this.savingSalesInvoice) {
                        return;
                    }

                    this.salesInvoiceModalOpen = false;
                    this.resetSalesInvoice();
                },
                invoiceNumber(value, precision = 2) {
                    const number = Number(value || 0);

                    if (!Number.isFinite(number) || number < 0) {
                        return 0;
                    }

                    const multiplier = 10 ** precision;

                    return Math.round((number + Number.EPSILON) * multiplier) / multiplier;
                },
                formatSiNumber(value) {
                    const siNumber = String(value ?? '').trim();

                    return siNumber === '' ? '' : siNumber.padStart(3, '0');
                },
                invoiceTotalSales() {
                    return this.invoiceNumber((this.salesInvoice.items || []).reduce((total, item) => {
                        return total + this.invoiceNumber(item.amount);
                    }, 0));
                },
                invoiceLessVat() {
                    return this.invoiceNumber((this.invoiceTotalSales() / 1.12) * 0.12);
                },
                invoiceAmountNetOfVat() {
                    return this.invoiceNumber(this.invoiceTotalSales() - this.invoiceLessVat());
                },
                invoiceVatableSales() {
                    return this.invoiceAmountNetOfVat();
                },
                invoiceVatAmount() {
                    return this.invoiceLessVat();
                },
                invoiceAddVat() {
                    return this.invoiceLessVat();
                },
                invoiceTotalAmountDue() {
                    const amountBeforeWithholdingTax = this.invoiceAmountNetOfVat() + this.invoiceAddVat();
                    const withholdingTax = this.invoiceNumber(this.salesInvoice.withholding_tax);

                    return Math.round((amountBeforeWithholdingTax - withholdingTax + Number.EPSILON) * 100) / 100;
                },
                invoiceMoney(value) {
                    const number = Number(value || 0);
                    const amount = Number.isFinite(number) ? number : 0;

                    return amount.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                },
                invoiceItemHasInput(item) {
                    return ['item_type', 'other_description', 'quantity', 'price', 'amount'].some((field) => {
                        return item[field] !== null && item[field] !== undefined && String(item[field]).trim() !== '';
                    });
                },
                fillSalesInvoiceDefaults() {
                    const defaults = {
                        sold_to: 'No Name Inputted',
                        tin: 'No TIN Inputted',
                        terms: 'No Terms Inputted',
                        address: 'No Address Inputted',
                        discount_id_no: 'No ID Number Inputted',
                        signature: 'No Signature Inputted'
                    };

                    Object.entries(defaults).forEach(([field, fallback]) => {
                        this.salesInvoice[field] = String(this.salesInvoice[field] || '').trim() || fallback;
                    });

                    this.salesInvoice.sold_to = this.salesInvoice.sold_to.toUpperCase();
                    this.salesInvoice.address = this.salesInvoice.address.toUpperCase();

                    this.salesInvoice.items.forEach((item) => {
                        if (item.item_type === 'Others' && !String(item.other_description || '').trim()) {
                            item.other_description = 'No Item Description Inputted';
                        }
                    });
                },
                salesInvoicePayload() {
                    return {
                        invoice_date: this.salesInvoice.invoice_date || null,
                        sold_to: this.salesInvoice.sold_to,
                        tin: this.salesInvoice.tin,
                        terms: this.salesInvoice.terms,
                        address: this.salesInvoice.address,
                        discount_id_no: this.salesInvoice.discount_id_no,
                        signature: this.salesInvoice.signature,
                        vat_exempt_sales: this.invoiceNumber(this.salesInvoice.vat_exempt_sales),
                        zero_rated_sales: this.invoiceNumber(this.salesInvoice.zero_rated_sales),
                        sc_pwd_discount: this.invoiceNumber(this.salesInvoice.sc_pwd_discount),
                        withholding_tax: this.invoiceNumber(this.salesInvoice.withholding_tax),
                        items: this.salesInvoice.items
                            .filter((item) => this.invoiceItemHasInput(item))
                            .map((item) => ({
                                item_type: item.item_type || null,
                                other_description: item.other_description || null,
                                quantity: this.invoiceNumber(item.quantity, 3),
                                price: this.invoiceNumber(item.price),
                                amount: this.invoiceNumber(item.amount)
                            }))
                    };
                },
                async submitSalesInvoice() {
                    if (!this.salesInvoice.id || this.savingSalesInvoice) {
                        return;
                    }

                    this.fillSalesInvoiceDefaults();
                    this.savingSalesInvoice = true;

                    try {
                        const response = await fetch(
                            config.updateUrlTemplate.replace('__RECEIPT__', this.salesInvoice.id),
                            {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrfToken
                                },
                                body: JSON.stringify(this.salesInvoicePayload())
                            }
                        );
                        let data = {};

                        try {
                            data = await response.json();
                        } catch (error) {
                            data = {};
                        }

                        if (!response.ok || !data.success) {
                            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                            this.showToast(firstError || data.message || 'Unable to update this receipt.', 'error');
                            return;
                        }

                        try {
                            window.sessionStorage.setItem('official-receipts-toast', data.message || 'Receipt updated.');
                        } catch (error) {
                            // The register still reloads correctly without a stored toast.
                        }

                        window.location.reload();
                    } catch (error) {
                        this.showToast('An unexpected error occurred while updating the receipt.', 'error');
                    } finally {
                        this.savingSalesInvoice = false;
                    }
                },
                openCancellation(id, siNumber, customer) {
                    this.cancellation = {
                        open: true,
                        id,
                        siNumber,
                        customer,
                        reason: '',
                        error: '',
                        loading: false
                    };
                },
                closeCancellation() {
                    if (this.cancellation.loading) {
                        return;
                    }

                    this.cancellation.open = false;
                },
                async confirmCancellation() {
                    if (!this.cancellation.id || this.cancellation.loading) {
                        return;
                    }

                    this.cancellation.loading = true;
                    this.cancellation.error = '';

                    try {
                        const response = await fetch(
                            config.cancelUrlTemplate.replace('__RECEIPT__', this.cancellation.id),
                            {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrfToken
                                },
                                body: JSON.stringify({ reason: this.cancellation.reason || null })
                            }
                        );
                        let data = {};

                        try {
                            data = await response.json();
                        } catch (error) {
                            data = {};
                        }

                        if (!response.ok || !data.success) {
                            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                            this.cancellation.error = firstError || data.message || 'Unable to cancel this receipt.';
                            return;
                        }

                        try {
                            window.sessionStorage.setItem('official-receipts-toast', data.message || 'Receipt canceled.');
                        } catch (error) {
                            // The register still reloads correctly without a stored toast.
                        }

                        window.location.reload();
                    } catch (error) {
                        this.cancellation.error = 'An unexpected error occurred while canceling the receipt.';
                    } finally {
                        this.cancellation.loading = false;
                    }
                },
                showToast(message, type = 'success') {
                    this.closeToast();
                    this.toast.message = message;
                    this.toast.type = type;
                    this.toast.open = true;
                    this.toast.timer = window.setTimeout(() => this.closeToast(), 4000);
                },
                closeToast() {
                    if (this.toast.timer) {
                        window.clearTimeout(this.toast.timer);
                    }

                    this.toast = { open: false, type: 'success', message: '', timer: null };
                }
            };
        }
    </script>
@endpush
