<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    public const STATUS_ACTIVE = 'Active';

    public const STATUS_CANCELED = 'Canceled';

    protected $fillable = [
        'staff_id',
        'invoice_date',
        'sold_to',
        'tin',
        'terms',
        'address',
        'discount_id_no',
        'signature',
        'si_number',
        'authorized_representative',
        'status',
        'canceled_at',
        'canceled_by',
        'cancellation_reason',
        'vatable_sales',
        'vat_exempt_sales',
        'zero_rated_sales',
        'vat_amount',
        'total_sales_vat_inclusive',
        'less_vat',
        'amount_net_of_vat',
        'sc_pwd_discount',
        'add_vat',
        'withholding_tax',
        'total_amount_due',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'canceled_at' => 'datetime',
            'vatable_sales' => 'decimal:2',
            'vat_exempt_sales' => 'decimal:2',
            'zero_rated_sales' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_sales_vat_inclusive' => 'decimal:2',
            'less_vat' => 'decimal:2',
            'amount_net_of_vat' => 'decimal:2',
            'sc_pwd_discount' => 'decimal:2',
            'add_vat' => 'decimal:2',
            'withholding_tax' => 'decimal:2',
            'total_amount_due' => 'decimal:2',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class)->orderBy('sort_order');
    }

    public function formattedSiNumber(): string
    {
        return str_pad((string) $this->si_number, 3, '0', STR_PAD_LEFT);
    }

    public function markCanceled(?int $canceledBy = null, ?string $reason = null): bool
    {
        if ($this->status === self::STATUS_CANCELED) {
            return true;
        }

        return $this->forceFill([
            'status' => self::STATUS_CANCELED,
            'canceled_at' => now(),
            'canceled_by' => $canceledBy,
            'cancellation_reason' => $reason,
        ])->save();
    }

    protected static function booted(): void
    {
        static::updating(function (SalesInvoice $invoice) {
            if ($invoice->isDirty('si_number')) {
                throw new \LogicException('A sales invoice SI number cannot be changed.');
            }
        });
    }
}
