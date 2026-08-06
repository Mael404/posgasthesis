<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
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
            'authorized_representative' => ['nullable', 'string', 'max:255'],
            'vat_exempt_sales' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'zero_rated_sales' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'sc_pwd_discount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'withholding_tax' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'items' => ['nullable', 'array', 'max:7'],
            'items.*.item_type' => ['nullable', 'string', Rule::in(self::ITEM_TYPES)],
            'items.*.other_description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999999.999'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $items = collect($validated['items'] ?? [])
            ->values()
            ->filter(fn (array $item) => $this->itemHasInput($item))
            ->map(function (array $item, int $index) {
                $itemType = $this->textOrDefault($item['item_type'] ?? null, 'Others');
                $description = $itemType === 'Others'
                    ? $this->textOrDefault($item['other_description'] ?? null, 'No Item Description Inputted')
                    : $itemType;

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
        $totalAmountDue = round($amountNetOfVat + $addVat, 2);

        $invoice = DB::transaction(function () use ($validated, $items, $totalSales, $lessVat, $amountNetOfVat, $addVat, $totalAmountDue) {
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
                'authorized_representative' => Str::upper($this->textOrDefault(
                    $validated['authorized_representative'] ?? null,
                    'No Authorized Representative Inputted'
                )),
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
                'withholding_tax' => round((float) ($validated['withholding_tax'] ?? 0), 2),
                'total_amount_due' => $totalAmountDue,
            ]);

            if ($items->isNotEmpty()) {
                $invoice->items()->createMany($items->all());
            }

            return $invoice;
        });

        return response()->json([
            'success' => true,
            'message' => "Sales invoice SI No. {$invoice->si_number} saved successfully.",
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
            DB::table('sales_invoice_number_counters')->insert([
                'id' => 1,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lastNumber = 0;
        } else {
            $lastNumber = (int) $counter->last_number;
        }

        $nextNumber = $lastNumber + 1;

        DB::table('sales_invoice_number_counters')
            ->where('id', 1)
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => now(),
            ]);

        return $nextNumber;
    }

    private function textOrDefault(mixed $value, string $default): string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : $default;
    }
}
