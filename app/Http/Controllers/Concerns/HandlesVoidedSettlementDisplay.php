<?php

namespace App\Http\Controllers\Concerns;

trait HandlesVoidedSettlementDisplay
{
    protected function settlementIsVoided(object $reading): bool
    {
        return str_contains(strtolower(trim((string) ($reading->status ?? ''))), 'void');
    }

    protected function storedSettlementNumber(object $reading, string $field): ?float
    {
        if (! property_exists($reading, $field)) {
            return null;
        }

        $value = $reading->$field;

        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = preg_replace('/[^0-9.\-]/', '', $value);
        }

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return $this->money((float) $value);
    }

    protected function applyVoidedSettlementDisplayState(
        object $reading,
        ?float $storedExpectedCollection,
        ?float $storedCashVariance
    ): void {
        $reading->is_voided_settlement = $this->settlementIsVoided($reading);
        $reading->expected_collection_snapshot = null;
        $reading->cash_variance_snapshot = null;
        $reading->voided_details_source = null;

        if (! $reading->is_voided_settlement) {
            return;
        }

        $snapshot = $this->decodedSettlementSnapshot($reading);

        if ($snapshot !== null) {
            $this->applySettlementSnapshotReading($reading, $snapshot['reading'] ?? []);
            $reading->voided_details_source = 'settlement_snapshot';
            $storedExpectedCollection ??= $this->settlementSnapshotNumber($snapshot, 'expected_collection');
            $storedCashVariance ??= $this->settlementSnapshotNumber($snapshot, 'cash_variance');
        }

        if ($storedExpectedCollection !== null) {
            $reading->expected_collection_snapshot = $storedExpectedCollection;
            $reading->expected_collection = $storedExpectedCollection;
        }

        if ($storedCashVariance !== null) {
            $reading->cash_variance_snapshot = $storedCashVariance;
        }

        $reading->cash_variance = 0.0;
    }

    protected function settlementSnapshotPayload(object $reading): array
    {
        $payload = [];

        if ($this->hasColumn('shift_readings', 'expected_collection_snapshot')) {
            $payload['expected_collection_snapshot'] = $this->money($reading->expected_collection ?? 0);
        }

        if ($this->hasColumn('shift_readings', 'cash_variance_snapshot')) {
            $payload['cash_variance_snapshot'] = $this->money($reading->cash_variance ?? 0);
        }

        if ($this->hasColumn('shift_readings', 'settlement_snapshot')) {
            $payload['settlement_snapshot'] = json_encode($this->buildSettlementSnapshot($reading));
        }

        return $payload;
    }

    protected function settlementDetailBreakdowns(object $reading): array
    {
        if (! $this->settlementIsVoided($reading)) {
            return $this->getTransactionBreakdowns($reading);
        }

        $snapshotBreakdowns = $this->settlementSnapshotBreakdowns($reading);

        if ($snapshotBreakdowns !== null) {
            $reading->voided_details_source = 'settlement_snapshot';

            return $snapshotBreakdowns;
        }

        $reading->voided_details_source = 'linked_transactions_fallback';

        $breakdowns = $this->getTransactionBreakdowns($reading, true);
        $this->applyVoidedBreakdownTotals($reading, $breakdowns);

        return $breakdowns;
    }

    protected function scopeVoidedSettlementHistoricalSales($query, object $reading)
    {
        if (! isset($reading->id)) {
            return $query->whereRaw('1 = 0');
        }

        $readingId = (int) $reading->id;
        $linkColumns = collect([
            'locked_by_shift_reading_id',
            'shift_reading_id',
            'settlement_id',
        ])->filter(fn ($column) => $this->hasColumn('sales', $column))->values();

        if ($linkColumns->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($query) use ($linkColumns, $readingId) {
            foreach ($linkColumns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}("sales.{$column}", $readingId);
            }
        });
    }

    protected function emptySettlementBreakdowns(): array
    {
        return [
            'credit_sales' => [],
            'payments_collected' => [],
            'excess_markups' => [],
            'system_discounts' => [],
            'manual_discounts' => [],
        ];
    }

    protected function decodedSettlementSnapshot(object $reading): ?array
    {
        if (! property_exists($reading, 'settlement_snapshot')) {
            return null;
        }

        $snapshot = $reading->settlement_snapshot;

        if ($snapshot === null || $snapshot === '') {
            return null;
        }

        if (is_object($snapshot)) {
            $snapshot = (array) $snapshot;
        }

        if (is_array($snapshot)) {
            return $snapshot;
        }

        $decoded = json_decode((string) $snapshot, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function buildSettlementSnapshot(object $reading): array
    {
        return [
            'version' => 1,
            'captured_at' => now()->toDateTimeString(),
            'reading' => $this->settlementSnapshotReading($reading),
            'transaction_breakdowns' => $this->normalizeSettlementBreakdowns($this->getTransactionBreakdowns($reading)),
        ];
    }

    private function settlementSnapshotReading(object $reading): array
    {
        $snapshot = [];

        foreach ($this->settlementSnapshotReadingFields() as $field) {
            if (property_exists($reading, $field)) {
                $snapshot[$field] = $this->settlementSnapshotValue($reading->$field);
            }
        }

        return $snapshot;
    }

    private function applySettlementSnapshotReading(object $reading, $snapshotReading): void
    {
        if (is_object($snapshotReading)) {
            $snapshotReading = (array) $snapshotReading;
        }

        if (! is_array($snapshotReading)) {
            return;
        }

        $allowedFields = $this->settlementSnapshotReadingFields();

        foreach ($snapshotReading as $field => $value) {
            if (in_array($field, $allowedFields, true)) {
                $reading->$field = $value;
            }
        }
    }

    private function settlementSnapshotBreakdowns(object $reading): ?array
    {
        $snapshot = $this->decodedSettlementSnapshot($reading);

        if ($snapshot === null || ! array_key_exists('transaction_breakdowns', $snapshot)) {
            return null;
        }

        return $this->normalizeSettlementBreakdowns($snapshot['transaction_breakdowns']);
    }

    private function normalizeSettlementBreakdowns($breakdowns): array
    {
        $normalized = $this->emptySettlementBreakdowns();

        if (is_object($breakdowns)) {
            $breakdowns = (array) $breakdowns;
        }

        if (! is_array($breakdowns)) {
            return $normalized;
        }

        foreach (array_keys($normalized) as $key) {
            $rows = $breakdowns[$key] ?? [];

            if (is_object($rows)) {
                $rows = (array) $rows;
            }

            if (! is_array($rows)) {
                continue;
            }

            $normalized[$key] = array_values(array_map(
                fn ($row) => $this->settlementSnapshotValue($row),
                $rows
            ));
        }

        return $normalized;
    }

    private function settlementSnapshotNumber(?array $snapshot, string $field): ?float
    {
        $reading = is_array($snapshot) ? ($snapshot['reading'] ?? []) : [];

        if (is_object($reading)) {
            $reading = (array) $reading;
        }

        if (! is_array($reading) || ! array_key_exists($field, $reading)) {
            return null;
        }

        $value = $reading[$field];

        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = preg_replace('/[^0-9.\-]/', '', $value);
        }

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return $this->money((float) $value);
    }

    private function applyVoidedBreakdownTotals(object $reading, array $breakdowns): void
    {
        if (! $this->settlementBreakdownsHaveRows($breakdowns)) {
            return;
        }

        $grossCreditSales = $this->breakdownTotal($breakdowns['credit_sales'] ?? [], 'amount');
        $paymentsCollected = $this->breakdownTotal($breakdowns['payments_collected'] ?? [], 'amount');
        $autoDiscount = $this->breakdownTotal($breakdowns['system_discounts'] ?? [], 'discount');
        $manualDiscount = $this->breakdownTotal($breakdowns['manual_discounts'] ?? [], 'manual_discount');
        $surcharge = $this->breakdownTotal($breakdowns['excess_markups'] ?? [], 'surcharge');
        $expectedCollection = $this->money(
            ($this->number($reading->total_pump_sales ?? 0) + $surcharge + $paymentsCollected)
            - $grossCreditSales
            - $autoDiscount
            - $manualDiscount
        );
        $cashVariance = $this->money($this->number($reading->total_remitted ?? 0) - $expectedCollection);

        $reading->gross_credit_sales = $grossCreditSales;
        $reading->payments_collected = $paymentsCollected;
        $reading->auto_discount = $autoDiscount;
        $reading->manual_discount = $manualDiscount;
        $reading->surcharge = $surcharge;

        if ($reading->expected_collection_snapshot === null) {
            $reading->expected_collection_snapshot = $expectedCollection;
            $reading->expected_collection = $expectedCollection;
        }

        if ($reading->cash_variance_snapshot === null) {
            $reading->cash_variance_snapshot = $cashVariance;
        }

        $reading->cash_variance = 0.0;
    }

    private function settlementBreakdownsHaveRows(array $breakdowns): bool
    {
        foreach ($this->emptySettlementBreakdowns() as $key => $rows) {
            if (! empty($breakdowns[$key])) {
                return true;
            }
        }

        return false;
    }

    private function breakdownTotal($rows, string $field): float
    {
        if (is_object($rows)) {
            $rows = (array) $rows;
        }

        if (! is_array($rows)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }

            if (! is_array($row)) {
                continue;
            }

            $total += $this->number($row[$field] ?? 0);
        }

        return $this->money($total);
    }

    private function settlementSnapshotValue($value)
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->settlementSnapshotValue($item), $value);
        }

        if (is_object($value)) {
            return $this->settlementSnapshotValue((array) $value);
        }

        return $value;
    }

    private function settlementSnapshotReadingFields(): array
    {
        $fields = [
            'id',
            'user_id',
            'staff_name',
            'period_from',
            'period_to',
            'created_at',
            'total_pump_sales',
            'total_pump_liters',
            'surcharge',
            'payments_collected',
            'gross_credit_sales',
            'auto_discount',
            'manual_discount',
            'expected_collection',
            'cash_on_hand',
            'gcash_amount',
            'cheque_amount',
            'total_remitted',
            'cash_variance',
            'cash_denominations',
            'gcash_entries',
            'cheque_entries',
            'dipstick_premium_start',
            'dipstick_regular_start',
            'dipstick_diesel_start',
            'dipstick_premium',
            'dipstick_regular',
            'dipstick_diesel',
        ];

        for ($pump = 1; $pump <= 6; $pump++) {
            foreach (['sales_start', 'sales_end', 'liters_start', 'liters_end', 'dispensed_sales', 'dispensed_liters'] as $suffix) {
                $fields[] = "pump{$pump}_{$suffix}";
            }
        }

        return $fields;
    }
}
