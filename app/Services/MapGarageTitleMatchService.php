<?php

namespace App\Services;

use App\Models\Garage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapGarageTitleMatchService
{
    private const MATCH_THRESHOLD = 80;

    private const CACHE_TTL_SECONDS = 86400; // 24 hours

    private const KUWAIT_CENTER_LAT = 29.3759;

    private const KUWAIT_CENTER_LNG = 47.9774;

    public function __construct(
        private ?string $placesKey = null,
        private ?string $geocodeKey = null
    ) {
        $this->placesKey = $placesKey ?? env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));
        $this->geocodeKey = $geocodeKey ?? env('GOOGLE_MAPS_API_KEY');
    }

    /**
     * Returns true if the location at the garage's maps link has a title that matches
     * the garage's title or title_ar (green pin), false otherwise (red pin).
     */
    public function titleMatches(Garage $garage): bool
    {
        $cacheKey = 'map_title_match_' . $garage->id;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($garage) {
            return $this->resolveTitleMatch($garage);
        });
    }

    private function resolveTitleMatch(Garage $garage): bool
    {
        $placeName = $this->getPlaceNameFromGoogle($garage);
        if ($placeName === null || $placeName === '') {
            return false;
        }

        $dbTitle = $garage->title ?? '';
        $dbTitleAr = $garage->title_ar ?? '';

        $similarityTitle = $this->nameSimilarity($placeName, $dbTitle);
        $similarityTitleAr = $this->nameSimilarity($placeName, $dbTitleAr);

        return $similarityTitle >= self::MATCH_THRESHOLD || $similarityTitleAr >= self::MATCH_THRESHOLD;
    }

    /**
     * Get the place name at the garage's location from Google (Place Details or reverse geocode).
     */
    private function getPlaceNameFromGoogle(Garage $garage): ?string
    {
        if ($this->placesKey && $garage->google_place_id) {
            $name = $this->getPlaceDetailsName($garage->google_place_id);
            if ($name !== null) {
                return $name;
            }
        }

        if ($this->geocodeKey && $garage->latitude !== null && $garage->longitude !== null) {
            return $this->getReverseGeocodeName((float) $garage->latitude, (float) $garage->longitude);
        }

        return null;
    }

    private function getPlaceDetailsName(string $placeId): ?string
    {
        $url = 'https://maps.googleapis.com/maps/api/place/details/json';
        $params = [
            'place_id' => $placeId,
            'fields' => 'name',
            'key' => $this->placesKey,
        ];

        try {
            $response = Http::timeout(10)->get($url, $params);
            if (! $response->successful()) {
                return null;
            }
            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK' || ! isset($data['result']['name'])) {
                return null;
            }

            return $data['result']['name'];
        } catch (\Throwable $e) {
            Log::warning('MapGarageTitleMatchService Place Details: ' . $e->getMessage());

            return null;
        }
    }

    private function getReverseGeocodeName(float $lat, float $lng): ?string
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json';
        $params = [
            'latlng' => $lat . ',' . $lng,
            'key' => $this->geocodeKey,
        ];

        try {
            $response = Http::timeout(10)->get($url, $params);
            if (! $response->successful()) {
                return null;
            }
            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) {
                return null;
            }
            $first = $data['results'][0] ?? [];
            // Prefer formatted_address for reverse geocode (no place name; address is the "title" of location)
            return $first['formatted_address'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('MapGarageTitleMatchService Geocode: ' . $e->getMessage());

            return null;
        }
    }

    private function nameSimilarity(string $name1, string $name2): int
    {
        $a = $this->normalizeNameForMatch($name1);
        $b = $this->normalizeNameForMatch($name2);
        if ($a === '' || $b === '') {
            return 0;
        }
        similar_text($a, $b, $percent);
        $forward = (float) $percent;
        similar_text($b, $a, $percent);
        $reverse = (float) $percent;

        return (int) round(max($forward, $reverse));
    }

    private function normalizeNameForMatch(string $name): string
    {
        $name = trim($name);
        $name = mb_strtolower($name);
        $name = preg_replace('/\s+/', ' ', $name);
        $remove = ['garage', 'workshop', 'car repair', 'the', 'ـ', '،', ','];
        foreach ($remove as $word) {
            $name = preg_replace('/\b' . preg_quote($word, '/') . '\b/u', ' ', $name);
        }
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }
}
