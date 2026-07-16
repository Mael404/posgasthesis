<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffPumpController extends Controller
{
    private const PUMP_FUEL_TYPES = [
        1 => 'Premium',
        2 => 'Unleaded',
        3 => 'Premium',
        4 => 'Unleaded',
        5 => 'Diesel',
        6 => 'Diesel',
    ];

    private array $tablePresence = [];
    private array $columnPresence = [];

    public function index(): View
    {
        $missingTables = $this->missingTables(['sales', 'customers', 'customer_ledgers', 'tanks', 'fuel_prices']);

        $prices = $this->hasTable('fuel_prices')
            ? DB::table('fuel_prices')->pluck('price_per_liter', 'fuel_type')
            : collect();

        $customers = $this->hasTable('customers')
            ? DB::table('customers')
                ->select('id', 'business_name', 'owner_name', 'current_balance', 'credit_limit', 'sp_diesel', 'sp_unleaded', 'sp_premium', 'status')
                ->where(function ($query) {
                    $query->where('status', '!=', 'Inactive')
                        ->orWhereNull('status')
                        ->orWhere('status', '');
                })
                ->orderBy('business_name')
                ->orderBy('owner_name')
                ->get()
            : collect();

        $recentSales = $this->recentSales();

        $tanks = $this->hasTable('tanks')
            ? DB::table('tanks')
                ->where(function ($query) {
                    $query->where('status', 'Active')
                        ->orWhereNull('status')
                        ->orWhere('status', '');
                })
                ->orderBy('id')
                ->get()
            : collect();

        return view('pages.staff.pumpstation', [
            'title' => 'Pump Station',
            'prices' => $prices,
            'customers' => $customers,
            'recentSales' => $recentSales,
            'tanks' => $tanks,
            'missingPumpTables' => $missingTables,
        ]);
    }

    public function storeSale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pump_id' => ['required', 'integer', Rule::in(array_keys(self::PUMP_FUEL_TYPES))],
            'fuel_type' => ['required', 'string', Rule::in(array_values(self::PUMP_FUEL_TYPES))],
            'liters' => 'required|numeric|min:0.1',
            'price' => 'nullable|numeric|min:0',
            'standard_price' => 'nullable|numeric|min:0',
            'payment_type' => 'required|in:credit,walkin,cash',
            'customer_id' => 'nullable|exists:customers,id',
            'manual_discount' => 'nullable|numeric|min:0',
            'manual_excess' => 'nullable|numeric|min:0',
            'or_number' => 'nullable|string|max:255',
            'walkin_name' => 'nullable|string|max:255',
            'plate_no' => 'nullable|string|max:50',
        ]);

        if ($missing = $this->missingTables(['sales', 'customers', 'customer_ledgers', 'fuel_prices'])) {
            return $this->jsonError('Missing table(s): ' . implode(', ', $missing) . '.');
        }

        try {
            DB::transaction(function () use ($validated) {
                $paymentType = $validated['payment_type'] === 'cash' ? 'walkin' : $validated['payment_type'];
                $fuelType = $this->fuelTypeForPump((int) $validated['pump_id']);
                $liters = (float) $validated['liters'];
                $manualDiscount = (float) ($validated['manual_discount'] ?? 0);
                $manualExcess = (float) ($validated['manual_excess'] ?? 0);
                $orNumber = $this->cleanOptionalText($validated['or_number'] ?? null);
                $referenceNo = $this->generateTransactionReference();
                $creditCustomer = null;
                $walkinCustomerId = null;
                $walkinName = $this->normalizeWalkinName($validated['walkin_name'] ?? null);
                $pricingCustomer = null;

                if ($paymentType === 'credit') {
                    if (empty($validated['customer_id'])) {
                        throw new \RuntimeException('Credit customer is required for credit sales.');
                    }

                    $creditCustomer = DB::table('customers')
                        ->where('id', (int) $validated['customer_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $creditCustomer) {
                        throw new \RuntimeException('Credit customer not found.');
                    }

                    $pricingCustomer = $creditCustomer;
                } elseif (is_numeric($walkinName)) {
                    $selectedCustomer = DB::table('customers')->where('id', (int) $walkinName)->first();

                    if ($selectedCustomer) {
                        $walkinCustomerId = (int) $selectedCustomer->id;
                        $walkinName = $this->formatCustomerDisplayName($selectedCustomer);
                        $pricingCustomer = $selectedCustomer;
                    }
                }

                [$standardPrice, $appliedPrice] = $this->salePrices($fuelType, $pricingCustomer, $validated);
                [$totalAmount, $autoDiscount, $totalDiscount, $totalSurcharge] = $this->saleTotals(
                    $liters,
                    $standardPrice,
                    $appliedPrice,
                    $manualDiscount,
                    $manualExcess
                );

                if ($paymentType === 'credit') {
                    $newBalance = round((float) $creditCustomer->current_balance + $totalAmount, 2);

                    if ($newBalance > (float) $creditCustomer->credit_limit) {
                        throw new \RuntimeException('Transaction failed: credit limit exceeded.');
                    }

                    DB::table('customers')->where('id', $creditCustomer->id)->update([
                        'current_balance' => $newBalance,
                        'updated_at' => now(),
                    ]);

                    $this->insertForExistingColumns('customer_ledgers', [
                        'customer_id' => $creditCustomer->id,
                        'transaction_date' => now(),
                        'type' => 'Fuel Sale',
                        'reference_no' => $referenceNo,
                        'or_number' => $orNumber,
                        'description' => $this->fuelSaleDescription($fuelType, $liters, $validated['pump_id'], $totalDiscount, $totalSurcharge),
                        'debit' => $totalAmount,
                        'credit' => 0,
                        'discount' => $totalDiscount,
                        'running_balance' => $newBalance,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->insertForExistingColumns('sales', [
                    'or_number' => $orNumber,
                    'reference_no' => $referenceNo,
                    'pump_id' => (int) $validated['pump_id'],
                    'user_id' => auth()->id(),
                    'customer_id' => $paymentType === 'credit' ? $creditCustomer->id : $walkinCustomerId,
                    'customer_name' => $paymentType === 'credit' ? null : $walkinName,
                    'plate_no' => $paymentType === 'credit' ? null : ($validated['plate_no'] ?? null),
                    'payment_type' => $paymentType,
                    'fuel_type' => $fuelType,
                    'liters' => $liters,
                    'price_applied' => $appliedPrice,
                    'amount' => $totalAmount,
                    'discount' => $autoDiscount,
                    'manual_discount' => $manualDiscount,
                    'surcharge' => $totalSurcharge,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $message = $request->input('payment_type') === 'credit'
                ? 'Credit sale logged successfully.'
                : 'Walk-in sale logged successfully.';

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function updateSale(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'pump_id' => ['required', 'integer', Rule::in(array_keys(self::PUMP_FUEL_TYPES))],
            'fuel_type' => ['required', 'string', Rule::in(array_values(self::PUMP_FUEL_TYPES))],
            'liters' => 'required|numeric|min:0.1',
            'price' => 'nullable|numeric|min:0',
            'standard_price' => 'nullable|numeric|min:0',
            'payment_type' => 'required|in:credit,walkin,cash',
            'customer_id' => 'nullable|exists:customers,id',
            'manual_discount' => 'nullable|numeric|min:0',
            'manual_excess' => 'nullable|numeric|min:0',
            'walkin_name' => 'nullable|string|max:255',
            'plate_no' => 'nullable|string|max:50',
            'or_number' => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($validated, $id) {
                $sale = DB::table('sales')
                    ->where('id', $id)
                    ->where('user_id', auth()->id())
                    ->lockForUpdate()
                    ->first();

                if (! $sale) {
                    throw new \RuntimeException('Transaction not found.');
                }

                if ($sale->payment_type === 'Collection') {
                    throw new \RuntimeException('Account payment collections cannot be edited here.');
                }

                if ($this->isSaleLocked($sale)) {
                    throw new \RuntimeException('Cannot edit: this transaction is locked in a shift settlement.');
                }

                $paymentType = $validated['payment_type'] === 'cash' ? 'walkin' : $validated['payment_type'];
                $fuelType = $this->fuelTypeForPump((int) $validated['pump_id']);
                $liters = (float) $validated['liters'];
                $manualDiscount = (float) ($validated['manual_discount'] ?? 0);
                $manualExcess = (float) ($validated['manual_excess'] ?? 0);
                $orNumber = $this->cleanOptionalText($validated['or_number'] ?? null);
                $saleReferenceNo = $this->cleanOptionalText($sale->reference_no ?? null) ?? $this->generateTransactionReference();

                $newCustomerId = null;
                $newCustomerName = null;
                $newPlateNo = null;

                if ($paymentType === 'credit') {
                    if (empty($validated['customer_id'])) {
                        throw new \RuntimeException('Credit customer is required for credit sales.');
                    }

                    $newCustomerId = (int) $validated['customer_id'];
                } else {
                    $newCustomerName = $this->normalizeWalkinName($validated['walkin_name'] ?? null);
                    $newPlateNo = $validated['plate_no'] ?? null;

                    if (is_numeric($newCustomerName)) {
                        $walkinCustomer = DB::table('customers')->where('id', (int) $newCustomerName)->first();

                        if ($walkinCustomer) {
                            $newCustomerId = (int) $walkinCustomer->id;
                            $newCustomerName = $this->formatCustomerDisplayName($walkinCustomer);
                        }
                    }
                }

                $previousInvoiceId = null;

                if ($sale->payment_type === 'credit' && $sale->customer_id) {
                    $previousCreditLedger = $this->findLatestCreditLedgerForSale($sale);
                    $previousInvoiceId = $previousCreditLedger->invoice_id ?? null;

                    $this->applyCreditEditLedger(
                        (int) $sale->customer_id,
                        0,
                        (float) $sale->amount,
                        $sale,
                        'Edit Reversal',
                        null,
                        null,
                        null,
                        0,
                        0,
                        null,
                        $previousInvoiceId
                    );
                }

                $pricingCustomer = null;

                if ($newCustomerId) {
                    $pricingCustomer = DB::table('customers')
                        ->where('id', $newCustomerId)
                        ->lockForUpdate()
                        ->first();
                }

                if ($paymentType === 'credit' && ! $pricingCustomer) {
                    throw new \RuntimeException('Credit customer not found.');
                }

                [$standardPrice, $appliedPrice] = $this->salePrices($fuelType, $pricingCustomer, $validated);
                $originalPaymentType = $sale->payment_type === 'credit' ? 'credit' : 'walkin';
                $samePricingContext = $paymentType === $originalPaymentType
                    && $fuelType === $sale->fuel_type
                    && (int) ($newCustomerId ?? 0) === (int) ($sale->customer_id ?? 0);

                if ($samePricingContext && (float) ($validated['price'] ?? 0) > 0) {
                    $appliedPrice = round((float) $validated['price'], 2);
                }

                [$totalAmount, $autoDiscount, $totalDiscount, $totalSurcharge] = $this->saleTotals(
                    $liters,
                    $standardPrice,
                    $appliedPrice,
                    $manualDiscount,
                    $manualExcess
                );

                if ($paymentType === 'credit') {
                    if (((float) $pricingCustomer->current_balance + $totalAmount) > (float) $pricingCustomer->credit_limit) {
                        throw new \RuntimeException('Transaction failed: credit limit exceeded.');
                    }

                    $this->applyCreditEditLedger(
                        $newCustomerId,
                        $totalAmount,
                        0,
                        $sale,
                        'Fuel Sale',
                        $fuelType,
                        $liters,
                        $validated['pump_id'],
                        $totalDiscount,
                        $totalSurcharge,
                        $orNumber,
                        ((int) ($sale->customer_id ?? 0) === $newCustomerId) ? $previousInvoiceId : null
                    );
                }

                DB::table('sales')->where('id', $sale->id)->update($this->onlyExistingColumns('sales', [
                    'or_number' => $orNumber,
                    'reference_no' => $saleReferenceNo,
                    'pump_id' => (int) $validated['pump_id'],
                    'customer_id' => $newCustomerId,
                    'customer_name' => $paymentType === 'credit' ? null : $newCustomerName,
                    'plate_no' => $paymentType === 'credit' ? null : $newPlateNo,
                    'payment_type' => $paymentType,
                    'fuel_type' => $fuelType,
                    'liters' => $liters,
                    'price_applied' => $appliedPrice,
                    'amount' => $totalAmount,
                    'discount' => $autoDiscount,
                    'manual_discount' => $manualDiscount,
                    'surcharge' => $totalSurcharge,
                    'updated_at' => now(),
                ]));
            });

            return response()->json(['success' => true, 'message' => 'Transaction updated successfully.']);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function storePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|max:50',
            'invoice_id' => 'nullable|exists:invoices,id',
            'or_number' => 'nullable|string|max:255',
        ]);

        try {
            $this->insertForExistingColumns('sales', [
                'user_id' => auth()->id(),
                'customer_id' => (int) $validated['customer_id'],
                'payment_type' => 'Collection',
                'fuel_type' => $validated['payment_method'],
                'pump_id' => 0,
                'liters' => 0,
                'price_applied' => 0,
                'amount' => (float) $validated['amount'],
                'or_number' => $this->cleanOptionalText($validated['or_number'] ?? null),
                'reference_no' => $this->generateTransactionReference(),
                'invoice_id' => $validated['invoice_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Payment logged for shift verification.']);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function bypassPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:sales,id',
            'semiadmin_password' => 'required|string',
        ]);

        try {
            $authorizingSemiadmin = $this->findSemiadminByPassword($validated['semiadmin_password']);

            if (! $authorizingSemiadmin) {
                return $this->jsonError('Invalid semi-admin password.', 403);
            }

            DB::transaction(function () use ($validated, $authorizingSemiadmin) {
                $sale = DB::table('sales')
                    ->where('id', (int) $validated['id'])
                    ->where('user_id', auth()->id())
                    ->lockForUpdate()
                    ->first();

                if (! $sale) {
                    throw new \RuntimeException('Payment transaction not found.');
                }

                if ($sale->payment_type !== 'Collection') {
                    throw new \RuntimeException('Only account payment collections can be bypass-posted.');
                }

                if (! empty($sale->customer_payment_posted_at)) {
                    throw new \RuntimeException('This payment has already been reflected in the customer account.');
                }

                if ($this->isSaleLocked($sale)) {
                    throw new \RuntimeException('Cannot bypass: this payment is locked in a shift settlement.');
                }

                if (empty($sale->customer_id)) {
                    throw new \RuntimeException('This payment is missing a customer account.');
                }

                $customer = DB::table('customers')
                    ->where('id', $sale->customer_id)
                    ->lockForUpdate()
                    ->first();

                if (! $customer) {
                    throw new \RuntimeException('Customer account not found.');
                }

                $amount = (float) $sale->amount;
                $newBalance = round((float) $customer->current_balance - $amount, 2);
                $referenceNo = $this->cleanOptionalText($sale->reference_no ?? null) ?? $this->generateTransactionReference();
                $method = $sale->fuel_type ?? 'Cash';
                $staffName = auth()->user()->name ?? 'Staff';

                DB::table('customers')->where('id', $customer->id)->update([
                    'current_balance' => $newBalance,
                    'updated_at' => now(),
                ]);

                $this->insertForExistingColumns('customer_ledgers', [
                    'customer_id' => $customer->id,
                    'invoice_id' => $sale->invoice_id ?? null,
                    'transaction_date' => $sale->created_at,
                    'type' => 'Payment',
                    'reference_no' => $referenceNo,
                    'or_number' => $this->cleanOptionalText($sale->or_number ?? null),
                    'description' => "Payment via {$method} (Bypass posted by {$staffName}; authorized by semi-admin)",
                    'debit' => 0,
                    'credit' => $amount,
                    'discount' => 0,
                    'running_balance' => $newBalance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->applyInvoicePayment($sale->invoice_id ?? null, $amount);

                DB::table('sales')->where('id', $sale->id)->update($this->onlyExistingColumns('sales', [
                    'posted_customer_id' => $customer->id,
                    'customer_id' => null,
                    'customer_name' => $this->formatCustomerDisplayName($customer),
                    'reference_no' => $referenceNo,
                    'customer_payment_posted_at' => now(),
                    'customer_payment_posted_by' => auth()->id(),
                    'customer_payment_authorized_by' => $authorizingSemiadmin->id,
                    'updated_at' => now(),
                ]));
            });

            return response()->json(['success' => true, 'message' => 'Payment reflected in the customer account.']);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function voidPostedPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:sales,id',
            'semiadmin_password' => 'required|string',
        ]);

        try {
            $authorizingSemiadmin = $this->findSemiadminByPassword($validated['semiadmin_password']);

            if (! $authorizingSemiadmin) {
                return $this->jsonError('Invalid semi-admin password.', 403);
            }

            DB::transaction(function () use ($validated, $authorizingSemiadmin) {
                $sale = DB::table('sales')
                    ->where('id', (int) $validated['id'])
                    ->where('user_id', auth()->id())
                    ->lockForUpdate()
                    ->first();

                if (! $sale) {
                    throw new \RuntimeException('Payment transaction not found.');
                }

                if ($sale->payment_type !== 'Collection') {
                    throw new \RuntimeException('Only account payment collections can be voided here.');
                }

                if (! empty($sale->customer_payment_voided_at)) {
                    throw new \RuntimeException('This posted payment has already been voided.');
                }

                if ($this->isSaleLocked($sale)) {
                    throw new \RuntimeException('This payment is locked by a submitted shift reading. It can only be changed after an admin rejects or undoes that shift reading.');
                }

                if (empty($sale->customer_payment_posted_at)) {
                    throw new \RuntimeException('Only payments already reflected in the customer account can be voided here.');
                }

                $customerId = $sale->posted_customer_id ?: $sale->customer_id;

                if (empty($customerId)) {
                    throw new \RuntimeException('This payment is missing a customer account.');
                }

                $customer = DB::table('customers')
                    ->where('id', $customerId)
                    ->lockForUpdate()
                    ->first();

                if (! $customer) {
                    throw new \RuntimeException('Customer account not found.');
                }

                $amount = (float) $sale->amount;
                $newBalance = round((float) $customer->current_balance + $amount, 2);
                $method = $sale->fuel_type ?? 'Payment';
                $voidedReference = $this->cleanOptionalText($sale->reference_no ?? null)
                    ?? $this->cleanOptionalText($sale->or_number ?? null)
                    ?? 'Payment #' . $sale->id;

                DB::table('customers')->where('id', $customer->id)->update([
                    'current_balance' => $newBalance,
                    'updated_at' => now(),
                ]);

                $this->insertForExistingColumns('customer_ledgers', [
                    'customer_id' => $customer->id,
                    'invoice_id' => $sale->invoice_id ?? null,
                    'transaction_date' => now(),
                    'type' => 'Void Payment',
                    'reference_no' => 'VOID-' . $sale->id,
                    'or_number' => $this->cleanOptionalText($sale->or_number ?? null),
                    'description' => "VOID Payment: Reversed {$method} payment from {$voidedReference} (authorized by semi-admin)",
                    'debit' => $amount,
                    'credit' => 0,
                    'discount' => 0,
                    'running_balance' => $newBalance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->reverseInvoicePayment($sale->invoice_id ?? null, $amount);

                DB::table('sales')->where('id', $sale->id)->update($this->onlyExistingColumns('sales', [
                    'posted_customer_id' => $customer->id,
                    'customer_name' => $this->formatCustomerDisplayName($customer),
                    'customer_payment_voided_at' => now(),
                    'customer_payment_voided_by' => auth()->id(),
                    'customer_payment_void_authorized_by' => $authorizingSemiadmin->id,
                    'updated_at' => now(),
                ]));
            });

            return response()->json(['success' => true, 'message' => 'Posted payment voided and customer account reversed.']);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function voidSale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:sales,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $sale = DB::table('sales')
                    ->where('id', (int) $validated['id'])
                    ->where('user_id', auth()->id())
                    ->lockForUpdate()
                    ->first();

                if (! $sale) {
                    throw new \RuntimeException('Transaction not found.');
                }

                if ($this->isSaleLocked($sale)) {
                    throw new \RuntimeException('Cannot void: this transaction is locked in a shift settlement.');
                }

                if ($sale->payment_type === 'Collection') {
                    if (! empty($sale->customer_payment_posted_at)) {
                        throw new \RuntimeException('Cannot cancel: this payment has already been reflected in the customer account.');
                    }

                    DB::table('sales')->where('id', $sale->id)->delete();

                    return;
                }

                if ($sale->payment_type === 'credit' && $sale->customer_id) {
                    $customer = DB::table('customers')
                        ->where('id', $sale->customer_id)
                        ->lockForUpdate()
                        ->first();

                    if ($customer) {
                        $newBalance = round((float) $customer->current_balance - (float) $sale->amount, 2);
                        $voidedReference = $this->cleanOptionalText($sale->reference_no ?? null)
                            ?? $this->cleanOptionalText($sale->or_number ?? null)
                            ?? 'Sale #' . $sale->id;

                        DB::table('customers')->where('id', $customer->id)->update([
                            'current_balance' => $newBalance,
                            'updated_at' => now(),
                        ]);

                        $this->insertForExistingColumns('customer_ledgers', [
                            'customer_id' => $customer->id,
                            'transaction_date' => now(),
                            'type' => 'Void',
                            'reference_no' => 'VOID-' . $sale->id,
                            'or_number' => $this->cleanOptionalText($sale->or_number ?? null),
                            'description' => 'VOID Transaction: Ref ' . $voidedReference,
                            'debit' => 0,
                            'credit' => (float) $sale->amount,
                            'discount' => 0,
                            'running_balance' => $newBalance,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::table('sales')->where('id', $sale->id)->delete();
            });

            return response()->json(['success' => true, 'message' => 'Transaction reversed successfully.']);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    private function recentSales(): LengthAwarePaginator
    {
        if (! $this->hasTable('sales') || ! $this->hasTable('users')) {
            return $this->emptyPaginator();
        }

        $query = DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->leftJoin('customers as posted_customers', 'sales.posted_customer_id', '=', 'posted_customers.id')
            ->select(
                'sales.*',
                'users.name as staff_name',
                DB::raw('COALESCE(customers.business_name, posted_customers.business_name) as business_name'),
                DB::raw('COALESCE(customers.owner_name, posted_customers.owner_name) as owner_name'),
                DB::raw('COALESCE(customers.business_name, posted_customers.business_name, customers.owner_name, posted_customers.owner_name, sales.customer_name) as display_customer_name')
            )
            ->where('sales.user_id', auth()->id())
            ->orderByDesc('sales.created_at')
            ->orderByDesc('sales.id');

        return $query->paginate(10, ['*'], 'recent_sales_page')->withQueryString();
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            10,
            LengthAwarePaginator::resolveCurrentPage('recent_sales_page'),
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'recent_sales_page',
            ]
        );
    }

    private function salePrices(string $fuelType, ?object $customer, array $fallback): array
    {
        $standardPrice = $this->standardPriceForFuel($fuelType);

        if ($standardPrice <= 0) {
            $standardPrice = (float) ($fallback['standard_price'] ?? 0);
        }

        $appliedPrice = $this->customerPriceForFuel($customer, $fuelType, $standardPrice);

        if ($appliedPrice <= 0) {
            $appliedPrice = (float) ($fallback['price'] ?? $standardPrice);
        }

        return [round($standardPrice, 2), round($appliedPrice, 2)];
    }

    private function saleTotals(float $liters, float $standardPrice, float $appliedPrice, float $manualDiscount, float $manualExcess): array
    {
        $subtotalAmount = round(($liters * $appliedPrice) + $manualExcess, 2);

        if ($manualDiscount > $subtotalAmount) {
            throw new \RuntimeException('The manual discount cannot be greater than the total transaction amount.');
        }

        $totalAmount = round($subtotalAmount - $manualDiscount, 2);
        $priceDifference = round($appliedPrice - $standardPrice, 2);
        $autoSurcharge = $priceDifference > 0 ? round($priceDifference * $liters, 2) : 0;
        $autoDiscount = $priceDifference < 0 ? round(abs($priceDifference) * $liters, 2) : 0;
        $totalDiscount = round($autoDiscount + $manualDiscount, 2);
        $totalSurcharge = round($autoSurcharge + $manualExcess, 2);

        return [$totalAmount, $autoDiscount, $totalDiscount, $totalSurcharge];
    }

    private function standardPriceForFuel(string $fuelType): float
    {
        if (! $this->hasTable('fuel_prices')) {
            return 0;
        }

        return (float) DB::table('fuel_prices')
            ->where('fuel_type', $fuelType)
            ->value('price_per_liter');
    }

    private function fuelTypeForPump(int $pumpId): string
    {
        if (! isset(self::PUMP_FUEL_TYPES[$pumpId])) {
            throw new \RuntimeException('The selected pump is invalid.');
        }

        return self::PUMP_FUEL_TYPES[$pumpId];
    }

    private function customerPriceForFuel(?object $customer, string $fuelType, float $standardPrice): float
    {
        if (! $customer) {
            return $standardPrice;
        }

        $field = match (strtolower($fuelType)) {
            'diesel' => 'sp_diesel',
            'unleaded' => 'sp_unleaded',
            'premium' => 'sp_premium',
            default => null,
        };

        if (! $field || ! isset($customer->{$field}) || (float) $customer->{$field} <= 0) {
            return $standardPrice;
        }

        return (float) $customer->{$field};
    }

    private function fuelSaleDescription(string $fuelType, float $liters, int $pumpId, float $discount, float $surcharge): string
    {
        $parts = ["Credit Charge: {$fuelType} - {$liters}L (Pump {$pumpId})"];

        if ($discount > 0) {
            $parts[] = '(Total Discount: PHP ' . number_format($discount, 2) . ')';
        }

        if ($surcharge > 0) {
            $parts[] = '(Total Excess: PHP ' . number_format($surcharge, 2) . ')';
        }

        return implode(' ', $parts);
    }

    private function normalizeWalkinName($walkinName): string
    {
        $walkinName = trim((string) $walkinName);

        return $walkinName !== '' ? $walkinName : 'Walk-In Customer';
    }

    private function isSaleLocked($sale): bool
    {
        return ! empty($sale->locked_by_shift_reading_id);
    }

    private function findLatestCreditLedgerForSale($sale)
    {
        $referenceNo = $this->cleanOptionalText($sale->reference_no ?? null);
        $orNumber = $this->cleanOptionalText($sale->or_number ?? null);
        $editReference = 'EDIT-' . $sale->id;

        return DB::table('customer_ledgers')
            ->where('customer_id', $sale->customer_id)
            ->where('debit', '>', 0)
            ->where('type', '!=', 'Payment')
            ->where(function ($query) use ($referenceNo, $orNumber, $editReference) {
                $query->where('reference_no', $editReference);

                if ($referenceNo) {
                    $query->orWhere('reference_no', $referenceNo)
                        ->orWhere('or_number', $referenceNo);
                }

                if ($orNumber) {
                    $query->orWhere('reference_no', $orNumber)
                        ->orWhere('or_number', $orNumber);
                }
            })
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();
    }

    private function applyCreditEditLedger(
        int $customerId,
        float $debit,
        float $credit,
        $sale,
        string $type,
        ?string $fuelType = null,
        ?float $liters = null,
        $pumpId = null,
        float $discount = 0,
        float $surcharge = 0,
        ?string $orNumber = null,
        $invoiceId = null
    ): void {
        if ($debit <= 0 && $credit <= 0) {
            return;
        }

        $customer = DB::table('customers')
            ->where('id', $customerId)
            ->lockForUpdate()
            ->first();

        if (! $customer) {
            throw new \RuntimeException('Customer account not found.');
        }

        $newBalance = round((float) $customer->current_balance + $debit - $credit, 2);

        DB::table('customers')->where('id', $customerId)->update([
            'current_balance' => $newBalance,
            'updated_at' => now(),
        ]);

        $details = $fuelType
            ? "{$fuelType} - " . number_format((float) $liters, 2) . "L (Pump {$pumpId})"
            : 'Original transaction amount';

        $adjustments = [];

        if ($discount > 0) {
            $adjustments[] = 'Total Discount: PHP ' . number_format($discount, 2);
        }

        if ($surcharge > 0) {
            $adjustments[] = 'Total Excess: PHP ' . number_format($surcharge, 2);
        }

        $adjustmentText = $adjustments ? ' (' . implode('; ', $adjustments) . ')' : '';
        $reference = $this->cleanOptionalText($sale->reference_no ?? null)
            ?? $this->cleanOptionalText($sale->or_number ?? null)
            ?? 'TXN-' . $sale->id;
        $descriptionType = $credit > 0 ? $type : 'Credit Charge';

        $this->insertForExistingColumns('customer_ledgers', [
            'customer_id' => $customerId,
            'transaction_date' => $sale->created_at,
            'type' => $type,
            'reference_no' => 'EDIT-' . $sale->id,
            'or_number' => $this->cleanOptionalText($orNumber ?? ($sale->or_number ?? null)),
            'description' => "{$descriptionType}: {$details} from {$reference}{$adjustmentText}",
            'debit' => $debit,
            'credit' => $credit,
            'discount' => $discount,
            'invoice_id' => $invoiceId,
            'running_balance' => $newBalance,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function applyInvoicePayment($invoiceId, float $amount): void
    {
        if (empty($invoiceId) || ! $this->hasTable('invoices')) {
            return;
        }

        $invoice = DB::table('invoices')
            ->where('id', $invoiceId)
            ->lockForUpdate()
            ->first();

        if (! $invoice) {
            return;
        }

        $paidSoFar = round((float) $invoice->payments_received + $amount, 2);

        DB::table('invoices')->where('id', $invoice->id)->update([
            'payments_received' => $paidSoFar,
            'status' => $paidSoFar >= (float) $invoice->total_amount_due ? 'Paid' : 'Partial',
            'updated_at' => now(),
        ]);
    }

    private function reverseInvoicePayment($invoiceId, float $amount): void
    {
        if (empty($invoiceId) || ! $this->hasTable('invoices')) {
            return;
        }

        $invoice = DB::table('invoices')
            ->where('id', $invoiceId)
            ->lockForUpdate()
            ->first();

        if (! $invoice) {
            return;
        }

        $paymentsReceived = max(0, round((float) $invoice->payments_received - $amount, 2));

        DB::table('invoices')->where('id', $invoice->id)->update([
            'payments_received' => $paymentsReceived,
            'status' => $this->invoiceStatusForPaymentsReceived($invoice, $paymentsReceived),
            'updated_at' => now(),
        ]);
    }

    private function invoiceStatusForPaymentsReceived($invoice, float $paymentsReceived): string
    {
        if (($invoice->status ?? null) === 'Rolled Over') {
            return $invoice->status;
        }

        if ($paymentsReceived <= 0) {
            return 'Unpaid';
        }

        return $paymentsReceived < (float) $invoice->total_amount_due ? 'Partial' : 'Paid';
    }

    private function generateTransactionReference(): string
    {
        do {
            $referenceNo = 'TXN-'
                . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)
                . chr(random_int(65, 90))
                . now()->format('Y');
        } while (
            DB::table('sales')->where('reference_no', $referenceNo)->exists()
            || DB::table('customer_ledgers')->where('reference_no', $referenceNo)->exists()
        );

        return $referenceNo;
    }

    private function cleanOptionalText($value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function findSemiadminByPassword(string $password)
    {
        return DB::table('users')
            ->where('role', 'semiadmin')
            ->get()
            ->first(function ($user) use ($password) {
                return Hash::check($password, $user->password);
            });
    }

    private function formatCustomerDisplayName($customer): string
    {
        $businessName = trim((string) ($customer->business_name ?? ''));
        $ownerName = trim((string) ($customer->owner_name ?? ''));

        if ($businessName !== '' && strtoupper($businessName) !== 'N/A') {
            return $ownerName !== '' && strtoupper($ownerName) !== 'N/A'
                ? strtoupper($businessName) . ' - ' . ucwords(strtolower($ownerName))
                : strtoupper($businessName);
        }

        return $ownerName !== ''
            ? ucwords(strtolower($ownerName))
            : 'Customer #' . $customer->id;
    }

    private function insertForExistingColumns(string $table, array $values): void
    {
        DB::table($table)->insert($this->onlyExistingColumns($table, $values));
    }

    private function onlyExistingColumns(string $table, array $values): array
    {
        return collect($values)
            ->filter(fn ($value, $column) => $this->hasColumn($table, $column))
            ->all();
    }

    private function missingTables(array $tables): array
    {
        return collect($tables)
            ->reject(fn ($table) => $this->hasTable($table))
            ->values()
            ->all();
    }

    private function hasTable(string $table): bool
    {
        if (! array_key_exists($table, $this->tablePresence)) {
            $this->tablePresence[$table] = Schema::hasTable($table);
        }

        return $this->tablePresence[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";

        if (! array_key_exists($key, $this->columnPresence)) {
            $this->columnPresence[$key] = $this->hasTable($table) && Schema::hasColumn($table, $column);
        }

        return $this->columnPresence[$key];
    }

    private function jsonError(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
