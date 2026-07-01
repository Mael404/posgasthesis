<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TankDeliveryRecord extends Model
{
    public const FUEL_TYPES = [
        'premium' => 'Premium',
        'unleaded' => 'Unleaded',
        'diesel' => 'Diesel',
    ];

    protected $fillable = [
        'inventory_log_id',
        'delivery_code',
        'staff_id',
        'record_date',
        'record_time',
        'plate_number',
        'security_seals_passed',
        'product_level_passed',
        'ethanol_blend_passed',
        'free_of_water_passed',
        'fully_drained_compartment_passed',
        'before_unloading_dipstick_premium',
        'after_unloading_dipstick_premium',
        'total_dispensed_premium',
        'after_before_total_premium',
        'delivery_summary_premium',
        'before_unloading_dipstick_unleaded',
        'after_unloading_dipstick_unleaded',
        'total_dispensed_unleaded',
        'after_before_total_unleaded',
        'delivery_summary_unleaded',
        'before_unloading_dipstick_diesel',
        'after_unloading_dipstick_diesel',
        'total_dispensed_diesel',
        'after_before_total_diesel',
        'delivery_summary_diesel',
        'verified_by',
        'verified_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'record_date' => 'date',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'security_seals_passed' => 'boolean',
        'product_level_passed' => 'boolean',
        'ethanol_blend_passed' => 'boolean',
        'free_of_water_passed' => 'boolean',
        'fully_drained_compartment_passed' => 'boolean',
    ];

    public function inventoryLog()
    {
        return $this->belongsTo(InventoryLog::class, 'inventory_log_id');
    }
}
