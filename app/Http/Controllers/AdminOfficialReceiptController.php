<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminOfficialReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'product' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'si_from' => ['nullable', 'integer', 'min:1'],
            'si_to' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['all', SalesInvoice::STATUS_ACTIVE, SalesInvoice::STATUS_CANCELED])],
        ]);

        $filters = [
            'start_date' => $validated['start_date'] ?? '',
            'end_date' => $validated['end_date'] ?? '',
            'product' => $validated['product'] ?? '',
            'name' => $validated['name'] ?? '',
            'si_from' => $validated['si_from'] ?? '',
            'si_to' => $validated['si_to'] ?? '',
            'status' => $validated['status'] ?? 'all',
        ];

        $filteredQuery = $this->filteredQuery($filters);

        $totals = (clone $filteredQuery)
            ->where('sales_invoices.status', SalesInvoice::STATUS_ACTIVE)
            ->selectRaw('COUNT(*) as active_receipts')
            ->selectRaw('COALESCE(SUM(total_sales_vat_inclusive), 0) as total_sales_vat_inclusive')
            ->selectRaw('COALESCE(SUM(vatable_sales), 0) as vatable_sales')
            ->selectRaw('COALESCE(SUM(vat_amount), 0) as vat_amount')
            ->selectRaw('COALESCE(SUM(amount_net_of_vat), 0) as amount_net_of_vat')
            ->selectRaw('COALESCE(SUM(total_amount_due), 0) as total_amount_due')
            ->first();

        $receipts = (clone $filteredQuery)
            ->with(['items', 'staff:id,name', 'canceledBy:id,name'])
            ->orderByRaw($this->siNumberExpression().' DESC')
            ->paginate(20)
            ->withQueryString();

        $productOptions = DB::table('sales_invoice_items')
            ->orderBy('item_type')
            ->orderBy('description')
            ->get(['item_type', 'description'])
            ->flatMap(fn (object $item) => [$item->item_type, $item->description])
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->unique(fn ($value) => strtolower(trim((string) $value)))
            ->sort()
            ->values();

        return view('pages.admin.official-receipts', [
            'title' => 'Official Receipts',
            'receipts' => $receipts,
            'totals' => $totals,
            'filters' => $filters,
            'productOptions' => $productOptions,
            'hasActiveFilters' => collect($filters)->contains(fn ($value, $key) => $key === 'status'
                ? $value !== 'all'
                : trim((string) $value) !== ''),
        ]);
    }

    public function cancel(Request $request, SalesInvoice $receipt): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $wasAlreadyCanceled = false;

        $receipt = DB::transaction(function () use ($receipt, $validated, &$wasAlreadyCanceled) {
            $lockedReceipt = SalesInvoice::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($lockedReceipt->status === SalesInvoice::STATUS_CANCELED) {
                $wasAlreadyCanceled = true;

                return $lockedReceipt;
            }

            $lockedReceipt->markCanceled(
                auth()->id(),
                trim((string) ($validated['reason'] ?? '')) ?: 'Canceled by authorized user.'
            );

            return $lockedReceipt->fresh();
        });

        $message = $wasAlreadyCanceled
            ? "Official receipt SI No. {$receipt->si_number} is already canceled."
            : "Official receipt SI No. {$receipt->si_number} canceled. Its number and record were retained.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'receipt' => [
                    'id' => $receipt->id,
                    'si_number' => $receipt->si_number,
                    'status' => $receipt->status,
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, SalesInvoice $receipt): JsonResponse|RedirectResponse
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
            'items' => ['nullable', 'array', 'max:7'],
            'items.*.item_type' => ['nullable', 'string', Rule::in(['Diesel', 'Premium', 'Unleaded', 'Others'])],
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
        $withholdingTax = round((float) ($validated['withholding_tax'] ?? 0), 2);
        $amountBeforeWithholdingTax = round($amountNetOfVat + $lessVat, 2);
        $totalAmountDue = round($amountBeforeWithholdingTax - $withholdingTax, 2);
        $authorizedRepresentative = trim((string) $request->user()->name);

        $receipt = DB::transaction(function () use ($receipt, $validated, $items, $totalSales, $lessVat, $amountNetOfVat, $withholdingTax, $totalAmountDue, $authorizedRepresentative) {
            $lockedReceipt = SalesInvoice::query()->lockForUpdate()->findOrFail($receipt->id);

            $lockedReceipt->fill([
                'invoice_date' => $validated['invoice_date'] ?? now()->toDateString(),
                'sold_to' => Str::upper($this->textOrDefault($validated['sold_to'] ?? null, 'No Name Inputted')),
                'tin' => $this->textOrDefault($validated['tin'] ?? null, 'No TIN Inputted'),
                'terms' => $this->textOrDefault($validated['terms'] ?? null, 'No Terms Inputted'),
                'address' => Str::upper($this->textOrDefault($validated['address'] ?? null, 'No Address Inputted')),
                'discount_id_no' => $this->textOrDefault($validated['discount_id_no'] ?? null, 'No ID Number Inputted'),
                'signature' => $this->textOrDefault($validated['signature'] ?? null, 'No Signature Inputted'),
                'authorized_representative' => $authorizedRepresentative,
                'vatable_sales' => $amountNetOfVat,
                'vat_exempt_sales' => round((float) ($validated['vat_exempt_sales'] ?? 0), 2),
                'zero_rated_sales' => round((float) ($validated['zero_rated_sales'] ?? 0), 2),
                'vat_amount' => $lessVat,
                'total_sales_vat_inclusive' => $totalSales,
                'less_vat' => $lessVat,
                'amount_net_of_vat' => $amountNetOfVat,
                'sc_pwd_discount' => round((float) ($validated['sc_pwd_discount'] ?? 0), 2),
                'add_vat' => $lessVat,
                'withholding_tax' => $withholdingTax,
                'total_amount_due' => $totalAmountDue,
            ])->save();

            $lockedReceipt->items()->delete();

            if ($items->isNotEmpty()) {
                $lockedReceipt->items()->createMany($items->all());
            }

            return $lockedReceipt->fresh(['items']);
        });

        $message = "Official receipt SI No. {$receipt->si_number} updated successfully.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'receipt' => [
                    'id' => $receipt->id,
                    'si_number' => $receipt->si_number,
                    'status' => $receipt->status,
                    'total_amount_due' => (float) $receipt->total_amount_due,
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    private function filteredQuery(array $filters)
    {
        $query = SalesInvoice::query();

        if ($filters['start_date'] !== '') {
            $query->whereDate('invoice_date', '>=', $filters['start_date']);
        }

        if ($filters['end_date'] !== '') {
            $query->whereDate('invoice_date', '<=', $filters['end_date']);
        }

        if ($filters['name'] !== '') {
            $query->where('sold_to', 'like', '%'.trim($filters['name']).'%');
        }

        if ($filters['product'] !== '') {
            $product = trim($filters['product']);

            $query->whereHas('items', function ($itemQuery) use ($product) {
                $itemQuery->where(function ($descriptionQuery) use ($product) {
                    $descriptionQuery->where('item_type', 'like', '%'.$product.'%')
                        ->orWhere('description', 'like', '%'.$product.'%');
                });
            });
        }

        if ($filters['si_from'] !== '') {
            $query->whereRaw($this->siNumberExpression().' >= ?', [(int) $filters['si_from']]);
        }

        if ($filters['si_to'] !== '') {
            $query->whereRaw($this->siNumberExpression().' <= ?', [(int) $filters['si_to']]);
        }

        if ($filters['status'] !== 'all') {
            $query->where('sales_invoices.status', $filters['status']);
        }

        return $query;
    }

    private function siNumberExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'CAST(sales_invoices.si_number AS UNSIGNED)',
            'pgsql' => 'CAST(sales_invoices.si_number AS BIGINT)',
            default => 'CAST(sales_invoices.si_number AS INTEGER)',
        };
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

    private function textOrDefault(mixed $value, string $default): string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : $default;
    }
}
