<?php

namespace App\Services;

use App\Models\GarageScraped;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class GooglePlacesScraperService
{
    private string $apiKey;
    private int $delayBetweenRequests = 1; // seconds
    private int $maxImagesPerPlace = 5;

    public function __construct()
    {
        $this->apiKey = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));
        
        if (empty($this->apiKey)) {
            throw new Exception('Google Places API key is not configured. Please set GOOGLE_PLACES_API_KEY or GOOGLE_MAPS_API_KEY in your .env file.');
        }
    }

    /**
     * Search for places using text search
     *
     * @param string $query Search query (e.g., "car garage in Kuwait")
     * @param string|null $location Location bias - can be location name (e.g., "Kuwait") or coordinates (e.g., "29.3759,47.9774")
     * @param string|null $nextPageToken Token for pagination
     * @return array Returns ['results' => [], 'next_page_token' => string|null]
     */
    public function textSearch(string $query, ?string $location = null, ?string $nextPageToken = null): array
    {
        $url = "https://maps.googleapis.com/maps/api/place/textsearch/json";
        
        $params = [
            'query' => $query,
            'key' => $this->apiKey,
        ];

        // For Text Search, location is typically included in the query itself
        // But we can use region parameter for country code bias
        if ($location) {
            // If location is a country name, add region code
            $locationLower = strtolower($location);
            if ($locationLower === 'kuwait') {
                $params['region'] = 'kw'; // Kuwait country code
            } elseif ($locationLower === 'saudi arabia' || $locationLower === 'saudi') {
                $params['region'] = 'sa';
            } elseif ($locationLower === 'uae' || $locationLower === 'united arab emirates') {
                $params['region'] = 'ae';
            }
            // Note: For Text Search, it's better to include location in the query string itself
            // The region parameter just biases results to that country
        }

        // Add pagination token if provided
        if ($nextPageToken) {
            $params['pagetoken'] = $nextPageToken;
        }

        try {
            $response = Http::timeout(30)->get($url, $params);
            
            if (!$response->successful()) {
                Log::error('Google Places Text Search failed: ' . $response->status());
                return ['results' => [], 'next_page_token' => null];
            }

            $data = $response->json();

            if ($data && isset($data['status']) && $data['status'] === 'OK') {
                return [
                    'results' => $data['results'] ?? [],
                    'next_page_token' => $data['next_page_token'] ?? null,
                ];
            } elseif (isset($data['status']) && $data['status'] === 'ZERO_RESULTS') {
                Log::info('No results found for query: ' . $query);
                return ['results' => [], 'next_page_token' => null];
            } else {
                Log::warning('Google Places Text Search error: ' . ($data['status'] ?? 'UNKNOWN') . ' - ' . ($data['error_message'] ?? ''));
                return ['results' => [], 'next_page_token' => null];
            }
        } catch (Exception $e) {
            Log::error('Google Places Text Search exception: ' . $e->getMessage());
            return ['results' => [], 'next_page_token' => null];
        }
    }

    /**
     * Get detailed place information
     *
     * @param string $placeId Google Place ID
     * @return array|null
     */
    public function getPlaceDetails(string $placeId): ?array
    {
        $url = "https://maps.googleapis.com/maps/api/place/details/json";
        
        // Request all available fields
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
            'photos',
            'reviews',
            'plus_code',
            'address_components',
            'business_status',
            'vicinity',
        ];

        $params = [
            'place_id' => $placeId,
            'fields' => implode(',', $fields),
            'key' => $this->apiKey,
        ];

        try {
            $response = Http::timeout(30)->get($url, $params);
            
            if (!$response->successful()) {
                Log::error('Google Places Details failed: ' . $response->status());
                return null;
            }

            $data = $response->json();

            if ($data && isset($data['status']) && $data['status'] === 'OK' && isset($data['result'])) {
                return $data['result'];
            } else {
                Log::warning('Google Places Details error: ' . ($data['status'] ?? 'UNKNOWN') . ' - ' . ($data['error_message'] ?? ''));
                return null;
            }
        } catch (Exception $e) {
            Log::error('Google Places Details exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get place photo URL
     *
     * @param string $photoReference Photo reference from place details
     * @param int $maxWidth Maximum width (default 800)
     * @return string|null
     */
    public function getPlacePhotoUrl(string $photoReference, int $maxWidth = 800): ?string
    {
        $url = "https://maps.googleapis.com/maps/api/place/photo";
        
        $params = [
            'photo_reference' => $photoReference,
            'maxwidth' => $maxWidth,
            'key' => $this->apiKey,
        ];

        return $url . '?' . http_build_query($params);
    }

    /**
     * Download and store image
     *
     * @param string $photoUrl Photo URL
     * @param GarageScraped $garage Garage model instance
     * @param string $collection Media collection name (logo, storefront_image, gallery)
     * @return bool
     */
    public function downloadAndStoreImage(string $photoUrl, GarageScraped $garage, string $collection = 'gallery'): bool
    {
        try {
            $response = Http::timeout(30)->get($photoUrl);
            
            if (!$response->successful()) {
                Log::warning('Failed to download image: ' . $photoUrl);
                return false;
            }

            $imageContent = $response->body();
            $extension = 'jpg'; // Google Photos are typically JPG
            
            // Generate unique filename
            $filename = 'garage_' . $garage->id . '_' . time() . '_' . uniqid() . '.' . $extension;
            
            // Store using Spatie Media Library
            $garage->addMediaFromString($imageContent)
                ->usingFileName($filename)
                ->usingName($filename)
                ->toMediaCollection($collection);

            return true;
        } catch (Exception $e) {
            Log::error('Error downloading image: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Map Google Places data to GarageScraped model
     *
     * @param array $placeData Google Places API response
     * @return array
     */
    public function mapPlaceDataToGarage(array $placeData): array
    {
        $geometry = $placeData['geometry'] ?? [];
        $location = $geometry['location'] ?? [];
        $addressComponents = $placeData['address_components'] ?? [];
        
        // Extract address components
        $city = null;
        $governate = null;
        $area = null;
        $block = null;
        $countryId = null;

        foreach ($addressComponents as $component) {
            $types = $component['types'] ?? [];
            
            if (in_array('locality', $types) || in_array('administrative_area_level_2', $types)) {
                $city = $component['long_name'] ?? $city;
            }
            
            if (in_array('administrative_area_level_1', $types)) {
                $governate = $component['long_name'] ?? $governate;
            }
            
            if (in_array('sublocality', $types) || in_array('sublocality_level_1', $types)) {
                $area = $component['long_name'] ?? $area;
            }
            
            if (in_array('country', $types)) {
                // You might want to map country code to country_id
                // For now, we'll leave it null or you can implement country lookup
            }
        }

        // Build Google Maps share link
        $mapsShareLink = null;
        if (!empty($location['lat']) && !empty($location['lng'])) {
            $mapsShareLink = "https://www.google.com/maps/search/?api=1&query={$location['lat']},{$location['lng']}";
        } elseif (!empty($placeData['place_id'])) {
            $mapsShareLink = "https://www.google.com/maps/place/?q=place_id:" . $placeData['place_id'];
        }

        return [
            'google_place_id' => $placeData['place_id'] ?? null,
            'title' => $placeData['name'] ?? 'Unknown',
            'description' => $placeData['name'] ?? 'Garage', // Use name as description if no description available
            'service_description' => $this->extractServiceDescription($placeData),
            'contact_no' => $placeData['formatted_phone_number'] ?? $placeData['international_phone_number'] ?? null,
            'address' => $placeData['formatted_address'] ?? $placeData['vicinity'] ?? null,
            'city' => $city,
            'governate' => $governate,
            'area' => $area,
            'block' => $block,
            'country_id' => $countryId,
            'latitude' => $location['lat'] ?? null,
            'longitude' => $location['lng'] ?? null,
            'rating' => isset($placeData['rating']) ? (int)round($placeData['rating']) : null, // Google rating is 1-5, we store as integer
            'maps_share_link' => $mapsShareLink,
            'status' => 'pending',
            'registered_by' => 'scraper',
            'scraped_at' => now(),
            'scraping_status' => 'completed',
            'is_available' => ($placeData['business_status'] ?? 'OPERATIONAL') === 'OPERATIONAL',
        ];
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
        
        // Filter out generic types and format
        $relevantTypes = array_filter($types, function($type) {
            return !in_array($type, ['establishment', 'point_of_interest', 'store']);
        });
        
        if (!empty($relevantTypes)) {
            return implode(', ', array_slice($relevantTypes, 0, 3));
        }
        
        return null;
    }

    /**
     * Scrape and save a place
     *
     * @param array $place Basic place data from search
     * @param bool $downloadImages Whether to download images
     * @return GarageScraped|null
     */
    public function scrapeAndSavePlace(array $place, bool $downloadImages = true): ?GarageScraped
    {
        $placeId = $place['place_id'] ?? null;
        
        if (!$placeId) {
            Log::warning('Place ID not found in place data');
            return null;
        }

        // Check if already scraped
        $existing = GarageScraped::where('google_place_id', $placeId)->first();
        if ($existing) {
            Log::info("Place already scraped: {$place['name']} (ID: {$placeId})");
            return $existing;
        }

        // Add delay to respect rate limits
        sleep($this->delayBetweenRequests);

        // Get detailed place information
        $placeDetails = $this->getPlaceDetails($placeId);
        
        if (!$placeDetails) {
            Log::warning("Failed to get details for place: {$place['name']}");
            return null;
        }

        // Merge basic place data with details
        $fullPlaceData = array_merge($place, $placeDetails);
        $fullPlaceData['place_id'] = $placeId; // Ensure place_id is set

        // Map data to garage format
        $garageData = $this->mapPlaceDataToGarage($fullPlaceData);

        try {
            // Create garage record
            $garage = GarageScraped::create($garageData);

            // Download images if requested
            if ($downloadImages && !empty($fullPlaceData['photos'])) {
                $photos = array_slice($fullPlaceData['photos'], 0, $this->maxImagesPerPlace);
                
                foreach ($photos as $index => $photo) {
                    $photoReference = $photo['photo_reference'] ?? null;
                    
                    if ($photoReference) {
                        // Add delay between image downloads
                        if ($index > 0) {
                            sleep(1);
                        }
                        
                        $photoUrl = $this->getPlacePhotoUrl($photoReference);
                        
                        if ($photoUrl) {
                            // First photo as storefront, rest as gallery
                            $collection = $index === 0 ? 'storefront_image' : 'gallery';
                            $this->downloadAndStoreImage($photoUrl, $garage, $collection);
                        }
                    }
                }
            }

            Log::info("Successfully scraped and saved: {$garage->title} (ID: {$garage->id})");
            return $garage;

        } catch (Exception $e) {
            Log::error("Error saving scraped garage: " . $e->getMessage());
            
            // Try to save error information
            try {
                GarageScraped::create(array_merge($garageData, [
                    'scraping_status' => 'failed',
                    'scraping_errors' => $e->getMessage(),
                ]));
            } catch (Exception $saveError) {
                Log::error("Failed to save error record: " . $saveError->getMessage());
            }
            
            return null;
        }
    }

    /**
     * Scrape multiple places from search results
     *
     * @param string $query Search query
     * @param string|null $location Location bias
     * @param bool $downloadImages Whether to download images
     * @param int|null $limit Maximum number of places to scrape
     * @return array Array of scraped GarageScraped models
     */
    public function scrapePlaces(string $query, ?string $location = null, bool $downloadImages = true, ?int $limit = null): array
    {
        $results = [];
        
        // Perform text search
        $searchResult = $this->textSearch($query, $location);
        $places = $searchResult['results'] ?? [];
        
        if (empty($places)) {
            Log::info("No places found for query: {$query}");
            return $results;
        }

        // Limit results if specified
        if ($limit && count($places) > $limit) {
            $places = array_slice($places, 0, $limit);
        }

        Log::info("Found " . count($places) . " places. Starting scraping...");

        foreach ($places as $index => $place) {
            Log::info("Scraping place " . ($index + 1) . "/" . count($places) . ": " . ($place['name'] ?? 'Unknown'));
            
            $garage = $this->scrapeAndSavePlace($place, $downloadImages);
            
            if ($garage) {
                $results[] = $garage;
            }
            
            // Add delay between places to respect rate limits
            if ($index < count($places) - 1) {
                sleep($this->delayBetweenRequests);
            }
        }

        Log::info("Scraping completed. Successfully scraped " . count($results) . " places.");
        
        return $results;
    }

    /**
     * Set delay between requests
     *
     * @param int $seconds
     * @return self
     */
    public function setDelay(int $seconds): self
    {
        $this->delayBetweenRequests = $seconds;
        return $this;
    }

    /**
     * Set maximum images per place
     *
     * @param int $maxImages
     * @return self
     */
    public function setMaxImages(int $maxImages): self
    {
        $this->maxImagesPerPlace = $maxImages;
        return $this;
    }
}

