<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use stdClass;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Garage extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, HasFactory;
    protected const FALLBACK_IMAGE_COUNT = 10;

    public $table = 'garages';

    protected $appends = [
        'logo',
        'storefront_image',
        'gallery',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public const STATUS_SELECT = [
        'pending' => 'Pending',
        'approved' => 'Approved',
    ];

    public const REGISTERED_BY_SELECT = [
        'surveyor' => 'Surveyor',
        'self' => 'Self',
    ];

    protected $fillable = [
        'user_id',
        'surveyor_id',
        'title',
        'title_ar',
        'description',
        'description_ar',
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
        'is_google_maps',
        'google_place_id',
        'confidence',
        'location_verified'
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
        if (!$file) {
            return $this->buildFallbackMediaObject(1);
        }
        if ($file) {
            // $file->url = $file->getUrl();
            // $file->thumbnail = $file->getUrl('thumb');
            // $file->preview = $file->getUrl('preview');
            // $file->url = 'https://wiseelik.com/images/logo.png';
            // $file->thumbnail = 'https://wiseelik.com/images/logo.png';
            // $file->preview = 'https://wiseelik.com/images/logo.png';
            $file->url = $this->resolveMediaUrlOrFallback($file);
            $file->thumbnail = $file->url;
            $file->preview = $file->url;
        }

        return $file;
    }

    public function getStorefrontImageAttribute()
    {
        $file = $this->getMedia('storefront_image')->last();
        if (!$file) {
            return $this->buildFallbackMediaObject(2);
        }
        if ($file) {
            // $file->url = $file->getUrl();
            // $file->thumbnail = $file->getUrl('thumb');
            // $file->preview = $file->getUrl('preview');
            // $file->url = 'https://wiseelik.com/images/logo.png';
            // $file->thumbnail = 'https://wiseelik.com/images/logo.png';
            // $file->preview = 'https://wiseelik.com/images/logo.png';
            $file->url = $this->resolveMediaUrlOrFallback($file);
            $file->thumbnail = $file->url;
            $file->preview = $file->url;
        }

        return $file;
    }

    public function getGalleryAttribute()
    {
        $files = $this->getMedia('gallery');
        $files->each(function ($item, $index) {
            // $item->url = $item->getUrl();
            // $item->thumbnail = $item->getUrl('thumb');
            // $item->preview = $item->getUrl('preview');
            // $item->url = 'https://wiseelik.com/images/logo.png';
            // $item->thumbnail = 'https://wiseelik.com/images/logo.png';
            // $item->preview = 'https://wiseelik.com/images/logo.png';
            $item->url = $this->resolveMediaUrlOrFallback($item, (int) $index + 3);
            $item->thumbnail = $item->url;
            $item->preview = $item->url;
        });

        if ($files->isEmpty()) {
            $files->push($this->buildFallbackMediaObject(3));
        }

        return $files;
    }

    protected function resolveMediaUrlOrFallback(Media $media, int $seed = 1): string
    {
        $path = method_exists($media, 'getPath') ? $media->getPath() : null;
        $existsOnDisk = $path ? @file_exists($path) : true;

        if ($existsOnDisk) {
            return $media->getUrl();
        }

        return $this->fallbackImageUrl($seed);
    }

    protected function fallbackImageUrl(int $seed = 1): string
    {
        $override = (int) ($this->fallback_index_override ?? 0);
        if ($override >= 1 && $override <= static::FALLBACK_IMAGE_COUNT) {
            $index = (($override + $seed - 2) % static::FALLBACK_IMAGE_COUNT) + 1;
            return "https://wiseelik.com/images/fallback-{$index}.png";
        }

        $id = (int) ($this->id ?? 0);
        $index = ($id > 0)
            ? ((($id + $seed - 1) % static::FALLBACK_IMAGE_COUNT) + 1)
            : ((($seed - 1) % static::FALLBACK_IMAGE_COUNT) + 1);

        return "https://wiseelik.com/images/fallback-{$index}.png";
    }

    protected function buildFallbackMediaObject(int $seed = 1): stdClass
    {
        $url = $this->fallbackImageUrl($seed);
        $file = new stdClass();
        $file->url = $url;
        $file->thumbnail = $url;
        $file->preview = $url;

        return $file;
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class);
    }

    public function specializations()
    {
        return $this->belongsToMany(Specialization::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function work_shifts()
    {
        return $this->hasMany(WorkShift::class, 'garage_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'garage_id');
    }

    public function userBlocks()
    {
        return $this->hasMany(UserBlock::class);
    }

    public function blockedByUsers()
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'garage_id', 'user_id')
            ->withPivot('reason')
            ->withTimestamps();
    }

    public function favorites()
    {
        return $this->hasMany(UserFavorite::class);
    }
}
