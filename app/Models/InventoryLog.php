<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    protected $table = 'inventory_logs';

    public $timestamps = false;

    protected $fillable = [
        'delivery_code',
        'tank_id',
        'supplier_id',
        'liters_delivered',
        'total_cost',
        'delivery_date',
        'created_at',
    ];
}
