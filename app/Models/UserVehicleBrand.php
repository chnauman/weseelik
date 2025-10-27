<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserVehicleBrand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'vehicle_brand_id',
        'is_primary',
        'model',
        'year',
        'color',
        'license_plate',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Get the user that owns the vehicle.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vehicle brand.
     */
    public function vehicleBrand()
    {
        return $this->belongsTo(VehicleBrand::class);
    }
}
