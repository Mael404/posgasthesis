<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceItem extends Model
{
    public const UNLEADED_DISPLAY_NAME = 'Unleaded/Regular';

    protected $fillable = [
        'sort_order',
        'item_type',
        'description',
        'quantity',
        'price',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public static function defaultDescription(string $itemType): string
    {
        return $itemType === 'Unleaded' ? self::UNLEADED_DISPLAY_NAME : $itemType;
    }

    public function displayDescription(): string
    {
        if ((string) $this->item_type === 'Unleaded') {
            return self::UNLEADED_DISPLAY_NAME;
        }

        return (string) $this->description;
    }
}
