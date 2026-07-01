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

        if (! $reading->is_voided_settlement) {
            return;
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

        return $payload;
    }
}
