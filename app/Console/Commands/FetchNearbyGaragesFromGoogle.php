<?php

namespace App\Console\Commands;

use App\Models\Garage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;

class FetchNearbyGaragesFromGoogle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'garage:fetch-nearby-garages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch nearby garages from Google Maps for each garage area and save to database';

    private string $googlePlacesApiKey;
    private int $radius = 500; // 500 meters
    private int $delayBetweenRequests = 2; // seconds

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $verbose = $this->getOutput()->isVerbose();

        $this->info('Starting nearby garages fetch from Google Maps...');

        // Get Google Places API key
        $this->googlePlacesApiKey = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));
        
        if (empty($this->googlePlacesApiKey)) {
            $this->error('Google Places API key is not configured. Please set GOOGLE_PLACES_API_KEY or GOOGLE_MAPS_API_KEY in your .env file.');
            return Command::FAILURE;
        }

        // Get unique areas with their representative coordinates
        $uniqueAreas = Garage::whereNotNull('area')
            ->where('area', '!=', '')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNull('deleted_at')
            ->selectRaw('area, AVG(CAST(latitude AS DECIMAL(10, 8))) as avg_lat, AVG(CAST(longitude AS DECIMAL(10, 8))) as avg_lng')
            ->groupBy('area')
            ->get();

        if ($uniqueAreas->isEmpty()) {
            $this->info('No unique areas found with coordinates.');
            return Command::SUCCESS;
        }

        $this->info("Found {$uniqueAreas->count()} unique area(s) to process.");

        $totalProcessed = 0;
        $totalNewGarages = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        $bar = $this->output->createProgressBar($uniqueAreas->count());
        $bar->start();

        foreach ($uniqueAreas as $areaData) {
            try {
                $area = $areaData->area;
                $lat = (float)$areaData->avg_lat;
                $lng = (float)$areaData->avg_lng;

                if ($verbose) {
                    $this->newLine();
                    $this->line("Processing area: {$area} (Coordinates: {$lat}, {$lng})");
                }

                // Search for nearby places using area's average coordinates
                $nearbyPlaces = $this->searchNearbyPlaces($lat, $lng, $verbose);

                if (empty($nearbyPlaces)) {
                    $totalSkipped++;
                    if ($verbose) {
                        $this->comment("⊘ No nearby places found for area: {$area}");
                    }
                    $bar->advance();
                    continue;
                }

                if ($verbose) {
                    $this->info("Found " . count($nearbyPlaces) . " nearby places in area: {$area}");
                }

                // Process each nearby place
                foreach ($nearbyPlaces as $place) {
                    // Check if garage already exists
                    if ($this->garageExists($place)) {
                        if ($verbose) {
                            $this->comment("⊘ Garage already exists: " . ($place['name'] ?? 'Unknown'));
                        }
                        $totalSkipped++;
                        continue;
                    }

                    // Get place details
                    $placeDetails = $this->getPlaceDetails($place['place_id'] ?? null, $verbose);
                    
                    if (!$placeDetails) {
                        $totalFailed++;
                        continue;
                    }

                    // Merge basic place data with details
                    $fullPlaceData = array_merge($place, $placeDetails);

                    // Create garage record
                    $newGarage = $this->createGarageFromPlace($fullPlaceData, $area, $verbose);

                    if ($newGarage) {
                        $totalNewGarages++;
                        if ($verbose) {
                            $this->info("✓ Created Garage ID {$newGarage->id}: {$newGarage->title}");
                        }
                    } else {
                        $totalFailed++;
                    }

                    // Delay between places
                    sleep($this->delayBetweenRequests);
                }

                $totalProcessed++;

                // Delay between areas
                if ($totalProcessed < $uniqueAreas->count()) {
                    sleep($this->delayBetweenRequests);
                }

            } catch (\Exception $e) {
                $totalFailed++;
                Log::error("Error processing area {$areaData->area}: " . $e->getMessage());
                $this->newLine();
                $this->error("Error processing area {$areaData->area}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Processing completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Total Areas Processed', $totalProcessed],
                ['New Garages Created', $totalNewGarages],
                ['Skipped (Already Exists)', $totalSkipped],
                ['Failed', $totalFailed],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Search for nearby places using Google Places Nearby Search API
     *
     * @param float $lat
     * @param float $lng
     * @param bool $verbose
     * @return array
     */
    private function searchNearbyPlaces(float $lat, float $lng, bool $verbose = false): array
    {
        $allResults = [];
        $nextPageToken = null;
        $maxPages = 3; // Limit to 3 pages (60 results max)

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

                $maxPages--;

            } catch (\Exception $e) {
                Log::error('Google Places Nearby Search exception: ' . $e->getMessage());
                break;
            }

        } while ($nextPageToken && $maxPages > 0);

        return $allResults;
    }

    /**
     * Get detailed place information
     *
     * @param string|null $placeId
     * @param bool $verbose
     * @return array|null
     */
    private function getPlaceDetails(?string $placeId, bool $verbose = false): ?array
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
     *
     * @param array $place
     * @return bool
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
            // Get all garages with coordinates and check distance in PHP
            // This is more reliable across different database systems
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
     *
     * @param array $placeData
     * @param string $area
     * @param bool $verbose
     * @return Garage|null
     */
    private function createGarageFromPlace(array $placeData, string $area, bool $verbose = false): ?Garage
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
            $titleData = $this->processText($name, $verbose);
            $descriptionData = $this->processText($description, $verbose);

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
            ];

            return Garage::create($garageData);

        } catch (\Exception $e) {
            Log::error("Error creating garage from place: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Process text: detect language and translate accordingly
     *
     * @param string $text
     * @param bool $verbose
     * @return array ['en' => string, 'ar' => string]
     */
    private function processText(string $text, bool $verbose = false): array
    {
        if (empty(trim($text))) {
            return ['en' => '', 'ar' => ''];
        }

        $isEnglish = $this->isEnglish($text);

        if ($isEnglish) {
            // Text is English, translate to Arabic
            $arabic = $this->translateToArabic($text, $verbose);
            return [
                'en' => $text,
                'ar' => $arabic ?? $text
            ];
        } else {
            // Text is non-English (likely Arabic), translate to English
            $english = $this->translateToEnglish($text, $verbose);
            return [
                'en' => $english ?? $text,
                'ar' => $text
            ];
        }
    }

    /**
     * Check if text is English
     *
     * @param string $text
     * @return bool
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
     *
     * @param string $text
     * @param bool $verbose
     * @return string|null
     */
    private function translateToEnglish(string $text, bool $verbose = false): ?string
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
     *
     * @param string $text
     * @param bool $verbose
     * @return string|null
     */
    private function translateToArabic(string $text, bool $verbose = false): ?string
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
     *
     * @param array $placeData
     * @return string
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
     *
     * @param array $placeData
     * @return string|null
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
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in meters
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
     *
     * @param array $placeData
     * @return string|null
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

