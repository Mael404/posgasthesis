<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'tank_delivery_records';
    private const DELIVERY_CODE_INDEX = 'tank_delivery_records_delivery_code_index';
    private const INVENTORY_LOG_UNIQUE = 'tank_delivery_records_inventory_log_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (
            Schema::hasColumn(self::TABLE, 'inventory_log_id')
            && ! $this->hasIndexOnColumns(self::TABLE, ['inventory_log_id'], true)
            && $this->hasDuplicateInventoryLogIds()
        ) {
            throw new \RuntimeException(
                'Cannot add a unique index to tank_delivery_records.inventory_log_id because duplicate inventory_log_id values already exist.'
            );
        }

        if (Schema::hasColumn(self::TABLE, 'delivery_code')) {
            foreach ($this->indexNamesOnColumns(self::TABLE, ['delivery_code'], true) as $indexName) {
                $this->dropIndex(self::TABLE, $indexName);
            }

            if (! $this->hasIndexOnColumns(self::TABLE, ['delivery_code'], false)) {
                $this->dropIndexIfExists(self::TABLE, self::DELIVERY_CODE_INDEX);

                DB::statement(sprintf(
                    'ALTER TABLE %s ADD INDEX %s (%s)',
                    $this->quoteIdentifier(self::TABLE),
                    $this->quoteIdentifier(self::DELIVERY_CODE_INDEX),
                    $this->quoteIdentifier('delivery_code')
                ));
            }
        }

        if (
            Schema::hasColumn(self::TABLE, 'inventory_log_id')
            && ! $this->hasIndexOnColumns(self::TABLE, ['inventory_log_id'], true)
        ) {
            $this->dropIndexIfExists(self::TABLE, self::INVENTORY_LOG_UNIQUE);

            DB::statement(sprintf(
                'ALTER TABLE %s ADD UNIQUE %s (%s)',
                $this->quoteIdentifier(self::TABLE),
                $this->quoteIdentifier(self::INVENTORY_LOG_UNIQUE),
                $this->quoteIdentifier('inventory_log_id')
            ));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (Schema::hasColumn(self::TABLE, 'inventory_log_id')) {
            $this->dropIndexIfExists(self::TABLE, self::INVENTORY_LOG_UNIQUE);
        }

        if (
            Schema::hasColumn(self::TABLE, 'delivery_code')
            && ! $this->hasDuplicateDeliveryCodes()
        ) {
            $this->dropIndexIfExists(self::TABLE, self::DELIVERY_CODE_INDEX);

            if (! $this->hasIndexOnColumns(self::TABLE, ['delivery_code'], true)) {
                DB::statement(sprintf(
                    'ALTER TABLE %s ADD UNIQUE %s (%s)',
                    $this->quoteIdentifier(self::TABLE),
                    $this->quoteIdentifier('tank_delivery_records_delivery_code_unique'),
                    $this->quoteIdentifier('delivery_code')
                ));
            }
        }
    }

    private function hasDuplicateInventoryLogIds(): bool
    {
        return DB::table(self::TABLE)
            ->select('inventory_log_id')
            ->whereNotNull('inventory_log_id')
            ->groupBy('inventory_log_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();
    }

    private function hasDuplicateDeliveryCodes(): bool
    {
        return DB::table(self::TABLE)
            ->select('delivery_code')
            ->whereNotNull('delivery_code')
            ->groupBy('delivery_code')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();
    }

    private function hasIndexOnColumns(string $table, array $columns, bool $unique): bool
    {
        return $this->indexNamesOnColumns($table, $columns, $unique) !== [];
    }

    private function indexNamesOnColumns(string $table, array $columns, bool $unique): array
    {
        $indexNames = [];

        foreach ($this->indexes($table) as $indexName => $index) {
            if ($indexName === 'PRIMARY') {
                continue;
            }

            if ($index['unique'] === $unique && $index['columns'] === $columns) {
                $indexNames[] = $indexName;
            }
        }

        return $indexNames;
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! array_key_exists($indexName, $this->indexes($table))) {
            return;
        }

        $this->dropIndex($table, $indexName);
    }

    private function dropIndex(string $table, string $indexName): void
    {
        if ($indexName === 'PRIMARY') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE %s DROP INDEX %s',
            $this->quoteIdentifier($table),
            $this->quoteIdentifier($indexName)
        ));
    }

    private function indexes(string $table): array
    {
        $rows = DB::select(sprintf('SHOW INDEX FROM %s', $this->quoteIdentifier($table)));
        $indexes = [];

        foreach ($rows as $row) {
            $indexName = $row->Key_name ?? null;
            $columnName = $row->Column_name ?? null;

            if ($indexName === null || $columnName === null) {
                continue;
            }

            $indexes[$indexName] ??= [
                'columns' => [],
                'unique' => ((int) ($row->Non_unique ?? 1)) === 0,
            ];

            $indexes[$indexName]['columns'][(int) ($row->Seq_in_index ?? 1)] = $columnName;
        }

        foreach ($indexes as $indexName => $index) {
            ksort($index['columns']);
            $index['columns'] = array_values($index['columns']);
            $indexes[$indexName] = $index;
        }

        return $indexes;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
};
