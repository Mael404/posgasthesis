<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    public const STATUS_PENDING_TANK_DELIVERY_RECORD = 'Pending Tank Delivery Record';
    public const STATUS_TANK_DELIVERY_RECORD_COMPLETED = 'Tank Delivery Record Completed';

    protected $table = 'inventory_logs';

    public $timestamps = false;

    protected $fillable = [
        'delivery_code',
        'delivery_status',
        'tank_id',
        'supplier_id',
        'liters_delivered',
        'total_cost',
        'delivery_date',
        'created_at',
    ];

    public function tankDeliveryRecord()
    {
        return $this->hasOne(TankDeliveryRecord::class, 'inventory_log_id');
    }
}
