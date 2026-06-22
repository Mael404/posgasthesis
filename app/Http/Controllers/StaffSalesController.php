<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StaffSalesController extends Controller
{
    private array $tablePresence = [];
    private array $columnPresence = [];

    public function index(Request $request): View
    {
        [$start, $end, $dateLabel] = $this->dateRange($request);
        $missingSalesTable = ! $this->hasTable('sales');

        if ($missingSalesTable) {
            return view('pages.staff.transactionhistory', [
                'title' => 'Transaction History',
                'transactions' => $this->emptyPaginator($request),
                'summary' => $this->emptySummary(),
                'filters' => $this->filterLabels($request, $start, $end, $dateLabel),
                'missingSalesTable' => true,
            ]);
        }

        $query = $this->filteredSalesQuery($request, $start, $end);
        $summary = $this->summaryFor(clone $query);

        $transactions = $query
            ->orderByDesc('sales.created_at')
            ->orderByDesc('sales.id')
            ->paginate(20)
            ->withQueryString();

        return view('pages.staff.transactionhistory', [
            'title' => 'Transaction History',
            'transactions' => $transactions,
            'summary' => $summary,
            'filters' => $this->filterLabels($request, $start, $end, $dateLabel),
            'missingSalesTable' => false,
        ]);
    }

    public function print(Request $request): View
    {
        [$start, $end, $dateLabel] = $this->dateRange($request);
        $transactions = collect();

        if ($this->hasTable('sales')) {
            $transactions = $this->filteredSalesQuery($request, $start, $end)
                ->orderBy('sales.created_at')
                ->orderBy('sales.id')
                ->get();
        }

        return view('pages.staff.transactionhistory-print', [
            'title' => 'Print Transaction History',
            'transactions' => $transactions,
            'summary' => [
                'records' => $transactions->count(),
                'amount' => (float) $transactions->sum('amount'),
                'liters' => (float) $transactions->sum('liters'),
                'no_receipt' => $transactions->filter(fn ($sale) => trim((string) ($sale->or_number ?? '')) === '')->count(),
            ],
            'filters' => $this->filterLabels($request, $start, $end, $dateLabel),
            'staff' => $request->user(),
        ]);
    }

    private function filteredSalesQuery(Request $request, Carbon $start, Carbon $end)
    {
        $query = $this->salesQuery();

        $this->applyPaymentFilter($query, (string) $request->input('payment_type', ''));
        $this->applyReceiptFilter($query, (string) $request->input('receipt_status', ''));

        if ($this->hasColumn('sales', 'created_at')) {
            $query->whereBetween('sales.created_at', [$start, $end]);
        }

        return $query;
    }

    private function salesQuery()
    {
        $query = DB::table('sales');
        $businessParts = [];
        $customerParts = [];

        if ($this->hasTable('customers') && $this->hasColumn('sales', 'customer_id')) {
            $query->leftJoin('customers', 'sales.customer_id', '=', 'customers.id');

            if ($this->hasColumn('customers', 'business_name')) {
                $businessParts[] = 'customers.business_name';
                $customerParts[] = 'customers.business_name';
            }

            if ($this->hasColumn('customers', 'owner_name')) {
                $customerParts[] = 'customers.owner_name';
            }
        }

        if ($this->hasTable('customers') && $this->hasColumn('sales', 'posted_customer_id')) {
            $query->leftJoin('customers as posted_customers', 'sales.posted_customer_id', '=', 'posted_customers.id');

            if ($this->hasColumn('customers', 'business_name')) {
                $businessParts[] = 'posted_customers.business_name';
                $customerParts[] = 'posted_customers.business_name';
            }

            if ($this->hasColumn('customers', 'owner_name')) {
                $customerParts[] = 'posted_customers.owner_name';
            }
        }

        if ($this->hasColumn('sales', 'customer_name')) {
            $customerParts[] = 'sales.customer_name';
        }

        $query
            ->select('sales.*')
            ->selectRaw($this->coalesceExpression($businessParts) . ' as customer_business_name')
            ->selectRaw($this->coalesceExpression($customerParts, "'Walk-In Customer'") . ' as customer_display_name');

        if ($this->hasColumn('sales', 'user_id')) {
            $query->where('sales.user_id', auth()->id());
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function applyPaymentFilter($query, string $paymentType): void
    {
        $paymentType = strtolower(trim($paymentType));

        if ($paymentType === '') {
            return;
        }

        if ($paymentType === 'cash') {
            $query->whereRaw('LOWER(sales.payment_type) in (?, ?)', ['cash', 'walkin']);
            return;
        }

        if (in_array($paymentType, ['credit', 'collection'], true)) {
            $query->whereRaw('LOWER(sales.payment_type) = ?', [$paymentType]);
        }
    }

    private function applyReceiptFilter($query, string $receiptStatus): void
    {
        if (! $this->hasColumn('sales', 'or_number')) {
            return;
        }

        if ($receiptStatus === 'with_receipt') {
            $query->whereNotNull('sales.or_number')
                ->where('sales.or_number', '!=', '');
            return;
        }

        if ($receiptStatus === 'no_receipt') {
            $query->where(function ($receiptQuery) {
                $receiptQuery->whereNull('sales.or_number')
                    ->orWhere('sales.or_number', '');
            });
        }
    }

    private function dateRange(Request $request): array
    {
        $today = Carbon::today();
        $dateFilter = strtolower((string) $request->input('date_filter', ''));

        if ($dateFilter === 'weekly') {
            return [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek(), 'This Week'];
        }

        if ($dateFilter === 'monthly') {
            return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(), 'This Month'];
        }

        if ($dateFilter === 'today') {
            return [$today->copy()->startOfDay(), $today->copy()->endOfDay(), 'Today'];
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            try {
                $startInput = $request->input('start_date') ?: $request->input('end_date');
                $endInput = $request->input('end_date') ?: $request->input('start_date');
                $start = Carbon::parse($startInput)->startOfDay();
                $end = Carbon::parse($endInput)->endOfDay();

                if ($end->lt($start)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                return [$start, $end, 'Custom Range'];
            } catch (\Throwable) {
                // Fall back to today's records when malformed dates are supplied.
            }
        }

        return [$today->copy()->startOfDay(), $today->copy()->endOfDay(), 'Today'];
    }

    private function filterLabels(Request $request, Carbon $start, Carbon $end, string $dateLabel): array
    {
        return [
            'start' => $start,
            'end' => $end,
            'date_label' => $dateLabel,
            'payment_type' => (string) $request->input('payment_type', ''),
            'payment_label' => $this->paymentLabel((string) $request->input('payment_type', '')),
            'receipt_status' => (string) $request->input('receipt_status', ''),
            'receipt_label' => $this->receiptLabel((string) $request->input('receipt_status', '')),
        ];
    }

    private function paymentLabel(string $paymentType): string
    {
        return match (strtolower(trim($paymentType))) {
            'cash' => 'Cash / Walk-In',
            'credit' => 'Credit Sale',
            'collection' => 'Collection',
            default => 'All Payments',
        };
    }

    private function receiptLabel(string $receiptStatus): string
    {
        return match ($receiptStatus) {
            'with_receipt' => 'With Receipt',
            'no_receipt' => 'No Receipt',
            default => 'All Receipts',
        };
    }

    private function summaryFor($query): array
    {
        $records = (clone $query)->count();
        $amount = $this->hasColumn('sales', 'amount') ? (float) (clone $query)->sum('sales.amount') : 0.0;
        $liters = $this->hasColumn('sales', 'liters') ? (float) (clone $query)->sum('sales.liters') : 0.0;
        $noReceipt = 0;

        if ($this->hasColumn('sales', 'or_number')) {
            $noReceipt = (clone $query)
                ->where(function ($receiptQuery) {
                    $receiptQuery->whereNull('sales.or_number')
                        ->orWhere('sales.or_number', '');
                })
                ->count();
        }

        return [
            'records' => $records,
            'amount' => $amount,
            'liters' => $liters,
            'no_receipt' => $noReceipt,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'records' => 0,
            'amount' => 0.0,
            'liters' => 0.0,
            'no_receipt' => 0,
        ];
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            20,
            LengthAwarePaginator::resolveCurrentPage(),
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
    }

    private function coalesceExpression(array $expressions, string $fallback = "''"): string
    {
        $parts = array_values(array_filter($expressions));

        if ($parts === []) {
            return $fallback;
        }

        $parts[] = $fallback;

        return 'COALESCE(' . implode(', ', $parts) . ')';
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
}
