<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'bookings';

    protected $dates = [
        'appointment_time',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public const STATUS_SELECT = [
        'pending'   => 'Pending',
        'rejected'  => 'Rejected',
        'approved'  => 'Approved',
        'completed' => 'Completed',
    ];

    public const PROCESSING_STATUS_SELECT = [
        'n/a'        => 'N/A',
        'picked'     => 'Picked',
        'in_service' => 'In Service',
        'returned'   => 'Returned',
    ];

    protected $fillable = [
        'reference_no',
        'user_id',
        'garage_id',
        'appointment_time',
        'status',
        'processing_status',
        'garage_remarks',
        'client_remarks',
        'client_rating',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function garage()
    {
        return $this->belongsTo(User::class, 'garage_id');
    }

    public function getAppointmentTimeAttribute($value)
    {
        return $value ? Carbon::createFromFormat('Y-m-d H:i:s', $value)->format(config('panel.date_format') . ' ' . config('panel.time_format')) : null;
    }

    public function setAppointmentTimeAttribute($value)
    {
        $this->attributes['appointment_time'] = $value ? Carbon::createFromFormat(config('panel.date_format') . ' ' . config('panel.time_format'), $value)->format('Y-m-d H:i:s') : null;
    }
}
