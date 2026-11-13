@extends('layouts.app')

@section('content')
    @php
        $customers = collect($customers ?? []);
        $customerPaginator = $customerPaginator ?? null;
        $hasCustomerPaginator = is_object($customerPaginator) && method_exists($customerPaginator, 'items');
        $customerRows = $hasCustomerPaginator ? collect($customerPaginator->items()) : collect($customerRows ?? $customers);
        $inactiveCustomers = collect($inactiveCustomers ?? []);
        $adminPayments = collect($adminPayments ?? []);
        $summary = array_merge([
            'active_count' => 0,
            'inactive_count' => 0,
            'credit_limit' => 0,
            'receivables' => 0,
            'advance_balance' => 0,
            'over_limit_count' => 0,
        ], $summary ?? []);
        $missingCustomerTables = $missingCustomerTables ?? [];
        $canManageCredit = $canManageCredit ?? false;
        $activeTab = in_array(request('tab'), ['list', 'ledger', 'payments'], true) ? request('tab') : 'list';
        $activeTab = $activeTab === 'payments' && ! $canManageCredit ? 'list' : $activeTab;
        $allCustomers = $customers->concat($inactiveCustomers)->unique('id')->values();
        $customerSearch = trim((string) ($customerSearch ?? ''));
        $perPageOptions = $perPageOptions ?? [10, 20, 50, 100];
        $perPage = (int) ($perPage ?? 10);
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 10;
        $customerTotal = is_object($customerPaginator) && method_exists($customerPaginator, 'total') ? $customerPaginator->total() : $customerRows->count();
        $customerFirstItem = is_object($customerPaginator) && method_exists($customerPaginator, 'firstItem') ? $customerPaginator->firstItem() : ($customerTotal > 0 ? 1 : null);
        $customerLastItem = is_object($customerPaginator) && method_exists($customerPaginator, 'lastItem') ? $customerPaginator->lastItem() : $customerRows->count();

        $customerLabel = function ($customer) {
            $businessName = trim((string) ($customer->business_name ?? ''));
            $ownerName = trim((string) ($customer->owner_name ?? ''));
            $hasBusinessName = $businessName !== '' && strtoupper($businessName) !== 'N/A';
            $hasOwnerName = $ownerName !== '' && strtoupper($ownerName) !== 'N/A';
            $label = $hasBusinessName ? strtoupper($businessName) : '';

            if ($hasOwnerName && (! $hasBusinessName || strcasecmp($businessName, $ownerName) !== 0)) {
                $ownerLabel = $ownerName;
                $label = $label ? "{$label} - {$ownerLabel}" : $ownerLabel;
            }

            return $label ?: 'Customer #' . $customer->id;
        };

        $balanceMeta = function ($customer) {
            $balance = (float) ($customer->current_balance ?? 0);
            $limit = (float) ($customer->credit_limit ?? 0);

            if ($balance < 0) {
                return [
                    'label' => 'Advance: &#8369;' . number_format(abs($balance), 2),
                    'class' => 'text-blue-light-700 dark:text-blue-light-400',
                    'status' => 'Advance',
                    'badge' => 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400',
                ];
            }

            if ($limit > 0 && $balance > $limit) {
                return [
                    'label' => '&#8369;' . number_format($balance, 2),
                    'class' => 'text-error-600 dark:text-error-400',
                    'status' => 'Over Limit',
                    'badge' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                ];
            }

            return [
                'label' => '&#8369;' . number_format($balance, 2),
                'class' => 'text-success-600 dark:text-success-500',
                'status' => 'Active',
                'badge' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
            ];
        };

        $customerPayload = $allCustomers->map(function ($customer) use ($customerLabel) {
            $label = $customerLabel($customer);

            return [
                'id' => (int) $customer->id,
                'business_name' => $customer->business_name ?? '',
                'owner_name' => $customer->owner_name ?? '',
                'first_name' => $customer->first_name ?? '',
                'middle_name' => $customer->middle_name ?? '',
                'last_name' => $customer->last_name ?? '',
                'suffix' => $customer->suffix ?? '',
                'contact_number' => $customer->contact_number ?? '',
                'credit_limit' => (float) ($customer->credit_limit ?? 0),
                'current_balance' => (float) ($customer->current_balance ?? 0),
                'payment_terms' => $customer->payment_terms ?? '15 Days',
                'sp_diesel' => $customer->sp_diesel,
                'sp_unleaded' => $customer->sp_unleaded,
                'sp_premium' => $customer->sp_premium,
                'status' => ($customer->status ?? 'Active') ?: 'Active',
                'label' => $label,
                'search' => strtolower($label . ' ' . ($customer->contact_number ?? '')),
            ];
        })->values();

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $dateInputClass = 'h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pl-4 pr-11 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $selectClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $dangerButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60';
        $iconButtonClass = 'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $viewIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 shadow-theme-xs transition hover:border-brand-300 hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/15';
        $editIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-warning-200 bg-warning-50 text-warning-700 shadow-theme-xs transition hover:border-warning-300 hover:bg-warning-100 dark:border-warning-500/25 dark:bg-warning-500/10 dark:text-warning-400 dark:hover:bg-warning-500/15';
        $deactivateIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-error-200 bg-error-50 text-error-600 shadow-theme-xs transition hover:border-error-300 hover:bg-error-100 dark:border-error-500/25 dark:bg-error-500/10 dark:text-error-400 dark:hover:bg-error-500/15';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
        $dateIcon = '<span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="size-6"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z" fill="currentColor"></path></svg></span>';
    @endphp

    <x-common.page-breadcrumb pageTitle="Customers & Credit" />

    <div
        x-data="customerCreditModule({
            activeTab: @js($activeTab),
            canManageCredit: @js($canManageCredit),
            customers: @js($customerPayload),
            csrfToken: @js(csrf_token()),
            storeUrl: @js(route('customers.store')),
            updateUrl: @js(route('customers.update')),
            payUrl: @js(route('customers.pay')),
            voidPaymentUrl: @js(route('customers.voidPayment')),
            toggleStatusUrl: @js(route('customers.toggleStatus')),
            pricingHistoryBaseUrl: @js(url('/customers')),
            ledgerBaseUrl: @js(url('/customers/ledger')),
            printLedgerBaseUrl: @js(url('/customers/print-ledger')),
            unpaidBaseUrl: @js(url('/customers')),
            soaUrl: @js(route('customers.soa'))
        })"
        x-init="init()"
        class="space-y-6">
        <style>[x-cloak] { display: none !important; }</style>

        @if ($missingCustomerTables)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Missing customer table(s): {{ implode(', ', $missingCustomerTables) }}.
            </div>
        @endif

        <div
            x-show="toast.open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="fixed right-4 w-[calc(100%-2rem)] max-w-sm rounded-xl border border-white/10 p-4 text-white shadow-theme-lg sm:right-6"
            style="top: 5.5rem; z-index: 100300; background-color: #047857;"
            role="status"
            aria-live="polite">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15 text-white">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6.75 12.25 3.25 3.25 7.25-7.25" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-theme-sm font-semibold text-white">Success</p>
                    <p class="mt-1 text-theme-sm text-white/90" x-text="toast.message"></p>
                </div>
                <button type="button" x-on:click="closeToast()" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white/80 transition hover:bg-white/10 hover:text-white" aria-label="Close success message">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>
        </div>

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Customers & Credit</h1>
                </div>

                <div class="flex flex-wrap gap-2 xl:justify-end">
                    <button type="button" x-on:click="openInactive()" class="{{ $secondaryButtonClass }}">
                        Inactive Customers
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $inactiveCustomers->count() }}</span>
                    </button>
                    @if ($canManageCredit)
                        <button type="button" x-on:click="openForm('add')" class="{{ $primaryButtonClass }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
                            Add Credit Customer
                        </button>
                    @endif
                </div>
            </div>
        </section>

        <section class="{{ $cardClass }} p-2 md:p-2">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <button type="button" x-on:click="switchTab('list')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'list' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Customer List & Balances
                </button>
                <button type="button" x-on:click="switchTab('ledger')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'ledger' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Credit Ledger
                </button>
                @if ($canManageCredit)
                    <button type="button" x-on:click="switchTab('payments')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                        :class="activeTab === 'payments' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                        Admin Payment History
                    </button>
                @endif
            </div>
        </section>

        <section x-show="activeTab === 'list'" x-cloak class="{{ $cardClass }}">
            <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(260px,380px)] lg:items-end">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Customer List & Balances</h2>
                </div>
                <form method="GET" action="{{ route('customers.index') }}" class="w-full">
                    <input type="hidden" name="tab" value="list">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Search customers</span>
                        <input type="search" name="customer_search" value="{{ $customerSearch }}" class="mt-1.5 {{ $inputClass }}" placeholder="Business, owner, or contact">
                    </label>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[1040px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[24%]">
                            <col class="w-[17%]">
                            <col class="w-[13%]">
                            <col class="w-[17%]">
                            <col class="w-[10%]">
                            <col class="w-[19%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Business Name</th>
                                <th class="{{ $thClass }}">Owner / Contact</th>
                                <th class="{{ $thNumberClass }}">Credit Limit</th>
                                <th class="{{ $thNumberClass }}">Current Balance</th>
                                <th class="{{ $thClass }}">Status</th>
                                <th class="{{ $thClass }}">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($customerRows as $customer)
                                @php
                                    $balance = $balanceMeta($customer);
                                @endphp
                                <tr>
                                    <td class="{{ $tdPrimaryClass }}">{{ strtoupper($customer->business_name ?? 'N/A') }}</td>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $customer->owner_name ?: 'N/A' }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $customer->contact_number ?: 'N/A' }}</div>
                                    </td>
                                    <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $customer->credit_limit, 2) }}</td>
                                    <td class="{{ $tdNumberClass }} font-semibold {!! $balance['class'] !!}">{!! $balance['label'] !!}</td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-theme-xs font-medium {{ $balance['badge'] }}">{{ $balance['status'] }}</span>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="flex min-w-[9.5rem] items-center gap-3">
                                            <button type="button" x-on:click="openCustomer('{{ $customer->id }}', 'view')" class="{{ $viewIconButtonClass }}" title="View Details" aria-label="View customer details">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" stroke="currentColor" stroke-width="1.8"/></svg>
                                            </button>
                                            @if ($canManageCredit)
                                                <button type="button" x-on:click="openForm('edit', '{{ $customer->id }}')" class="{{ $editIconButtonClass }}" title="Edit Profile" aria-label="Edit customer profile">
                                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m14.25 5.25 4.5 4.5M4 20h4.5L19.25 9.25a3.18 3.18 0 0 0-4.5-4.5L4 15.5V20Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </button>
                                                <button type="button" x-on:click="toggleStatus('{{ $customer->id }}', 'Inactive')" class="{{ $deactivateIconButtonClass }}" title="Deactivate Account" aria-label="Deactivate customer account">
                                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM17 8l5 5M22 8l-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">{{ $customerSearch !== '' ? 'No active customers match your search.' : 'No active credit customers found.' }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4 dark:border-gray-800 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p class="{{ $captionClass }}">
                        @if ($customerTotal > 0 && $customerFirstItem)
                            Showing {{ number_format($customerFirstItem) }}-{{ number_format($customerLastItem) }} of {{ number_format($customerTotal) }} customer{{ $customerTotal === 1 ? '' : 's' }}.
                        @else
                            No customers found.
                        @endif
                    </p>
                    <form method="GET" action="{{ route('customers.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="list">
                        @if ($customerSearch !== '')
                            <input type="hidden" name="customer_search" value="{{ $customerSearch }}">
                        @endif
                        <label for="customer-list-per-page" class="text-theme-sm text-gray-500 dark:text-gray-400">Rows</label>
                        <select id="customer-list-per-page" name="per_page" class="h-9 w-20 rounded-lg border border-gray-300 bg-transparent px-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800" onchange="this.form.submit()">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}" {{ (int) $perPage === (int) $option ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if (is_object($customerPaginator) && method_exists($customerPaginator, 'hasPages') && $customerPaginator->hasPages())
                    @php
                        $customerCurrentPage = method_exists($customerPaginator, 'currentPage') ? (int) $customerPaginator->currentPage() : 1;
                        $customerLastPageNumber = method_exists($customerPaginator, 'lastPage') ? (int) $customerPaginator->lastPage() : $customerCurrentPage;
                        $customerPageNumbers = collect([1, $customerCurrentPage - 1, $customerCurrentPage, $customerCurrentPage + 1, $customerLastPageNumber])
                            ->filter(fn ($page) => $page >= 1 && $page <= $customerLastPageNumber)
                            ->unique()
                            ->sort()
                            ->values();
                        $previousCustomerPage = null;
                    @endphp
                    <nav class="flex flex-wrap items-center gap-2 lg:justify-end" role="navigation" aria-label="Customer pagination">
                        @if ($customerPaginator->onFirstPage())
                            <span class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-theme-sm font-medium text-gray-400 dark:border-gray-800 dark:text-gray-600">Prev</span>
                        @else
                            <a href="{{ $customerPaginator->previousPageUrl() }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">Prev</a>
                        @endif

                        @foreach ($customerPageNumbers as $page)
                            @if ($previousCustomerPage && $page > $previousCustomerPage + 1)
                                <span class="inline-flex h-9 min-w-9 items-center justify-center px-1 text-theme-sm text-gray-400 dark:text-gray-600">...</span>
                            @endif

                            @if ($page === $customerCurrentPage)
                                <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-brand-500 px-3 text-theme-sm font-medium text-white" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $customerPaginator->url($page) }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">{{ $page }}</a>
                            @endif

                            @php $previousCustomerPage = $page; @endphp
                        @endforeach

                        @if ($customerPaginator->hasMorePages())
                            <a href="{{ $customerPaginator->nextPageUrl() }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">Next</a>
                        @else
                            <span class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-theme-sm font-medium text-gray-400 dark:border-gray-800 dark:text-gray-600">Next</span>
                        @endif
                    </nav>
                @endif
            </div>
        </section>

        <section x-show="activeTab === 'ledger'" x-cloak class="{{ $cardClass }}">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Credit Ledger</h2>
                <p class="mt-1 {{ $captionClass }}">Review transaction history by customer and period.</p>
            </div>

            <div class="mb-5 grid grid-cols-1 gap-3 xl:grid-cols-[minmax(280px,1fr)_180px_180px_auto_auto_auto_auto] xl:items-end">
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Select Customer Ledger</span>
                    <select x-model="ledger.customerId" x-on:change="fetchLedger()" class="mt-1.5 {{ $selectClass }}">
                        <option value="">Select a customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customerLabel($customer) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>From</span>
                    <span class="relative mt-1.5 block custom-datepicker">
                        <input type="text" x-model="ledger.fromDate" class="{{ $dateInputClass }}" placeholder="Select date" autocomplete="off" data-customer-datepicker>
                        {!! $dateIcon !!}
                    </span>
                </label>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>To</span>
                    <span class="relative mt-1.5 block custom-datepicker">
                        <input type="text" x-model="ledger.toDate" class="{{ $dateInputClass }}" placeholder="Select date" autocomplete="off" data-customer-datepicker>
                        {!! $dateIcon !!}
                    </span>
                </label>
                <button type="button" x-on:click="fetchLedger()" class="{{ $primaryButtonClass }}">Filter</button>
                <button type="button" x-on:click="clearLedgerFilters()" class="{{ $secondaryButtonClass }}">Clear</button>
                <button type="button" x-on:click="printLedger()" class="{{ $secondaryButtonClass }}">Print</button>
                <button type="button" x-on:click="openSoa()" class="{{ $secondaryButtonClass }}">SOA</button>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-h-[620px] max-w-full overflow-auto custom-scrollbar">
                    <table class="min-w-[980px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[17%]">
                            <col class="w-[10%]">
                            <col class="w-[13%]">
                            <col class="w-[28%]">
                            <col class="w-[10%]">
                            <col class="w-[10%]">
                            <col class="w-[12%]">
                        </colgroup>
                        <thead class="sticky top-0 z-1 border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                            <tr>
                                <th class="{{ $thClass }}">Date</th>
                                <th class="{{ $thClass }}">Type</th>
                                <th class="{{ $thClass }}">Ref #</th>
                                <th class="{{ $thClass }}">Description</th>
                                <th class="{{ $thNumberClass }}">Debit</th>
                                <th class="{{ $thNumberClass }}">Credit</th>
                                <th class="{{ $thNumberClass }}">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-if="!ledger.customerId && !ledger.loading">
                                <tr><td colspan="7" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">Select a customer to view ledger history.</td></tr>
                            </template>
                            <template x-if="ledger.loading">
                                <tr><td colspan="7" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">Loading ledger...</td></tr>
                            </template>
                            <template x-if="ledger.error">
                                <tr><td colspan="7" class="px-5 py-8 text-center text-theme-sm text-error-600 dark:text-error-400" x-text="ledger.error"></td></tr>
                            </template>
                            <template x-if="ledger.customerId && !ledger.loading && !ledger.error && ledger.rows.length === 0">
                                <tr><td colspan="7" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No transactions found.</td></tr>
                            </template>
                            <template x-for="entry in ledger.rows" :key="entry.id">
                                <tr>
                                    <td class="{{ $tdClass }}" x-text="entry.transaction_datetime || entry.date_display"></td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-theme-xs font-medium"
                                            :class="entry.type === 'Payment' ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500' : 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400'"
                                            x-text="entry.type"></span>
                                    </td>
                                    <td class="{{ $tdClass }}" x-text="entry.reference_no || '-'"></td>
                                    <td class="{{ $tdClass }}" x-text="entry.description || '-'"></td>
                                    <td class="{{ $tdNumberClass }} text-error-600 dark:text-error-400" x-text="entry.debit > 0 ? formatMoney(entry.debit) : '-'"></td>
                                    <td class="{{ $tdNumberClass }} text-success-600 dark:text-success-500" x-text="entry.credit > 0 ? formatMoney(entry.credit) : '-'"></td>
                                    <td class="{{ $tdNumberClass }} font-semibold" :class="entry.running_balance < 0 ? 'text-blue-light-700 dark:text-blue-light-400' : 'text-gray-800 dark:text-white/90'" x-text="formatBalance(entry.running_balance)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if ($canManageCredit)
            <section x-show="activeTab === 'payments'" x-cloak class="{{ $cardClass }}">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Admin Payment History</h2>
                    <p class="mt-1 {{ $captionClass }}">Manual customer payments that can be voided by admin.</p>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-h-[620px] max-w-full overflow-auto custom-scrollbar">
                        <table class="min-w-[980px] w-full table-fixed">
                            <colgroup>
                                <col class="w-[16%]">
                                <col class="w-[23%]">
                                <col class="w-[14%]">
                                <col class="w-[31%]">
                                <col class="w-[10%]">
                                <col class="w-[6%]">
                            </colgroup>
                            <thead class="sticky top-0 z-1 border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                                <tr>
                                    <th class="{{ $thClass }}">Date Logged</th>
                                    <th class="{{ $thClass }}">Customer</th>
                                    <th class="{{ $thClass }}">Ref No.</th>
                                    <th class="{{ $thClass }}">Description</th>
                                    <th class="{{ $thNumberClass }}">Amount</th>
                                    <th class="{{ $thClass }}">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($adminPayments as $payment)
                                    <tr>
                                        <td class="{{ $tdClass }}">{{ $payment->created_at ? \Carbon\Carbon::parse($payment->created_at)->format('M d, Y h:i A') : 'N/A' }}</td>
                                        <td class="{{ $tdPrimaryClass }}">{{ $payment->business_name && strtoupper($payment->business_name) !== 'N/A' ? strtoupper($payment->business_name) : ($payment->owner_name ?? 'Customer') }}</td>
                                        <td class="{{ $tdClass }}">{{ $payment->reference_no ?: '-' }}</td>
                                        <td class="{{ $tdClass }}">{{ $payment->description ?: '-' }}</td>
                                        <td class="{{ $tdNumberClass }} font-semibold text-success-600 dark:text-success-500">&#8369;{{ number_format((float) $payment->credit, 2) }}</td>
                                        <td class="{{ $tdClass }}">
                                            <button type="button" x-on:click="voidPayment('{{ $payment->id }}')" class="{{ $iconButtonClass }} hover:text-error-600" title="Void Payment">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No admin payment records found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        @include('pages.admin.partials.customer-credit-modals', [
            'inputClass' => $inputClass,
            'dateInputClass' => $dateInputClass,
            'selectClass' => $selectClass,
            'primaryButtonClass' => $primaryButtonClass,
            'secondaryButtonClass' => $secondaryButtonClass,
            'dangerButtonClass' => $dangerButtonClass,
            'cardClass' => $cardClass,
            'labelClass' => $labelClass,
            'captionClass' => $captionClass,
            'dateIcon' => $dateIcon,
            'canManageCredit' => $canManageCredit,
        ])
    </div>
@endsection

@push('scripts')
    <script>
        function customerCreditModule(config) {
            return {
                activeTab: config.activeTab || 'list',
                customers: config.customers || [],
                customersById: {},
                customerSearch: '',
                inactiveSearch: '',
                modal: null,
                formMode: 'add',
                selectedCustomer: null,
                pricingHistory: { customerId: null, rows: [], loading: false, error: '' },
                pricingHistoryRequestId: 0,
                toast: { open: false, message: '', timer: null },
                messageDialog: { open: false, type: 'error', title: '', message: '' },
                confirmation: {
                    open: false,
                    title: '',
                    message: '',
                    details: '',
                    confirmText: 'Confirm',
                    cancelText: 'Cancel',
                    tone: 'brand',
                    resolver: null
                },
                reloadTimer: null,
                feedbackClosing: false,
                savingCustomer: false,
                savingPayment: false,
                ledger: { customerId: '', fromDate: '', toDate: '', rows: [], loading: false, error: '' },
                customerForm: {},
                legacyCustomerName: false,
                useNameComponents: true,
                paymentForm: {},
                invoices: [],
                invoiceLoading: false,
                selectedInvoiceId: '',
                paymentType: 'partial',

                init() {
                    this.customersById = Object.fromEntries(this.customers.map(customer => [String(customer.id), customer]));
                    this.resetCustomerForm();
                    this.resetPaymentForm();
                    this.initDatePickers();
                    this.showStoredToast();
                },

                initDatePickers() {
                    if (!window.flatpickr) return;

                    document.querySelectorAll('[data-customer-datepicker]').forEach(input => {
                        if (input._customerDatePicker) return;

                        input._customerDatePicker = window.flatpickr(input, {
                            static: true,
                            monthSelectorType: 'static',
                            dateFormat: 'Y-m-d',
                            defaultDate: input.value || null,
                            allowInput: false,
                            disableMobile: true,
                            onChange() {
                                input.dispatchEvent(new Event('input'));
                            }
                        });
                    });
                },

                switchTab(tab) {
                    if (tab === 'payments' && !config.canManageCredit) return;
                    this.activeTab = tab;
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', tab);
                    window.history.replaceState({}, '', url);
                },

                customerMatches(id, term) {
                    const customer = this.customersById[String(id)];
                    const value = String(term || '').toLowerCase().trim();
                    return !value || (customer && customer.search.includes(value));
                },

                openCustomer(id, mode) {
                    this.selectedCustomer = this.customersById[String(id)] || null;
                    if (!this.selectedCustomer) return;
                    if (mode === 'edit') {
                        this.openForm('edit', id);
                        return;
                    }
                    this.modal = 'view';
                    this.fetchPricingHistory(this.selectedCustomer.id);
                },

                async fetchPricingHistory(customerId) {
                    const requestId = ++this.pricingHistoryRequestId;
                    const history = { customerId, rows: [], loading: true, error: '' };
                    this.pricingHistory = history;

                    try {
                        const response = await fetch(config.pricingHistoryBaseUrl + '/' + customerId + '/pricing-history', {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message || 'Unable to load pricing history.');
                        }

                        history.rows = data;
                    } catch (error) {
                        history.error = error.message || 'Unable to load pricing history.';
                    } finally {
                        history.loading = false;
                        if (requestId === this.pricingHistoryRequestId) {
                            this.pricingHistory = { ...history };
                        }
                    }
                },

                openInactive() {
                    this.inactiveSearch = '';
                    this.modal = 'inactive';
                },

                openForm(mode, id = null) {
                    this.formMode = mode;
                    this.resetCustomerForm();

                    if (mode === 'edit') {
                        const customer = this.customersById[String(id)];
                        if (!customer) return;
                        this.selectedCustomer = customer;
                        this.legacyCustomerName = !customer.first_name && !customer.last_name;
                        this.useNameComponents = !this.legacyCustomerName;
                        this.customerForm = {
                            id: customer.id,
                            business_name: customer.business_name || '',
                            owner_name: customer.owner_name || '',
                            first_name: customer.first_name || '',
                            middle_name: customer.middle_name || '',
                            last_name: customer.last_name || '',
                            suffix: customer.suffix || '',
                            contact_number: customer.contact_number || '',
                            credit_limit: Number(customer.credit_limit || 0).toFixed(2),
                            payment_terms: customer.payment_terms || '15 Days',
                            sp_diesel: customer.sp_diesel || '',
                            sp_unleaded: customer.sp_unleaded || '',
                            sp_premium: customer.sp_premium || ''
                        };
                    }

                    this.modal = 'customer-form';
                    this.$nextTick(() => this.initDatePickers());
                },

                closeModal() {
                    if (this.confirmation.open || this.messageDialog.open || this.feedbackClosing) return;

                    this.modal = null;
                    this.invoiceLoading = false;
                },

                resetCustomerForm() {
                    this.legacyCustomerName = false;
                    this.useNameComponents = true;
                    this.customerForm = {
                        id: '',
                        business_name: '',
                        owner_name: '',
                        first_name: '',
                        middle_name: '',
                        last_name: '',
                        suffix: '',
                        contact_number: '',
                        credit_limit: '',
                        payment_terms: '15 Days',
                        sp_diesel: '',
                        sp_unleaded: '',
                        sp_premium: ''
                    };
                },

                cleanCustomerName(value) {
                    return String(value || '').replace(/[\p{Z}\s]+/gu, ' ').trim();
                },

                customerDisplayName() {
                    if (!this.useNameComponents) return this.customerForm.owner_name || '';
                    return ['first_name', 'middle_name', 'last_name', 'suffix']
                        .map(field => this.cleanCustomerName(this.customerForm[field]))
                        .filter(Boolean).join(' ');
                },

                customerNameValidationError() {
                    if (!this.useNameComponents) return '';
                    const namePattern = /^(?=.*\p{L})[\p{L}\p{M}\p{Pd} .'\u2018\u2019\uFF07\u00B7\u30FB\u05F3\u05F4\u200C\u200D]+$/u;
                    for (const [field, label, required] of [['first_name', 'First name', true], ['middle_name', 'Middle name', false], ['last_name', 'Last name', true]]) {
                        const value = this.cleanCustomerName(this.customerForm[field]);
                        this.customerForm[field] = value;
                        const length = Array.from(value).length;
                        if (required && length < 2) return label + ' must contain at least 2 characters.';
                        if (length > 60) return label + ' must not exceed 60 characters.';
                        if (value && !namePattern.test(value)) return label + ' must contain valid personal-name characters.';
                    }
                    if (!['', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V'].includes(this.customerForm.suffix)) {
                        return 'Select a valid suffix.';
                    }
                    return '';
                },

                resetPaymentForm() {
                    this.paymentForm = {
                        customer_id: '',
                        invoice_id: '',
                        amount: '',
                        payment_date: new Date().toISOString().slice(0, 10),
                        payment_method: 'Cash',
                        reference_no: '',
                        remarks: ''
                    };
                    this.invoices = [];
                    this.selectedInvoiceId = '';
                    this.paymentType = 'partial';
                },

                async submitCustomer() {
                    if (!this.customerForm.business_name || Number(this.customerForm.credit_limit) < 0 || this.customerForm.credit_limit === '') {
                        this.showNotice('error', 'Business name and credit limit are required.');
                        return;
                    }

                    const nameError = this.customerNameValidationError();
                    if (nameError) {
                        this.showNotice('error', nameError);
                        return;
                    }

                    const isEdit = this.formMode === 'edit';
                    const url = isEdit ? config.updateUrl : config.storeUrl;
                    const confirmed = await this.askConfirmation({
                        title: isEdit ? 'Update Customer Profile' : 'Save Credit Customer',
                        message: isEdit ? 'Update this customer profile?' : 'Save this new credit customer?',
                        confirmText: isEdit ? 'Update Profile' : 'Save Customer',
                        tone: 'brand'
                    });
                    if (!confirmed) return;

                    this.savingCustomer = true;
                    const payload = { ...this.customerForm };
                    delete payload.owner_name;
                    if (!this.useNameComponents) {
                        for (const field of ['first_name', 'middle_name', 'last_name', 'suffix']) delete payload[field];
                    }
                    const data = await this.postJson(url, payload);
                    this.savingCustomer = false;

                    if (!data.success) {
                        this.showNotice('error', data.message || 'Unable to save customer.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Customer saved.');
                },

                async toggleStatus(id, status) {
                    const action = status === 'Inactive' ? 'deactivate' : 'reactivate';
                    const confirmed = await this.askConfirmation({
                        title: status === 'Inactive' ? 'Deactivate Customer' : 'Reactivate Customer',
                        message: 'Do you want to ' + action + ' this customer?',
                        confirmText: status === 'Inactive' ? 'Deactivate' : 'Reactivate',
                        tone: status === 'Inactive' ? 'danger' : 'success'
                    });
                    if (!confirmed) return;

                    const data = await this.postJson(config.toggleStatusUrl, { id, status });

                    if (!data.success) {
                        this.showNotice('error', data.message || 'Unable to update customer status.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Customer status updated.');
                },

                async fetchLedger() {
                    this.ledger.error = '';
                    this.ledger.rows = [];

                    if (!this.ledger.customerId) {
                        this.ledger.error = 'Select a customer first.';
                        return;
                    }

                    if (this.ledger.fromDate && this.ledger.toDate && this.ledger.fromDate > this.ledger.toDate) {
                        this.ledger.error = 'The from date cannot be later than the to date.';
                        return;
                    }

                    const query = new URLSearchParams();
                    if (this.ledger.fromDate) query.set('from_date', this.ledger.fromDate);
                    if (this.ledger.toDate) query.set('to_date', this.ledger.toDate);

                    this.ledger.loading = true;

                    try {
                        const response = await fetch(config.ledgerBaseUrl + '/' + this.ledger.customerId + (query.toString() ? '?' + query.toString() : ''), {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            this.ledger.error = data.message || 'Unable to load ledger transactions.';
                            return;
                        }

                        this.ledger.rows = data;
                    } catch (error) {
                        this.ledger.error = 'Could not load ledger transactions.';
                    } finally {
                        this.ledger.loading = false;
                    }
                },

                clearLedgerFilters() {
                    this.ledger.fromDate = '';
                    this.ledger.toDate = '';
                    this.ledger.error = '';
                    if (this.ledger.customerId) this.fetchLedger();
                },

                printLedger() {
                    if (!this.ledger.customerId) {
                        this.showNotice('error', 'Select a customer first.');
                        return;
                    }
                    window.open(config.printLedgerBaseUrl + '/' + this.ledger.customerId, '_blank');
                },

                openSoa() {
                    if (!this.ledger.customerId || !this.ledger.fromDate || !this.ledger.toDate) {
                        this.showNotice('error', 'Select a customer and date range before opening the SOA.');
                        return;
                    }

                    if (this.ledger.fromDate > this.ledger.toDate) {
                        this.showNotice('error', 'The from date cannot be later than the to date.');
                        return;
                    }

                    const query = new URLSearchParams({
                        customer_id: this.ledger.customerId,
                        start_date: this.ledger.fromDate,
                        end_date: this.ledger.toDate
                    });
                    window.open(config.soaUrl + '?' + query.toString(), '_blank');
                },

                async openPaymentModal() {
                    if (!this.selectedCustomer) return;
                    this.resetPaymentForm();
                    this.paymentForm.customer_id = this.selectedCustomer.id;
                    this.modal = 'payment';
                    this.$nextTick(() => this.initDatePickers());
                    await this.loadUnpaidInvoices(this.selectedCustomer.id);
                },

                async loadUnpaidInvoices(customerId) {
                    this.invoiceLoading = true;
                    this.invoices = [];
                    try {
                        const response = await fetch(config.unpaidBaseUrl + '/' + customerId + '/unpaid-invoices', {
                            headers: { 'Accept': 'application/json' }
                        });
                        this.invoices = response.ok ? await response.json() : [];
                    } catch (error) {
                        this.invoices = [];
                    } finally {
                        this.invoiceLoading = false;
                    }
                },

                onInvoiceSelected() {
                    const invoice = this.currentInvoice();
                    this.paymentType = 'partial';
                    this.paymentForm.invoice_id = invoice ? invoice.id : '';
                    this.paymentForm.reference_no = invoice ? invoice.invoice_number : '';
                    this.paymentForm.amount = '';
                },

                currentInvoice() {
                    return this.invoices.find(invoice => String(invoice.id) === String(this.selectedInvoiceId)) || null;
                },

                invoiceRemaining(invoice) {
                    if (!invoice) return 0;
                    return Math.max(0, Number(invoice.total_amount_due || 0) - Number(invoice.payments_received || 0));
                },

                setPaymentType(type) {
                    const invoice = this.currentInvoice();
                    if (!invoice) return;
                    this.paymentType = type;
                    if (type === 'full') {
                        const remaining = this.invoiceRemaining(invoice);
                        const balance = Number(this.selectedCustomer?.current_balance || 0);
                        const amount = balance > 0 ? Math.min(remaining, balance) : remaining;
                        this.paymentForm.amount = Number(amount).toFixed(2);
                    } else {
                        this.paymentForm.amount = '';
                    }
                },

                payMax() {
                    const invoice = this.currentInvoice();
                    const max = invoice ? this.invoiceRemaining(invoice) : Number(this.selectedCustomer?.current_balance || 0);
                    if (max > 0) this.paymentForm.amount = Number(max).toFixed(2);
                },

                paymentAdvanceAmount() {
                    const amount = Number(this.paymentForm.amount || 0);
                    let max = this.currentInvoice() ? this.invoiceRemaining(this.currentInvoice()) : Number(this.selectedCustomer?.current_balance || 0);
                    if (max < 0) max = 0;
                    return amount > max + 0.01 ? amount - max : 0;
                },

                async submitPayment() {
                    const amount = Number(this.paymentForm.amount || 0);

                    if (!this.paymentForm.customer_id || amount <= 0) {
                        this.showNotice('error', 'Enter a valid payment amount.');
                        return;
                    }

                    const advance = this.paymentAdvanceAmount();
                    const confirmed = await this.askConfirmation({
                        title: 'Confirm Payment',
                        message: 'Record payment of ' + this.formatMoney(amount) + '?',
                        details: advance > 0 ? 'This includes advance credit of ' + this.formatMoney(advance) + '.' : '',
                        confirmText: 'Record Payment',
                        tone: 'success'
                    });
                    if (!confirmed) return;

                    this.savingPayment = true;
                    const data = await this.postJson(config.payUrl, this.paymentForm);
                    this.savingPayment = false;

                    if (!data.success) {
                        this.showNotice('error', data.message || 'Unable to record payment.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Payment recorded.');
                },

                async voidPayment(ledgerId) {
                    const confirmed = await this.askConfirmation({
                        title: 'Void Payment',
                        message: 'Void this payment and restore the customer balance?',
                        confirmText: 'Void Payment',
                        tone: 'danger'
                    });
                    if (!confirmed) return;

                    const data = await this.postJson(config.voidPaymentUrl, { ledger_id: ledgerId });

                    if (!data.success) {
                        this.showNotice('error', data.message || 'Unable to void payment.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Payment voided.');
                },

                async postJson(url, payload) {
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });
                        const data = await response.json();

                        if (response.ok) {
                            return data;
                        }

                        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                        return { success: false, message: data.message || firstError || 'Request failed.' };
                    } catch (error) {
                        return { success: false, message: 'An unexpected error occurred.' };
                    }
                },

                showNotice(type, message) {
                    if (type === 'success') {
                        this.showToast(message);
                        return;
                    }

                    this.showMessage(type, message);
                },

                showToast(message) {
                    this.closeToast();
                    this.toast.message = message;
                    this.toast.open = true;
                    this.toast.timer = window.setTimeout(() => this.closeToast(), 3000);
                },

                closeToast() {
                    if (this.toast.timer) {
                        window.clearTimeout(this.toast.timer);
                    }

                    this.toast = { open: false, message: '', timer: null };
                },

                showStoredToast() {
                    let message = '';

                    try {
                        message = window.sessionStorage.getItem('customer-credit-toast') || '';
                        window.sessionStorage.removeItem('customer-credit-toast');
                    } catch (error) {
                        message = '';
                    }

                    if (message) {
                        this.$nextTick(() => this.showToast(message));
                    }
                },

                showMessage(type, message, title = '') {
                    const dialogType = type === 'info' ? 'info' : 'error';
                    this.messageDialog = {
                        open: true,
                        type: dialogType,
                        title: title || (dialogType === 'info' ? 'Information' : 'Unable to Continue'),
                        message
                    };
                },

                closeMessageDialog() {
                    this.feedbackClosing = true;
                    this.messageDialog.open = false;

                    this.$nextTick(() => {
                        this.feedbackClosing = false;
                    });
                },

                askConfirmation(options = {}) {
                    return new Promise(resolve => {
                        this.confirmation = {
                            open: true,
                            title: options.title || 'Confirm Action',
                            message: options.message || 'Please confirm this action.',
                            details: options.details || '',
                            confirmText: options.confirmText || 'Confirm',
                            cancelText: options.cancelText || 'Cancel',
                            tone: options.tone || 'brand',
                            resolver: resolve
                        };

                        this.$nextTick(() => {
                            if (this.$refs.confirmPrimary) {
                                this.$refs.confirmPrimary.focus();
                            }
                        });
                    });
                },

                resolveConfirmation(confirmed) {
                    const resolver = this.confirmation.resolver;
                    this.feedbackClosing = true;
                    this.confirmation.open = false;
                    this.confirmation.resolver = null;

                    if (resolver) {
                        resolver(confirmed);
                    }

                    this.$nextTick(() => {
                        this.feedbackClosing = false;
                    });
                },

                reloadWithToast(message) {
                    try {
                        window.sessionStorage.setItem('customer-credit-toast', message);
                    } catch (error) {
                        this.showToast(message);
                    }

                    this.scheduleReload();
                },

                scheduleReload(delay = 0) {
                    if (this.reloadTimer) {
                        window.clearTimeout(this.reloadTimer);
                    }

                    if (delay <= 0) {
                        window.location.reload();
                        return;
                    }

                    this.reloadTimer = window.setTimeout(() => window.location.reload(), delay);
                },

                formatMoney(value) {
                    return '\u20b1' + new Intl.NumberFormat('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(Number(value || 0));
                },

                formatBalance(value) {
                    const amount = Number(value || 0);
                    return amount < 0 ? 'Advance: ' + this.formatMoney(Math.abs(amount)) : this.formatMoney(amount);
                },

                customerBalanceClass(customer) {
                    if (!customer) return 'text-gray-800 dark:text-white/90';
                    const balance = Number(customer.current_balance || 0);
                    const limit = Number(customer.credit_limit || 0);
                    if (balance < 0) return 'text-blue-light-700 dark:text-blue-light-400';
                    if (limit > 0 && balance > limit) return 'text-error-600 dark:text-error-400';
                    return 'text-success-600 dark:text-success-500';
                },

                specialPrice(value) {
                    return Number(value) > 0 ? this.formatMoney(value) : 'Standard';
                }
            };
        }
    </script>
@endpush
