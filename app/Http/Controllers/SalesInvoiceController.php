<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SalesInvoiceController extends Controller
{
    private const ITEM_TYPES = ['Diesel', 'Premium', 'Unleaded', 'Others'];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_date' => ['nullable', 'date'],
            'sold_to' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'discount_id_no' => ['nullable', 'string', 'max:255'],
            'signature' => ['nullable', 'string', 'max:255'],
            'vat_exempt_sales' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'zero_rated_sales' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'sc_pwd_discount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'withholding_tax' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'items' => ['required', 'array', 'min:1', 'max:7'],
            'items.*.item_type' => ['required', 'string', Rule::in(self::ITEM_TYPES)],
            'items.*.other_description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'items.*.price' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'items.*.amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
        ], [
            'items.required' => 'Please select an item before continuing.',
            'items.array' => 'Please select an item before continuing.',
            'items.min' => 'Please select an item before continuing.',
            'items.*.item_type.required' => 'Please select an item before continuing.',
            'items.*.item_type.in' => 'Please select a valid item before continuing.',
            'items.*.quantity.required' => 'QTY is required for every invoice item.',
            'items.*.quantity.gt' => 'QTY must be greater than zero.',
            'items.*.price.required' => 'PRICE is required for every invoice item.',
            'items.*.price.gt' => 'PRICE must be greater than zero.',
            'items.*.amount.required' => 'Amount is required for every invoice item.',
            'items.*.amount.gt' => 'Amount must be greater than zero.',
        ]);

        $items = collect($validated['items'] ?? [])
            ->values()
            ->filter(fn (array $item) => $this->itemHasInput($item))
            ->map(function (array $item, int $index) {
                $itemType = $item['item_type'];
                $description = $itemType === 'Others'
                    ? $this->textOrDefault($item['other_description'] ?? null, 'No Item Description Inputted')
                    : SalesInvoiceItem::defaultDescription($itemType);

                return [
                    'sort_order' => $index + 1,
                    'item_type' => $itemType,
                    'description' => $description,
                    'quantity' => round((float) ($item['quantity'] ?? 0), 3),
                    'price' => round((float) ($item['price'] ?? 0), 2),
                    'amount' => round((float) ($item['amount'] ?? 0), 2),
                ];
            });

        $totalSales = round((float) $items->sum('amount'), 2);
        $lessVat = round(($totalSales / 1.12) * 0.12, 2);
        $amountNetOfVat = round($totalSales - $lessVat, 2);
        $addVat = $lessVat;
        $withholdingTax = round((float) ($validated['withholding_tax'] ?? 0), 2);
        $amountBeforeWithholdingTax = round($amountNetOfVat + $addVat, 2);
        $totalAmountDue = round($amountBeforeWithholdingTax - $withholdingTax, 2);
        $authorizedRepresentative = trim((string) $request->user()->name);

        $invoice = DB::transaction(function () use ($validated, $items, $totalSales, $lessVat, $amountNetOfVat, $addVat, $withholdingTax, $totalAmountDue, $authorizedRepresentative) {
            $siNumber = $this->nextSiNumber();

            $invoice = SalesInvoice::create([
                'staff_id' => auth()->id(),
                'invoice_date' => $validated['invoice_date'] ?? now()->toDateString(),
                'sold_to' => Str::upper($this->textOrDefault($validated['sold_to'] ?? null, 'No Name Inputted')),
                'tin' => $this->textOrDefault($validated['tin'] ?? null, 'No TIN Inputted'),
                'terms' => $this->textOrDefault($validated['terms'] ?? null, 'No Terms Inputted'),
                'address' => Str::upper($this->textOrDefault($validated['address'] ?? null, 'No Address Inputted')),
                'discount_id_no' => $this->textOrDefault($validated['discount_id_no'] ?? null, 'No ID Number Inputted'),
                'signature' => $this->textOrDefault($validated['signature'] ?? null, 'No Signature Inputted'),
                'si_number' => (string) $siNumber,
                'authorized_representative' => $authorizedRepresentative,
                'status' => SalesInvoice::STATUS_ACTIVE,
                'vatable_sales' => $amountNetOfVat,
                'vat_exempt_sales' => round((float) ($validated['vat_exempt_sales'] ?? 0), 2),
                'zero_rated_sales' => round((float) ($validated['zero_rated_sales'] ?? 0), 2),
                'vat_amount' => $lessVat,
                'total_sales_vat_inclusive' => $totalSales,
                'less_vat' => $lessVat,
                'amount_net_of_vat' => $amountNetOfVat,
                'sc_pwd_discount' => round((float) ($validated['sc_pwd_discount'] ?? 0), 2),
                'add_vat' => $addVat,
                'withholding_tax' => $withholdingTax,
                'total_amount_due' => $totalAmountDue,
            ]);

            if ($items->isNotEmpty()) {
                $invoice->items()->createMany($items->all());
            }

            return $invoice;
        });

        return response()->json([
            'success' => true,
            'message' => "Sales invoice SI No. {$invoice->formattedSiNumber()} saved successfully.",
            'invoice' => [
                'id' => $invoice->id,
                'si_number' => $invoice->si_number,
                'total_amount_due' => (float) $invoice->total_amount_due,
            ],
        ], 201);
    }

    private function itemHasInput(array $item): bool
    {
        foreach (['item_type', 'other_description', 'quantity', 'price', 'amount'] as $field) {
            if (array_key_exists($field, $item) && $item[$field] !== null && trim((string) $item[$field]) !== '') {
                return true;
            }
        }

        return false;
    }

    private function nextSiNumber(): int
    {
        $counter = DB::table('sales_invoice_number_counters')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            DB::table('sales_invoice_number_counters')->insertOrIgnore([
                'id' => 1,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = DB::table('sales_invoice_number_counters')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();
        }

        $latestIssuedNumber = (int) DB::table('sales_invoices')
            ->selectRaw('COALESCE(MAX('.$this->siNumberExpression().'), 0) as latest_number')
            ->value('latest_number');

        $lastNumber = max((int) $counter->last_number, $latestIssuedNumber);

        $nextNumber = $lastNumber + 1;

        DB::table('sales_invoice_number_counters')
            ->where('id', 1)
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => now(),
            ]);

        return $nextNumber;
    }

    private function siNumberExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'CAST(sales_invoices.si_number AS UNSIGNED)',
            'pgsql' => 'CAST(sales_invoices.si_number AS BIGINT)',
            default => 'CAST(sales_invoices.si_number AS INTEGER)',
        };
    }

    private function textOrDefault(mixed $value, string $default): string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : $default;
    }
}
