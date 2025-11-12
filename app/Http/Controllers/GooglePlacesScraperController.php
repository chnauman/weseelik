<?php

namespace App\Http\Controllers;

use App\Services\GooglePlacesScraperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GooglePlacesScraperController extends Controller
{
    /**
     * Show the scraping page
     */
    public function index()
    {
        return view('scraper.index');
    }

    /**
     * Scrape places via AJAX
     */
    public function scrape(Request $request)
    {
        $request->validate([
            'city' => 'required|string',
            'category' => 'required|string',
            'page' => 'nullable|integer|min:1',
            'next_page_token' => 'nullable|string',
        ]);

        $city = $request->input('city');
        $category = $request->input('category');
        $page = $request->input('page', 1);
        $nextPageToken = $request->input('next_page_token');
        $perPage = 5; // Show 5 results per page

        try {
            $scraper = new GooglePlacesScraperService();
            $scraper->setDelay(1);

            // Build search query
            $query = $this->buildSearchQuery($category, $city);

            // Get places from Google Places API (with pagination support)
            $searchResult = $scraper->textSearch($query, 'Kuwait', $nextPageToken);
            $places = $searchResult['results'] ?? [];
            $nextToken = $searchResult['next_page_token'] ?? null;

            if (empty($places)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No places found for your search criteria.',
                    'places' => [],
                    'has_more' => false,
                ]);
            }

            // Get detailed information for each place (preview mode - don't save)
            $results = [];
            $placesToProcess = array_slice($places, 0, $perPage);

            foreach ($placesToProcess as $place) {
                $placeId = $place['place_id'] ?? null;
                
                if ($placeId) {
                    // Add small delay to respect rate limits
                    if (count($results) > 0) {
                        usleep(500000); // 0.5 second delay
                    }
                    
                    // Get full details
                    $placeDetails = $scraper->getPlaceDetails($placeId);
                    
                    if ($placeDetails) {
                        $fullPlaceData = array_merge($place, $placeDetails);
                        $fullPlaceData['place_id'] = $placeId;
                        
                        // Map to display format (preview only, not saved)
                        $results[] = $this->formatPlaceForDisplay($fullPlaceData, $scraper);
                    }
                }
            }

            $hasMore = !empty($nextToken) || count($places) > $perPage;

            return response()->json([
                'success' => true,
                'places' => $results,
                'has_more' => $hasMore,
                'next_page_token' => $nextToken,
                'total_found' => count($places),
                'page' => $page,
            ]);

        } catch (\Exception $e) {
            Log::error('Scraping error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while scraping. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build search query from category and city
     */
    private function buildSearchQuery(string $category, string $city): string
    {
        $categoryMap = [
            'garages' => 'car garage',
            'petrol_pumps' => 'petrol pump gas station',
            'restaurants' => 'restaurant',
            'atms' => 'ATM',
            'hospitals' => 'hospital',
            'schools' => 'school',
            'malls' => 'shopping mall',
            'hotels' => 'hotel',
        ];

        $searchTerm = $categoryMap[strtolower($category)] ?? $category;
        
        if ($city && strtolower($city) !== 'all kuwait') {
            return "{$searchTerm} in {$city}, Kuwait";
        }
        
        return "{$searchTerm} in Kuwait";
    }

    /**
     * Format place data for display
     */
    private function formatPlaceForDisplay(array $placeData, GooglePlacesScraperService $scraper): array
    {
        $geometry = $placeData['geometry'] ?? [];
        $location = $geometry['location'] ?? [];
        $photos = $placeData['photos'] ?? [];
        
        // Get photo URLs (only main photo for card display)
        $photoUrls = [];
        foreach (array_slice($photos, 0, 1) as $photo) {
            $photoReference = $photo['photo_reference'] ?? null;
            if ($photoReference) {
                $photoUrls[] = $scraper->getPlacePhotoUrl($photoReference, 800);
            }
        }

        // Build Google Maps link - prioritize name+address (most reliable), then place_id, then coordinates
        $mapsLink = null;
        
        // Priority 1: Use place name + address (most reliable for opening exact location)
        if (!empty($placeData['name'])) {
            $name = urlencode($placeData['name']);
            $address = '';
            
            // Try formatted_address first, then vicinity
            if (!empty($placeData['formatted_address'])) {
                $address = urlencode($placeData['formatted_address']);
            } elseif (!empty($placeData['vicinity'])) {
                $address = urlencode($placeData['vicinity']);
            }
            
            if ($address) {
                // Use name + address for precise search
                $mapsLink = "https://www.google.com/maps/search/?api=1&query={$name},{$address}";
            } else {
                // Just use name with Kuwait if no address available
                $mapsLink = "https://www.google.com/maps/search/?api=1&query={$name},Kuwait";
            }
        }
        // Priority 2: Use place_id with name (alternative format)
        if (empty($mapsLink) && !empty($placeData['place_id']) && !empty($placeData['name'])) {
            $placeId = urlencode($placeData['place_id']);
            $name = urlencode($placeData['name']);
            // Try format with both query and query_place_id
            $mapsLink = "https://www.google.com/maps/search/?api=1&query={$name}&query_place_id={$placeId}";
        }
        // Priority 2b: Alternative place_id format (direct place URL)
        if (empty($mapsLink) && !empty($placeData['place_id'])) {
            $placeId = urlencode($placeData['place_id']);
            // Direct place format
            $mapsLink = "https://www.google.com/maps/place/?q=place_id:{$placeId}";
        }
        // Priority 3: Fallback to coordinates if nothing else available
        if (empty($mapsLink) && !empty($location['lat']) && !empty($location['lng'])) {
            $mapsLink = "https://www.google.com/maps/search/?api=1&query={$location['lat']},{$location['lng']}";
        }

        // Extract address components
        $addressComponents = $placeData['address_components'] ?? [];
        $city = null;
        $area = null;
        
        foreach ($addressComponents as $component) {
            $types = $component['types'] ?? [];
            if (in_array('locality', $types) || in_array('administrative_area_level_2', $types)) {
                $city = $component['long_name'] ?? $city;
            }
            if (in_array('sublocality', $types) || in_array('sublocality_level_1', $types)) {
                $area = $component['long_name'] ?? $area;
            }
        }

        return [
            'place_id' => $placeData['place_id'] ?? null,
            'name' => $placeData['name'] ?? 'Unknown',
            'address' => $placeData['formatted_address'] ?? $placeData['vicinity'] ?? null,
            'city' => $city,
            'area' => $area,
            'phone' => $placeData['formatted_phone_number'] ?? $placeData['international_phone_number'] ?? null,
            'website' => $placeData['website'] ?? null,
            'rating' => $placeData['rating'] ?? null,
            'user_ratings_total' => $placeData['user_ratings_total'] ?? 0,
            'photos' => $photoUrls,
            'main_photo' => !empty($photoUrls) ? $photoUrls[0] : null,
            'maps_link' => $mapsLink,
            'latitude' => $location['lat'] ?? null,
            'longitude' => $location['lng'] ?? null,
            'types' => $placeData['types'] ?? [],
            'business_status' => $placeData['business_status'] ?? 'OPERATIONAL',
        ];
    }
}

