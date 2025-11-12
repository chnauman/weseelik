<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GarageScraped extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, HasFactory;

    public $table = 'garages_scraped';

    protected $appends = [
        'logo',
        'storefront_image',
        'gallery',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'scraped_at',
    ];

    public const STATUS_SELECT = [
        'pending'  => 'Pending',
        'approved' => 'Approved',
    ];

    public const REGISTERED_BY_SELECT = [
        'surveyor' => 'Surveyor',
        'self'     => 'Self',
        'scraper'  => 'Scraper',
    ];

    public const SCRAPING_STATUS_SELECT = [
        'pending'   => 'Pending',
        'completed' => 'Completed',
        'failed'    => 'Failed',
    ];

    protected $fillable = [
        'google_place_id',
        'scraped_at',
        'scraping_status',
        'scraping_errors',
        'user_id',
        'surveyor_id',
        'title',
        'description',
        'service_description',
        'contact_no',
        'address',
        'city',
        'country_id',
        'latitude',
        'longitude',
        'status',
        'rating',
        'registered_by',
        'created_at',
        'updated_at',
        'deleted_at',
        'no_of_visits',
        'no_of_likes',
        'governate',
        'area',
        'block',
        'maps_share_link',
        'home_service',
        'pickup_service',
        'is_available',
        'confidence',
        'location_verified',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit('crop', 50, 50);
        $this->addMediaConversion('preview')->fit('crop', 120, 120);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function surveyor()
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function getLogoAttribute()
    {
        $file = $this->getMedia('logo')->last();
        if ($file) {
            $file->url       = $file->getUrl();
            $file->thumbnail = $file->getUrl('thumb');
            $file->preview   = $file->getUrl('preview');
        }

        return $file;
    }

    public function getStorefrontImageAttribute()
    {
        $file = $this->getMedia('storefront_image')->last();
        if ($file) {
            $file->url       = $file->getUrl();
            $file->thumbnail = $file->getUrl('thumb');
            $file->preview   = $file->getUrl('preview');
        }

        return $file;
    }

    public function getGalleryAttribute()
    {
        $files = $this->getMedia('gallery');
        $files->each(function ($item) {
            $item->url       = $item->getUrl();
            $item->thumbnail = $item->getUrl('thumb');
            $item->preview   = $item->getUrl('preview');
        });

        return $files;
    }
}

