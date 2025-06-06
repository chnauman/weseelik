<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Message;
use App\Models\Garage;
use App\Models\UserDetail;
use App\Models\VehicleBrand;
use App\Models\UserVehicleBrand;
use App\Models\UserFavorite;
use App\Models\UserBlock;

class User extends Authenticatable
{
    use SoftDeletes, Notifiable, HasFactory, HasApiTokens;

    public $table = 'users';

    protected $hidden = [
        'remember_token',
        'password',
    ];

    public const TYPE_SELECT = [
        'client'   => 'Client',
        'business' => 'Business',
    ];

    protected $dates = [
        'email_verified_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        'type',
        'is_profile_complete',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_profile_complete' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function getIsAdminAttribute()
    {
        return $this->roles()->where('id', 1)->exists();
    }

    public function getEmailVerifiedAtAttribute($value)
    {
        return $value ? Carbon::createFromFormat('Y-m-d H:i:s', $value)->format(config('panel.date_format') . ' ' . config('panel.time_format')) : null;
    }

    public function setEmailVerifiedAtAttribute($value)
    {
        $this->attributes['email_verified_at'] = $value ? Carbon::createFromFormat(config('panel.date_format') . ' ' . config('panel.time_format'), $value)->format('Y-m-d H:i:s') : null;
    }

    public function setPasswordAttribute($input)
    {
        if ($input) {
            $this->attributes['password'] = app('hash')->needsRehash($input) ? Hash::make($input) : $input;
        }
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function userBlocks()
    {
        return $this->hasMany(UserBlock::class);
    }

    public function favorties()
    {
        return $this->hasMany(UserFavorite::class);
    }

    public function blockedGarages()
    {
        return $this->belongsToMany(Garage::class, 'user_blocks', 'user_id', 'garage_id')
            ->withPivot('reason')
            ->withTimestamps();
    }

    /**
     * Get the user's details.
     */
    public function detail()
    {
        return $this->hasOne(UserDetail::class);
    }

    /**
     * Get the user's primary vehicle brand.
     */
    public function primaryVehicleBrand()
    {
        return $this->belongsTo(VehicleBrand::class, 'primary_vehicle_brand_id');
    }

    /**
     * Get all the user's vehicle brands.
     */
    public function vehicleBrands()
    {
        return $this->belongsToMany(VehicleBrand::class, 'user_vehicle_brands')
            ->withPivot('is_primary', 'model', 'year', 'color', 'license_plate')
            ->withTimestamps();
    }

    /**
     * Get all the user's vehicle relationships.
     */
    public function userVehicleBrands()
    {
        return $this->hasMany(UserVehicleBrand::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function garages()
    {
        return $this->hasMany(Garage::class);
    }
}
