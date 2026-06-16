<div x-show="modal === 'view'" x-cloak x-on:keydown.escape.window="closeModal()" class="fixed inset-0 z-[100010] flex items-center justify-center overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" x-on:click="closeModal()"></div>
    <div class="relative w-full max-w-3xl rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
        <button type="button" x-on:click="closeModal()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
        </button>

        <div class="border-b border-gray-100 p-5 dark:border-gray-800 md:p-6">
            <p class="{{ $labelClass }}">Customer Profile</p>
            <h2 class="mt-1 pr-10 text-xl font-semibold text-gray-800 dark:text-white/90" x-text="selectedCustomer?.business_name || 'Customer'"></h2>
            <p class="mt-1 {{ $captionClass }}" x-text="selectedCustomer?.owner_name || 'No owner name'"></p>
        </div>

        <div class="max-h-[78vh] overflow-y-auto p-5 custom-scrollbar md:p-6">
            <template x-if="selectedCustomer">
                <div class="space-y-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Credit Limit</p>
                            <p class="mt-2 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatMoney(selectedCustomer.credit_limit)"></p>
                        </article>
                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Current Balance</p>
                            <p class="mt-2 text-lg font-semibold tabular-nums" :class="customerBalanceClass(selectedCustomer)" x-text="formatBalance(selectedCustomer.current_balance)"></p>
                        </article>
                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Terms</p>
                            <p class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90" x-text="selectedCustomer.payment_terms || 'N/A'"></p>
                        </article>
                    </div>

                    @if ($canManageCredit)
                        <button type="button" x-on:click="openPaymentModal()" class="w-full {{ $primaryButtonClass }}">
                            Record Payment
                        </button>
                    @endif

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Diesel Special</p>
                            <p class="mt-2 font-semibold text-gray-800 dark:text-white/90" x-text="specialPrice(selectedCustomer.sp_diesel)"></p>
                        </article>
                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Unleaded Special</p>
                            <p class="mt-2 font-semibold text-gray-800 dark:text-white/90" x-text="specialPrice(selectedCustomer.sp_unleaded)"></p>
                        </article>
                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Premium Special</p>
                            <p class="mt-2 font-semibold text-gray-800 dark:text-white/90" x-text="specialPrice(selectedCustomer.sp_premium)"></p>
                        </article>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<div x-show="modal === 'customer-form'" x-cloak x-on:keydown.escape.window="closeModal()" class="fixed inset-0 z-[100010] flex items-center justify-center overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" x-on:click="closeModal()"></div>
    <div class="relative w-full max-w-4xl rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
        <button type="button" x-on:click="closeModal()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
        </button>

        <div class="border-b border-gray-100 p-5 dark:border-gray-800 md:p-6">
            <p class="{{ $labelClass }}" x-text="formMode === 'edit' ? 'Edit Profile' : 'New Customer'"></p>
            <h2 class="mt-1 pr-10 text-xl font-semibold text-gray-800 dark:text-white/90" x-text="formMode === 'edit' ? 'Edit Credit Customer' : 'Add Credit Customer'"></h2>
        </div>

        <form x-on:submit.prevent="submitCustomer()" class="max-h-[78vh] overflow-y-auto p-5 custom-scrollbar md:p-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Business Name</span>
                    <input type="text" x-model="customerForm.business_name" class="mt-1.5 {{ $inputClass }}" required>
                </label>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Owner / Contact Name</span>
                    <input type="text" x-model="customerForm.owner_name" class="mt-1.5 {{ $inputClass }}">
                </label>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Contact Number</span>
                    <input type="text" x-model="customerForm.contact_number" class="mt-1.5 {{ $inputClass }}">
                </label>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Credit Limit</span>
                        <input type="number" min="0" step="0.01" x-model="customerForm.credit_limit" class="mt-1.5 {{ $inputClass }}" required>
                    </label>
                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Terms</span>
                        <select x-model="customerForm.payment_terms" class="mt-1.5 {{ $selectClass }}">
                            <option>3 Days</option>
                            <option>7 Days</option>
                            <option>15 Days</option>
                            <option>30 Days</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Special Wholesale Pricing</h3>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Diesel Special</span>
                        <input type="number" min="0" step="0.01" x-model="customerForm.sp_diesel" class="mt-1.5 {{ $inputClass }}" placeholder="Standard">
                    </label>
                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Unleaded Special</span>
                        <input type="number" min="0" step="0.01" x-model="customerForm.sp_unleaded" class="mt-1.5 {{ $inputClass }}" placeholder="Standard">
                    </label>
                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Premium Special</span>
                        <input type="number" min="0" step="0.01" x-model="customerForm.sp_premium" class="mt-1.5 {{ $inputClass }}" placeholder="Standard">
                    </label>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" x-on:click="closeModal()" class="{{ $secondaryButtonClass }}">Cancel</button>
                <button type="submit" class="{{ $primaryButtonClass }}" :disabled="savingCustomer">
                    <span x-text="savingCustomer ? 'Saving...' : (formMode === 'edit' ? 'Update Profile' : 'Save Customer')"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<div x-show="modal === 'payment'" x-cloak x-on:keydown.escape.window="closeModal()" class="fixed inset-0 z-[100010] flex items-center justify-center overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" x-on:click="closeModal()"></div>
    <div class="relative w-full max-w-4xl rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
        <button type="button" x-on:click="closeModal()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
        </button>

        <div class="border-b border-gray-100 p-5 dark:border-gray-800 md:p-6">
            <p class="{{ $labelClass }}">Receive Payment</p>
            <h2 class="mt-1 pr-10 text-xl font-semibold text-gray-800 dark:text-white/90" x-text="selectedCustomer?.label || 'Customer Payment'"></h2>
        </div>

        <form x-on:submit.prevent="submitPayment()" class="max-h-[78vh] overflow-y-auto p-5 custom-scrollbar md:p-6">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="{{ $labelClass }}">Current Balance</p>
                        <p class="mt-2 text-xl font-semibold tabular-nums" :class="customerBalanceClass(selectedCustomer)" x-text="formatBalance(selectedCustomer?.current_balance || 0)"></p>
                    </div>
                    <button type="button" x-on:click="payMax()" class="{{ $secondaryButtonClass }}">Max</button>
                </div>
            </div>

            <div class="mt-5 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_auto_auto] lg:items-end">
                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Pay Against Invoice</span>
                        <select x-model="selectedInvoiceId" x-on:change="onInvoiceSelected()" class="mt-1.5 {{ $selectClass }}">
                            <option value="">Manual Payment (No Invoice)</option>
                            <template x-if="invoiceLoading">
                                <option disabled>Loading invoices...</option>
                            </template>
                            <template x-for="invoice in invoices" :key="invoice.id">
                                <option x-bind:value="invoice.id" x-text="invoice.invoice_number + ' - Remaining: ' + formatMoney(invoiceRemaining(invoice)) + (invoice.status === 'Partial' ? ' (Partial)' : '')"></option>
                            </template>
                        </select>
                    </label>
                    <button type="button" x-on:click="setPaymentType('partial')" class="{{ $secondaryButtonClass }}" :class="paymentType === 'partial' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : ''">Partial</button>
                    <button type="button" x-on:click="setPaymentType('full')" class="{{ $secondaryButtonClass }}" :class="paymentType === 'full' ? 'border-success-500 text-success-600 dark:text-success-500' : ''">Full</button>
                </div>

                <template x-if="currentInvoice()">
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.03]">
                            <p class="{{ $labelClass }}">Invoice</p>
                            <p class="mt-1 font-semibold text-gray-800 dark:text-white/90" x-text="currentInvoice().invoice_number"></p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.03]">
                            <p class="{{ $labelClass }}">Status</p>
                            <p class="mt-1 font-semibold text-gray-800 dark:text-white/90" x-text="currentInvoice().status"></p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.03]">
                            <p class="{{ $labelClass }}">Amount Due</p>
                            <p class="mt-1 font-semibold text-gray-800 dark:text-white/90" x-text="formatMoney(invoiceRemaining(currentInvoice()))"></p>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Payment Amount</span>
                    <input type="number" min="1" step="0.01" x-model="paymentForm.amount" class="mt-1.5 {{ $inputClass }}" required>
                    <span x-show="paymentAdvanceAmount() > 0" x-cloak class="mt-1 block text-theme-xs font-medium text-blue-light-700 dark:text-blue-light-400">
                        Includes advance credit of <span x-text="formatMoney(paymentAdvanceAmount())"></span>.
                    </span>
                </label>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Payment Date</span>
                    <span class="relative mt-1.5 block custom-datepicker">
                        <input type="text" x-model="paymentForm.payment_date" class="{{ $dateInputClass }}" data-customer-datepicker required>
                        {!! $dateIcon !!}
                    </span>
                </label>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Method</span>
                    <select x-model="paymentForm.payment_method" class="mt-1.5 {{ $selectClass }}">
                        <option>Cash</option>
                        <option>Check</option>
                        <option>Bank Transfer</option>
                        <option>GCash/E-Wallet</option>
                    </select>
                </label>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Reference No.</span>
                    <input type="text" x-model="paymentForm.reference_no" class="mt-1.5 {{ $inputClass }}" :readonly="!!currentInvoice()">
                </label>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400 md:col-span-2">
                    <span>Remarks</span>
                    <textarea x-model="paymentForm.remarks" rows="3" class="mt-1.5 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                </label>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" x-on:click="closeModal()" class="{{ $secondaryButtonClass }}">Cancel</button>
                <button type="submit" class="{{ $primaryButtonClass }}" :disabled="savingPayment">
                    <span x-text="savingPayment ? 'Recording...' : 'Confirm Payment'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<div x-show="modal === 'inactive'" x-cloak x-on:keydown.escape.window="closeModal()" class="fixed inset-0 z-[100010] flex items-center justify-center overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" x-on:click="closeModal()"></div>
    <div class="relative w-full max-w-6xl rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
        <button type="button" x-on:click="closeModal()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
        </button>

        <div class="border-b border-gray-100 p-5 dark:border-gray-800 md:p-6">
            <p class="{{ $labelClass }}">Inactive Accounts</p>
            <h2 class="mt-1 pr-10 text-xl font-semibold text-gray-800 dark:text-white/90">Inactive Customers</h2>
        </div>

        <div class="max-h-[78vh] overflow-y-auto p-5 custom-scrollbar md:p-6">
            <label class="mb-4 block max-w-md text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                <span>Search inactive customers</span>
                <input type="search" x-model.debounce.150ms="inactiveSearch" class="mt-1.5 {{ $inputClass }}" placeholder="Business, owner, or contact">
            </label>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[920px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[26%]">
                            <col class="w-[24%]">
                            <col class="w-[16%]">
                            <col class="w-[18%]">
                            <col class="w-[16%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass ?? 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400' }}">Business Name</th>
                                <th class="{{ $thClass ?? 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400' }}">Owner / Contact</th>
                                <th class="{{ $thNumberClass ?? 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400' }}">Credit Limit</th>
                                <th class="{{ $thNumberClass ?? 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400' }}">Balance</th>
                                <th class="{{ $thClass ?? 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400' }}">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($inactiveCustomers as $customer)
                                @php
                                    $balance = $balanceMeta($customer);
                                @endphp
                                <tr x-show="customerMatches('{{ $customer->id }}', inactiveSearch)">
                                    <td class="{{ $tdPrimaryClass ?? 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90' }}">{{ strtoupper($customer->business_name ?? 'N/A') }}</td>
                                    <td class="{{ $tdClass ?? 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300' }}">
                                        <div>{{ $customer->owner_name ? ucwords(strtolower($customer->owner_name)) : 'N/A' }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $customer->contact_number ?: 'N/A' }}</div>
                                    </td>
                                    <td class="{{ $tdNumberClass ?? 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300' }}">&#8369;{{ number_format((float) $customer->credit_limit, 2) }}</td>
                                    <td class="{{ $tdNumberClass ?? 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300' }} font-semibold {!! $balance['class'] !!}">{!! $balance['label'] !!}</td>
                                    <td class="{{ $tdClass ?? 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300' }}">
                                        <div class="flex items-center gap-2">
                                            <button type="button" x-on:click="openCustomer('{{ $customer->id }}', 'view')" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" title="View Details">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" stroke="currentColor" stroke-width="1.8"/></svg>
                                            </button>
                                            @if ($canManageCredit)
                                                <button type="button" x-on:click="openForm('edit', '{{ $customer->id }}')" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" title="Edit Profile">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m14.25 5.25 4.5 4.5M4 20h4.5L19.25 9.25a3.18 3.18 0 0 0-4.5-4.5L4 15.5V20Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </button>
                                                <button type="button" x-on:click="toggleStatus('{{ $customer->id }}', 'Active')" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-success-600 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-success-500" title="Reactivate Account">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM16 11l2 2 4-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No inactive customers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
