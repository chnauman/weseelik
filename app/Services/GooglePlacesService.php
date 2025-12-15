<?php

namespace App\Services;

use App\Models\Garage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;

class GooglePlacesService
{
    private string $googlePlacesApiKey;
    private int $radius = 50000; // 50,000 meters (50km) - maximum allowed by Google Places API
    private int $delayBetweenRequests = 2; // seconds

    public function __construct()
    {
        $this->googlePlacesApiKey = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));
    }

    /**
     * Fetch and store garages for an area
     *
     * @param string $area
     * @param float $lat
     * @param float $lng
     * @param callable|null $callback Called for each garage processed: callback($garage, $status)
     * @param int $limit Maximum number of new garages to fetch (default: 5)
     * @return void
     */
    public function fetchAndStoreGarages(string $area, float $lat, float $lng, ?callable $callback = null, int $limit = 5): void
    {
        // Get already fetched place IDs for this area from cache
        $cacheKey = "fetched_place_ids_{$area}";
        $fetchedPlaceIds = Cache::get($cacheKey, []);
        
        // Search for nearby places
        $nearbyPlaces = $this->searchNearbyPlaces($lat, $lng);

        if (empty($nearbyPlaces)) {
            return;
        }

        $newGaragesCount = 0;
        $updatedPlaceIds = $fetchedPlaceIds;

        // Process each nearby place
        foreach ($nearbyPlaces as $place) {
            // Stop if we've reached the limit
            if ($newGaragesCount >= $limit) {
                break;
            }

            $placeId = $place['place_id'] ?? null;
            
            // Skip if this place was already fetched in a previous request
            if ($placeId && in_array($placeId, $fetchedPlaceIds)) {
                if ($callback) {
                    $callback(null, 'skipped');
                }
                continue;
            }

            // Check if garage already exists in database
            if ($this->garageExists($place)) {
                // Add to fetched list even if it exists (to skip in future requests)
                if ($placeId && !in_array($placeId, $updatedPlaceIds)) {
                    $updatedPlaceIds[] = $placeId;
                }
                if ($callback) {
                    $callback(null, 'skipped');
                }
                continue;
            }

            // Get place details
            $placeDetails = $this->getPlaceDetails($placeId);
            
            if (!$placeDetails) {
                if ($callback) {
                    $callback(null, 'failed');
                }
                continue;
            }

            // Merge basic place data with details
            $fullPlaceData = array_merge($place, $placeDetails);

            // Create garage record
            $newGarage = $this->createGarageFromPlace($fullPlaceData, $area);

            if ($newGarage) {
                $newGaragesCount++;
                // Add place ID to fetched list
                if ($placeId && !in_array($placeId, $updatedPlaceIds)) {
                    $updatedPlaceIds[] = $placeId;
                }
                
                if ($callback) {
                    $callback($newGarage, 'created');
                }
            } else {
                if ($callback) {
                    $callback(null, 'failed');
                }
            }

            // Delay between places
            sleep($this->delayBetweenRequests);
        }

        // Update cache with fetched place IDs (store for 30 days)
        Cache::put($cacheKey, $updatedPlaceIds, 2592000);
    }

    /**
     * Search for nearby places using Google Places Nearby Search API
     * Limits to 3 pages (60 results max) to avoid excessive API calls
     */
    private function searchNearbyPlaces(float $lat, float $lng): array
    {
        $allResults = [];
        $nextPageToken = null;
        $maxPages = 3; // Limit to 3 pages (60 results) - enough for pagination

        do {
            $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json";
            
            $params = [
                'location' => "{$lat},{$lng}",
                'radius' => $this->radius,
                'type' => 'car_repair|gas_station|establishment',
                'key' => $this->googlePlacesApiKey,
            ];

            if ($nextPageToken) {
                $params['pagetoken'] = $nextPageToken;
                // Must wait before using pagetoken
                sleep(2);
            }

            try {
                $response = Http::timeout(30)->get($url, $params);
                
                if (!$response->successful()) {
                    Log::warning('Google Places Nearby Search failed: ' . $response->status());
                    break;
                }

                $data = $response->json();

                if ($data && isset($data['status']) && $data['status'] === 'OK' && !empty($data['results'])) {
                    $allResults = array_merge($allResults, $data['results']);
                    $nextPageToken = $data['next_page_token'] ?? null;
                } elseif (isset($data['status']) && $data['status'] === 'ZERO_RESULTS') {
                    break;
                } else {
                    Log::warning('Google Places Nearby Search error: ' . ($data['status'] ?? 'UNKNOWN'));
                    break;
                }

                // Limit pages
                $maxPages--;
                if ($maxPages <= 0) {
                    break;
                }

            } catch (\Exception $e) {
                Log::error('Google Places Nearby Search exception: ' . $e->getMessage());
                break;
            }

        } while ($nextPageToken);

        return $allResults;
    }

    /**
     * Get detailed place information
     */
    private function getPlaceDetails(?string $placeId): ?array
    {
        if (!$placeId) {
            return null;
        }

        $url = "https://maps.googleapis.com/maps/api/place/details/json";
        
        $fields = [
            'name',
            'formatted_address',
            'formatted_phone_number',
            'international_phone_number',
            'website',
            'rating',
            'user_ratings_total',
            'types',
            'geometry',
            'opening_hours',
            'price_level',
            'address_components',
            'business_status',
            'vicinity',
            'reviews',
        ];

        $params = [
            'place_id' => $placeId,
            'fields' => implode(',', $fields),
            'key' => $this->googlePlacesApiKey,
        ];

        try {
            $response = Http::timeout(30)->get($url, $params);
            
            if (!$response->successful()) {
                Log::warning('Google Places Details failed: ' . $response->status());
                return null;
            }

            $data = $response->json();

            if ($data && isset($data['status']) && $data['status'] === 'OK' && isset($data['result'])) {
                return $data['result'];
            } else {
                Log::warning('Google Places Details error: ' . ($data['status'] ?? 'UNKNOWN'));
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Google Places Details exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if garage already exists in database
     */
    private function garageExists(array $place): bool
    {
        $placeId = $place['place_id'] ?? null;
        $name = $place['name'] ?? null;
        $geometry = $place['geometry'] ?? [];
        $location = $geometry['location'] ?? [];
        $lat = $location['lat'] ?? null;
        $lng = $location['lng'] ?? null;

        // Check by Google Place ID
        if ($placeId) {
            $exists = Garage::where('google_place_id', $placeId)
                ->whereNull('deleted_at')
                ->exists();
            
            if ($exists) {
                return true;
            }
        }

        // Check by name similarity (fuzzy match)
        if ($name) {
            $similar = Garage::whereNull('deleted_at')
                ->where(function ($query) use ($name) {
                    $query->where('title', 'LIKE', "%{$name}%")
                          ->orWhere('title_ar', 'LIKE', "%{$name}%");
                })
                ->exists();
            
            if ($similar) {
                return true;
            }
        }

        // Check by coordinates (within 50 meters)
        if ($lat && $lng) {
            $nearbyGarages = Garage::whereNull('deleted_at')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get();

            foreach ($nearbyGarages as $garage) {
                $distance = $this->calculateDistance(
                    $lat,
                    $lng,
                    (float)$garage->latitude,
                    (float)$garage->longitude
                );

                if ($distance <= 50) { // 50 meters
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Create garage record from Google Place data
     */
    private function createGarageFromPlace(array $placeData, string $area): ?Garage
    {
        try {
            $geometry = $placeData['geometry'] ?? [];
            $location = $geometry['location'] ?? [];
            $addressComponents = $placeData['address_components'] ?? [];

            // Extract address components
            $city = null;
            $governate = null;
            $block = null;

            foreach ($addressComponents as $component) {
                $types = $component['types'] ?? [];
                
                if (in_array('locality', $types) || in_array('administrative_area_level_2', $types)) {
                    $city = $component['long_name'] ?? $city;
                }
                
                if (in_array('administrative_area_level_1', $types)) {
                    $governate = $component['long_name'] ?? $governate;
                }
            }

            // Get name and description
            $name = $placeData['name'] ?? 'Unknown Garage';
            $description = $this->extractDescription($placeData);

            // Detect language and translate
            $titleData = $this->processText($name);
            $descriptionData = $this->processText($description);

            // Build Google Maps share link
            $mapsShareLink = $this->buildMapsShareLink($placeData);

            // Prepare garage data
            $garageData = [
                'title' => $titleData['en'],
                'title_ar' => $titleData['ar'],
                'description' => $descriptionData['en'],
                'description_ar' => $descriptionData['ar'],
                'service_description' => $this->extractServiceDescription($placeData),
                'contact_no' => $placeData['formatted_phone_number'] ?? $placeData['international_phone_number'] ?? null,
                'address' => $placeData['formatted_address'] ?? $placeData['vicinity'] ?? null,
                'city' => $city,
                'governate' => $governate,
                'area' => $area,
                'block' => $block,
                'latitude' => $location['lat'] ?? null,
                'longitude' => $location['lng'] ?? null,
                'rating' => isset($placeData['rating']) ? (int)round($placeData['rating']) : null,
                'status' => 'approved',
                'registered_by' => 'google_maps',
                'is_google_maps' => true,
                'google_place_id' => $placeData['place_id'] ?? null,
                'maps_share_link' => $mapsShareLink,
                'is_available' => ($placeData['business_status'] ?? 'OPERATIONAL') === 'OPERATIONAL',
                'country_id' => 112, // Kuwait
            ];

            return Garage::create($garageData);

        } catch (\Exception $e) {
            Log::error("Error creating garage from place: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Process text: detect language and translate accordingly
     */
    private function processText(string $text): array
    {
        if (empty(trim($text))) {
            return ['en' => '', 'ar' => ''];
        }

        $isEnglish = $this->isEnglish($text);

        if ($isEnglish) {
            // Text is English, translate to Arabic
            $arabic = $this->translateToArabic($text);
            return [
                'en' => $text,
                'ar' => $arabic ?? $text
            ];
        } else {
            // Text is non-English (likely Arabic), translate to English
            $english = $this->translateToEnglish($text);
            return [
                'en' => $english ?? $text,
                'ar' => $text
            ];
        }
    }

    /**
     * Check if text is English
     */
    private function isEnglish(string $text): bool
    {
        $text = trim($text);
        
        if (empty($text)) {
            return true;
        }

        // Check for Arabic characters
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return false;
        }

        // Check for other non-Latin scripts
        if (preg_match('/[\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $text)) {
            return false;
        }

        // Check if text is ASCII with letters
        if (mb_check_encoding($text, 'ASCII') && preg_match('/[a-zA-Z]/', $text)) {
            return true;
        }

        return false;
    }

    /**
     * Translate text to English using OpenAI
     */
    private function translateToEnglish(string $text): ?string
    {
        try {
            $model = env('OPENAI_MODEL', 'gpt-4o-mini');
            
            if (empty($text)) {
                return null;
            }
            
            $prompt = "Translate the following text to English. Return ONLY the English translation, nothing else.

Text: {$text}

English translation:";

            $apiKey = env('OPENAI_API_KEY');
            if (empty($apiKey)) {
                Log::warning("OpenAI API key not configured for translation");
                return null;
            }

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional translator. Translate the given text to English. Return only the translation, no explanations.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            return !empty($content) ? trim($content, '"\'') : null;

        } catch (\Exception $e) {
            Log::error("OpenAI translation to English error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Translate text to Arabic using OpenAI
     */
    private function translateToArabic(string $text): ?string
    {
        try {
            $model = env('OPENAI_MODEL', 'gpt-4o-mini');
            
            if (empty($text)) {
                return null;
            }
            
            $prompt = "Translate the following text to Arabic. Return ONLY the Arabic translation, nothing else.

Text: {$text}

Arabic translation:";

            $apiKey = env('OPENAI_API_KEY');
            if (empty($apiKey)) {
                Log::warning("OpenAI API key not configured for translation");
                return null;
            }

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional translator. Translate the given text to Arabic. Return only the translation, no explanations.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            return !empty($content) ? trim($content, '"\'') : null;

        } catch (\Exception $e) {
            Log::error("OpenAI translation to Arabic error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract description from place data
     */
    private function extractDescription(array $placeData): string
    {
        // Try to get from reviews (first review text)
        $reviews = $placeData['reviews'] ?? [];
        if (!empty($reviews) && isset($reviews[0]['text'])) {
            $reviewText = $reviews[0]['text'];
            // Limit to 255 characters for string field
            return mb_substr($reviewText, 0, 255);
        }

        // Fallback: combine name with types if available
        $name = $placeData['name'] ?? 'Garage';
        $types = $placeData['types'] ?? [];
        
        // Filter relevant types
        $relevantTypes = array_filter($types, function($type) {
            return !in_array($type, ['establishment', 'point_of_interest', 'store']);
        });
        
        if (!empty($relevantTypes)) {
            $typeStr = implode(', ', array_slice($relevantTypes, 0, 2));
            $description = $name . ' - ' . $typeStr;
            return mb_substr($description, 0, 255);
        }

        // Final fallback to name
        return mb_substr($name, 0, 255);
    }

    /**
     * Extract service description from place data
     */
    private function extractServiceDescription(array $placeData): ?string
    {
        $types = $placeData['types'] ?? [];
        
        // Filter out generic types
        $relevantTypes = array_filter($types, function($type) {
            return !in_array($type, ['establishment', 'point_of_interest', 'store']);
        });
        
        if (!empty($relevantTypes)) {
            return implode(', ', array_slice($relevantTypes, 0, 3));
        }
        
        return null;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // Earth radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Build Google Maps share link
     */
    private function buildMapsShareLink(array $placeData): ?string
    {
        $name = $placeData['name'] ?? null;
        $placeId = $placeData['place_id'] ?? null;
        $address = $placeData['formatted_address'] ?? $placeData['vicinity'] ?? null;
        $geometry = $placeData['geometry'] ?? [];
        $location = $geometry['location'] ?? [];

        // Priority 1: Use name + address
        if ($name && $address) {
            $encodedName = urlencode($name);
            $encodedAddress = urlencode($address);
            return "https://www.google.com/maps/search/?api=1&query={$encodedName},{$encodedAddress}";
        }

        // Priority 2: Use place_id with name
        if ($placeId && $name) {
            $encodedPlaceId = urlencode($placeId);
            $encodedName = urlencode($name);
            return "https://www.google.com/maps/search/?api=1&query={$encodedName}&query_place_id={$encodedPlaceId}";
        }

        // Priority 3: Use place_id
        if ($placeId) {
            $encodedPlaceId = urlencode($placeId);
            return "https://www.google.com/maps/place/?q=place_id:{$encodedPlaceId}";
        }

        // Priority 4: Use coordinates
        if (!empty($location['lat']) && !empty($location['lng'])) {
            return "https://www.google.com/maps/search/?api=1&query={$location['lat']},{$location['lng']}";
        }

        return null;
    }
}

