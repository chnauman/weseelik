<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Garage;

class GarageMatchController extends Controller
{
    /**
     * Search for nearby places within specified radius
     *
     * @param float $lat
     * @param float $lng
     * @param int $radius
     * @param string $apiKey
     * @return array
     */
    private function searchNearbyPlaces(float $lat, float $lng, int $radius, string $apiKey)
    {
        $allResults = [];
        
        // Search for all types of establishments
        // Using 'establishment' type to get all types of places
        $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?" .
               "location={$lat},{$lng}&" .
               "radius={$radius}&" .
               "type=establishment&" .
               "key={$apiKey}";
        
        try {
            $response = Http::timeout(10)->get($url);
            
            if (!$response->successful()) {
                Log::warning('Nearby places search failed: ' . $response->status());
                return [];
            }
            
            $data = $response->json();
            
            if ($data && isset($data['status']) && $data['status'] === 'OK' && !empty($data['results'])) {
                // Get place details for each result to get more information
                foreach ($data['results'] as $place) {
                    $placeId = $place['place_id'] ?? '';
                    if ($placeId) {
                        $details = $this->getPlaceDetails($placeId, $apiKey);
                        if ($details) {
                            $allResults[] = array_merge($place, ['details' => $details]);
                        } else {
                            $allResults[] = $place;
                        }
                    } else {
                        $allResults[] = $place;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Nearby places search error: ' . $e->getMessage());
            return [];
        }
        
        return $allResults;
    }

    /**
     * Get detailed place information
     *
     * @param string $placeId
     * @param string $apiKey
     * @return array|null
     */
    private function getPlaceDetails(string $placeId, string $apiKey)
    {
        try {
            $url = "https://maps.googleapis.com/maps/api/place/details/json?" .
                   "place_id={$placeId}&" .
                   "fields=name,formatted_address,formatted_phone_number,international_phone_number,types,rating,user_ratings_total&" .
                   "key={$apiKey}";
            
            $response = Http::timeout(10)->get($url);
            
            if (!$response->successful()) {
                return null;
            }
            
            $data = $response->json();
            
            if ($data && isset($data['status']) && $data['status'] === 'OK' && isset($data['result'])) {
                return $data['result'];
            }
        } catch (\Exception $e) {
            Log::warning('Place details error: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Match garage title with nearby places using OpenAI
     *
     * @param string $garageTitle
     * @param array $places
     * @return array
     */
    private function matchGarageWithPlaces(string $garageTitle, array $places)
    {
        $model = env('OPENAI_MODEL', 'gpt-4o-mini');
        $temperature = env('OPENAI_TEMPERATURE', 0.7);

        // Prepare places data for OpenAI
        $placesData = [];
        foreach ($places as $index => $place) {
            $name = $place['name'] ?? 'Unknown';
            $address = $place['vicinity'] ?? ($place['formatted_address'] ?? '');
            $types = $place['types'] ?? [];
            $placeType = !empty($types) ? implode(', ', array_slice($types, 0, 3)) : 'establishment';
            
            // Get additional details if available
            $details = $place['details'] ?? null;
            if ($details) {
                $name = $details['name'] ?? $name;
                $address = $details['formatted_address'] ?? $address;
            }
            
            $placesData[] = [
                'index' => $index,
                'name' => $name,
                'address' => $address,
                'type' => $placeType,
            ];
        }

        // Create prompt for OpenAI
        $placesJson = json_encode($placesData, JSON_PRETTY_PRINT);
        
        $prompt = "You are a matching assistant. Compare the garage title with each nearby place and determine the match percentage.

Garage Title: \"{$garageTitle}\"

Nearby Places:
{$placesJson}

For each place, analyze if the garage title matches the place name. Consider:
- Exact name matches
- Similar names (variations, abbreviations, translations)
- Partial matches
- Business type relevance

Return a JSON object with a 'matches' array. Each match should have:
- 'index': the index from the places array
- 'match_percentage': a number from 0-100 indicating how much the garage title matches the place name
- 'reason': a brief explanation of why this match percentage was assigned

Example format:
{
  \"matches\": [
    {
      \"index\": 0,
      \"match_percentage\": 55,
      \"reason\": \"Exact name match\"
    },
    {
      \"index\": 1,
      \"match_percentage\": 55,
      \"reason\": \"Partial match, different business type\"
    }
  ]
}

Only return valid JSON. Do not include any markdown formatting or additional text.";

        try {
            $response = OpenAI::chat()->create([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant that analyzes name matches and returns JSON responses. Always respond with valid JSON only.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3, // Lower temperature for more consistent matching
            'response_format' => ['type' => 'json_object'],
        ]);

            $content = $response->choices[0]->message->content ?? '';
            
            // Parse JSON response
            $data = json_decode($content, true);
            
            if (!is_array($data) || !isset($data['matches'])) {
                Log::warning('Invalid OpenAI response format for garage matching');
                return [];
            }

            // Map match percentages to places
            $matches = [];
            foreach ($data['matches'] as $match) {
                $index = $match['index'] ?? null;
                $matchPercentage = $match['match_percentage'] ?? 0;
                $reason = $match['reason'] ?? '';

                if ($index !== null && isset($places[$index])) {
                    $place = $places[$index];
                    $name = $place['name'] ?? 'Unknown';
                    $address = $place['vicinity'] ?? ($place['formatted_address'] ?? '');
                    $rating = $place['rating'] ?? null;
                    $types = $place['types'] ?? [];
                    $placeType = !empty($types) ? implode(', ', array_slice($types, 0, 3)) : 'establishment';
                    
                    // Get additional details if available
                    $details = $place['details'] ?? null;
                    if ($details) {
                        $name = $details['name'] ?? $name;
                        $address = $details['formatted_address'] ?? $address;
                        $phone = $details['formatted_phone_number'] ?? $details['international_phone_number'] ?? null;
                    } else {
                        $phone = null;
                    }

                    // Generate Google Maps URL - prioritize name+address (most reliable), then place_id, then coordinates
                    $lat = $place['geometry']['location']['lat'] ?? null;
                    $lng = $place['geometry']['location']['lng'] ?? null;
                    $placeId = $place['place_id'] ?? null;
                    $googleMapsUrl = null;
                    
                    // Priority 1: Use place name + address (most reliable for opening exact location)
                    if (!empty($name)) {
                        $encodedName = urlencode($name);
                        $encodedAddress = '';
                        
                        // Try formatted_address first, then vicinity
                        if (!empty($address)) {
                            $encodedAddress = urlencode($address);
                        }
                        
                        if ($encodedAddress) {
                            // Use name + address for precise search
                            $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$encodedName},{$encodedAddress}";
                        } else {
                            // Just use name with Kuwait if no address available
                            $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$encodedName},Kuwait";
                        }
                    }
                    // Priority 2: Use place_id with name (alternative format)
                    if (empty($googleMapsUrl) && !empty($placeId) && !empty($name)) {
                        $encodedPlaceId = urlencode($placeId);
                        $encodedName = urlencode($name);
                        // Try format with both query and query_place_id
                        $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$encodedName}&query_place_id={$encodedPlaceId}";
                    }
                    // Priority 2b: Alternative place_id format (direct place URL)
                    if (empty($googleMapsUrl) && !empty($placeId)) {
                        $encodedPlaceId = urlencode($placeId);
                        // Direct place format
                        $googleMapsUrl = "https://www.google.com/maps/place/?q=place_id:{$encodedPlaceId}";
                    }
                    // Priority 3: Fallback to coordinates if nothing else available
                    if (empty($googleMapsUrl) && !empty($lat) && !empty($lng)) {
                        $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
                    }

                    $matches[] = [
                        'name' => $name,
                        'address' => $address,
                        'phone' => $phone,
                        'type' => $placeType,
                        'rating' => $rating,
                        'match_percentage' => (int)$matchPercentage,
                        'match_reason' => $reason,
                        'google_maps_url' => $googleMapsUrl,
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ];
                }
            }

            return $matches;

        } catch (\Exception $e) {
            Log::error('OpenAI matching error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Match garage title with Google Maps place name from URL
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function matchGarageWithUrl($id)
    {
        // Get garage by ID
        $garage = Garage::find($id);

        if (!$garage) {
            return response()->json([
                'status' => 'error',
                'message' => 'Garage not found.',
            ], 404);
        }

        // Check if garage has maps_share_link
        $mapsUrl = $garage->maps_share_link;

        if (empty($mapsUrl)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Garage does not have a Google Maps share link.',
                'garage' => [
                    'id' => $garage->id,
                    'title' => $garage->title,
                ],
            ], 400);
        }

        // Get garage title
        $garageTitle = $garage->title;

        if (empty($garageTitle)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Garage does not have a title.',
                'garage' => [
                    'id' => $garage->id,
                ],
            ], 400);
        }

        // Resolve shortened URLs first
        $resolvedUrl = $this->resolveShortUrl($mapsUrl);
        if ($resolvedUrl && $resolvedUrl !== $mapsUrl) {
            $mapsUrl = $resolvedUrl;
        }

        // Extract place name and coordinates from Google Maps URL
        $placeInfo = $this->extractPlaceNameAndCoordinatesFromUrl($mapsUrl);
        $googlePlaceName = $placeInfo['name'] ?? null;
        $placeCoordinates = $placeInfo['coordinates'] ?? null;

        $matchResult = null;
        $shouldSearchNearby = false;

        // If we have a place name, try to match it first
        if (!empty($googlePlaceName)) {
            $matchResult = $this->matchNames($garageTitle, $googlePlaceName);
            // If match result is null or match percentage is less than 55%, search nearby places
            if ($matchResult === null || !isset($matchResult['match_percentage']) || $matchResult['match_percentage'] < 55) {
                $shouldSearchNearby = true;
            }
        } else {
            // No place name found, search nearby places
            $shouldSearchNearby = true;
        }

        $nearbyPlacesMatches = [];

        // If we need to search nearby places, use coordinates from place info or extract from URL
        if ($shouldSearchNearby) {
            // First try coordinates from place info (from Google Places API)
            $coordinates = $placeCoordinates;
            
            // If we don't have coordinates from place info, try to extract from URL
            if (!$coordinates || !isset($coordinates['lat']) || !isset($coordinates['lng'])) {
                $coordinates = $this->extractCoordinatesFromUrl($mapsUrl);
            }
            
            if ($coordinates && isset($coordinates['lat']) && isset($coordinates['lng'])) {
                $placesApiKey = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));
                
                if ($placesApiKey) {
                    // Search for nearby places within 500 meters
                    $nearbyPlaces = $this->searchNearbyPlaces(
                        (float)$coordinates['lat'], 
                        (float)$coordinates['lng'], 
                        500, 
                        $placesApiKey
                    );

                    if (!empty($nearbyPlaces)) {
                        // Match garage title with each nearby place (use original title, matchGarageWithPlaces handles translation)
                        $nearbyPlacesMatches = $this->matchGarageWithPlaces($garageTitle, $nearbyPlaces);
                        
                        // Filter to only include matches with match_percentage > 0
                        $nearbyPlacesMatches = array_filter($nearbyPlacesMatches, function ($match) {
                            return isset($match['match_percentage']) && $match['match_percentage'] > 0;
                        });
                        
                        // Re-index array after filtering
                        $nearbyPlacesMatches = array_values($nearbyPlacesMatches);
                        
                        // Sort by match percentage (highest first)
                        usort($nearbyPlacesMatches, function ($a, $b) {
                            return ($b['match_percentage'] ?? 0) <=> ($a['match_percentage'] ?? 0);
                        });
                    }
                } else {
                    Log::warning('Google Places API key not configured for nearby places search');
                }
            } else {
                Log::warning('Could not extract coordinates from URL: ' . $mapsUrl);
            }
        }

        // Calculate highest confidence from direct match and nearby places
        $directMatchPercentage = $matchResult['match_percentage'] ?? null;
        $highestNearbyMatchPercentage = null;
        
        if (!empty($nearbyPlacesMatches)) {
            // Get the highest match percentage from nearby places (already sorted, so first one is highest)
            $highestNearbyMatchPercentage = $nearbyPlacesMatches[0]['match_percentage'] ?? null;
        }
        
        // Determine the highest confidence (use whichever is higher between direct match and nearby places)
        $finalConfidence = 0; // Default to 0 if no matches found
        if ($directMatchPercentage !== null && $highestNearbyMatchPercentage !== null) {
            $finalConfidence = max($directMatchPercentage, $highestNearbyMatchPercentage);
        } elseif ($directMatchPercentage !== null) {
            $finalConfidence = $directMatchPercentage;
        } elseif ($highestNearbyMatchPercentage !== null) {
            $finalConfidence = $highestNearbyMatchPercentage;
        }
        
        // Ensure finalConfidence is always an integer
        $finalConfidence = (int)$finalConfidence;
        
        // Determine location_verified status based on confidence
        $locationVerified = $this->determineLocationVerified($finalConfidence);
        
        // Update garage record if confidence is null (only update if not already set)
        $updatedConfidence = null;
        $updatedLocationVerified = null;
        
        if ($garage->confidence === null) {
            try {
                $garage->confidence = $finalConfidence;
                $garage->location_verified = $locationVerified;
                $garage->save();
                
                $updatedConfidence = $finalConfidence;
                $updatedLocationVerified = $locationVerified;
            } catch (\Exception $e) {
                Log::error('Failed to update garage confidence and location_verified: ' . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to update garage location verification status.',
                    'error' => $e->getMessage(),
                ], 500);
            }
        } else {
            // Use existing values if already set
            $updatedConfidence = $garage->confidence;
            $updatedLocationVerified = $garage->location_verified;
        }

        // Prepare response with consistent structure
        $response = [
            'status' => 'success',
            'match_percentage' => $finalConfidence, // Always at root level (highest match)
            'garage' => [
                'id' => $garage->id,
                'title' => $garageTitle,
                'maps_url' => $mapsUrl,
                'confidence' => $updatedConfidence,
                'location_verified' => $updatedLocationVerified,
            ],
        ];

        // Always include direct_match if available
        if ($matchResult && !empty($googlePlaceName)) {
            $response['direct_match'] = [
                'name' => $googlePlaceName,
                'match_percentage' => $matchResult['match_percentage'],
                'match_reason' => $matchResult['reason'],
            ];
        }

        // Always include nearby_places if available
        if (!empty($nearbyPlacesMatches)) {
            $response['nearby_places'] = $nearbyPlacesMatches;
            $response['total_nearby_places'] = count($nearbyPlacesMatches);
        }

        // Set appropriate message based on results
        if (!empty($nearbyPlacesMatches) && $matchResult && !empty($googlePlaceName)) {
            $response['message'] = 'Found ' . count($nearbyPlacesMatches) . ' nearby places with match percentages. Direct match also available.';
        } elseif (!empty($nearbyPlacesMatches)) {
            $response['message'] = 'Found ' . count($nearbyPlacesMatches) . ' nearby places with match percentages.';
        } elseif ($matchResult && !empty($googlePlaceName)) {
            $response['message'] = 'Direct match found with Google Maps place name.';
        } else {
            $response['message'] = 'Could not find matching places. Coordinates may not be available in the URL.';
        }

        return response()->json($response);
    }

    /**
     * Extract place name and coordinates from Google Maps URL
     *
     * @param string $url
     * @return array Returns ['name' => string|null, 'coordinates' => ['lat' => float, 'lng' => float]|null]
     */
    private function extractPlaceNameAndCoordinatesFromUrl(string $url)
    {
        // Resolve shortened URLs (goo.gl, maps.app.goo.gl, etc.)
        $resolvedUrl = $this->resolveShortUrl($url);
        if ($resolvedUrl && $resolvedUrl !== $url) {
            $url = $resolvedUrl;
            Log::info('Resolved shortened URL to: ' . $url);
        }

        // First, try to extract place_id or coordinates from URL
        $placeId = null;
        $coordinates = null;

        // Try to extract place_id from URL patterns
        if (preg_match('/place_id=([^&]+)/', $url, $matches)) {
            $placeId = urldecode($matches[1]);
        } elseif (preg_match('/[?&]q=place_id:([^&]+)/', $url, $matches)) {
            $placeId = urldecode($matches[1]);
        } elseif (preg_match('/place\/([^\/\?&]+)/', $url, $matches)) {
            // Try to extract from URL path (Google Maps place URLs)
            $potentialPlaceId = urldecode($matches[1]);
            // Place IDs are typically long alphanumeric strings
            if (strlen($potentialPlaceId) > 20 && preg_match('/^[A-Za-z0-9_-]+$/', $potentialPlaceId)) {
                $placeId = $potentialPlaceId;
            }
        } elseif (preg_match('/maps\/place\/([^\/\?&]+)/', $url, $matches)) {
            // Another pattern for place URLs
            $potentialPlaceId = urldecode($matches[1]);
            if (strlen($potentialPlaceId) > 20 && preg_match('/^[A-Za-z0-9_-]+$/', $potentialPlaceId)) {
                $placeId = $potentialPlaceId;
            }
        }

        // Try to extract coordinates from various Google Maps URL formats
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            $coordinates = $matches[1] . ',' . $matches[2];
        } elseif (preg_match('/(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            $coordinates = $matches[1] . ',' . $matches[2];
        }

        // If we have place_id, use Google Places API to get both name and coordinates
        if ($placeId) {
            $placesApiKey = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));
            if ($placesApiKey) {
                $placeDetails = $this->getPlaceDetailsWithGeometry($placeId, $placesApiKey);
                if ($placeDetails) {
                    $result = ['name' => null, 'coordinates' => null];
                    
                    if (isset($placeDetails['name'])) {
                        $result['name'] = $placeDetails['name'];
                    }
                    
                    if (isset($placeDetails['geometry']['location'])) {
                        $result['coordinates'] = [
                            'lat' => (float)$placeDetails['geometry']['location']['lat'],
                            'lng' => (float)$placeDetails['geometry']['location']['lng'],
                        ];
                    }
                    
                    // Return if we have at least the name
                    if ($result['name']) {
                        return $result;
                    }
                }
            }
        }

        // If we have coordinates, try to find nearby place and get coordinates
        if ($coordinates) {
            $coordParts = explode(',', $coordinates);
            if (count($coordParts) === 2) {
                $lat = trim($coordParts[0]);
                $lng = trim($coordParts[1]);
                $placesApiKey = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));
                if ($placesApiKey) {
                    // Use nearby search to get place name
                    $placeName = $this->getPlaceNameFromCoordinates($lat, $lng, $placesApiKey);
                    if ($placeName) {
                        return [
                            'name' => $placeName,
                            'coordinates' => [
                                'lat' => (float)$lat,
                                'lng' => (float)$lng,
                            ],
                        ];
                    }
                }
            }
        }

        // Try to extract place name directly from URL path (Google Maps URLs often have the name in the path)
        if (preg_match('/\/place\/([^\/@\?]+)/', $url, $matches)) {
            $placeNameFromPath = urldecode($matches[1]);
            // Clean up the name (remove + signs, decode URL encoding)
            $placeNameFromPath = str_replace('+', ' ', $placeNameFromPath);
            $placeNameFromPath = trim($placeNameFromPath);
            
            // If it looks like a valid place name (not a place_id), use it
            if (strlen($placeNameFromPath) > 3 && 
                !preg_match('/^[A-Za-z0-9_-]{20,}$/', $placeNameFromPath) && // Not a place_id
                !preg_match('/^-?\d+\.\d+,-?\d+\.\d+$/', $placeNameFromPath)) { // Not coordinates
                // Try to get coordinates from URL if available
                $coords = null;
                if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $coordMatches)) {
                    $coords = [
                        'lat' => (float)$coordMatches[1],
                        'lng' => (float)$coordMatches[2],
                    ];
                }
                
                // If coordinates are still null, try geocoding the place name
                if ($coords === null) {
                    $geocodingApiKey = env('GOOGLE_MAPS_API_KEY');
                    if ($geocodingApiKey) {
                        $geocodeResult = $this->geocodeLocation($placeNameFromPath, $geocodingApiKey);
                        if ($geocodeResult && isset($geocodeResult['lat']) && isset($geocodeResult['lng'])) {
                            $coords = [
                                'lat' => (float)$geocodeResult['lat'],
                                'lng' => (float)$geocodeResult['lng'],
                            ];
                        }
                    }
                }
                
                return [
                    'name' => $placeNameFromPath,
                    'coordinates' => $coords,
                ];
            }
        }

        // Fallback: Use OpenAI to extract place name from URL
        $model = env('OPENAI_MODEL', 'gpt-4o-mini');

        try {
            $prompt = "Extract the place name or business name from this Google Maps URL. If the URL contains a place name in the path or parameters, return only the name. If you cannot determine the name from the URL, return 'UNKNOWN'.

URL: {$url}

Return only the place name, nothing else. If you cannot extract it, return 'UNKNOWN'.";

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that extracts place names from URLs. Return only the place name or UNKNOWN if not found.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            
            if (!empty($content) && strtoupper($content) !== 'UNKNOWN') {
                // Try to get coordinates from URL if available
                $coords = null;
                if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $coordMatches)) {
                    $coords = [
                        'lat' => (float)$coordMatches[1],
                        'lng' => (float)$coordMatches[2],
                    ];
                }
                
                // If coordinates are still null, try geocoding the place name
                if ($coords === null) {
                    $geocodingApiKey = env('GOOGLE_MAPS_API_KEY');
                    if ($geocodingApiKey) {
                        $geocodeResult = $this->geocodeLocation($content, $geocodingApiKey);
                        if ($geocodeResult && isset($geocodeResult['lat']) && isset($geocodeResult['lng'])) {
                            $coords = [
                                'lat' => (float)$geocodeResult['lat'],
                                'lng' => (float)$geocodeResult['lng'],
                            ];
                        }
                    }
                }
                
                return [
                    'name' => $content,
                    'coordinates' => $coords,
                ];
            }
        } catch (\Exception $e) {
            Log::error('OpenAI place name extraction error: ' . $e->getMessage());
        }

        return ['name' => null, 'coordinates' => null];
    }

    /**
     * Resolve shortened URL to full URL
     *
     * @param string $url
     * @return string
     */
    private function resolveShortUrl(string $url)
    {
        try {
            // Check if it's a shortened URL
            if (!preg_match('/(goo\.gl|maps\.app\.goo\.gl|bit\.ly|tinyurl)/i', $url)) {
                return $url; // Not a shortened URL
            }

            // First, try to get the Location header without following redirects
            $response = Http::timeout(10)
                ->withoutRedirecting()
                ->get($url);

            // Get redirect location from headers
            if ($response->status() >= 300 && $response->status() < 400) {
                $location = $response->header('Location');
                if ($location) {
                    // Handle relative URLs
                    if (strpos($location, 'http') !== 0) {
                        $parsedUrl = parse_url($url);
                        $scheme = $parsedUrl['scheme'] ?? 'https';
                        $host = $parsedUrl['host'] ?? '';
                        $location = $scheme . '://' . $host . $location;
                    }
                    // Recursively resolve if the resolved URL is still shortened
                    if (preg_match('/(goo\.gl|maps\.app\.goo\.gl|bit\.ly|tinyurl)/i', $location)) {
                        return $this->resolveShortUrl($location);
                    }
                    return $location;
                }
            }

            // If that didn't work, try with a HEAD request (lighter)
            try {
                $headResponse = Http::timeout(10)
                    ->withoutRedirecting()
                    ->head($url);
                
                if ($headResponse->status() >= 300 && $headResponse->status() < 400) {
                    $location = $headResponse->header('Location');
                    if ($location) {
                        if (strpos($location, 'http') !== 0) {
                            $parsedUrl = parse_url($url);
                            $scheme = $parsedUrl['scheme'] ?? 'https';
                            $host = $parsedUrl['host'] ?? '';
                            $location = $scheme . '://' . $host . $location;
                        }
                        if (preg_match('/(goo\.gl|maps\.app\.goo\.gl|bit\.ly|tinyurl)/i', $location)) {
                            return $this->resolveShortUrl($location);
                        }
                        return $location;
                    }
                }
            } catch (\Exception $e) {
                // Ignore HEAD request errors, continue with other methods
            }

        } catch (\Exception $e) {
            Log::warning('Error resolving shortened URL: ' . $e->getMessage());
        }

        return $url; // Return original if resolution fails
    }

    /**
     * Get place name from coordinates using Google Places API
     *
     * @param float $lat
     * @param float $lng
     * @param string $apiKey
     * @return string|null
     */
    private function getPlaceNameFromCoordinates($lat, $lng, $apiKey)
    {
        try {
            // Use nearby search with very small radius to find the exact place
            $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?" .
                   "location={$lat},{$lng}&" .
                   "radius=10&" .
                   "key={$apiKey}";
            
            $response = Http::timeout(10)->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data && isset($data['status']) && $data['status'] === 'OK' && !empty($data['results'])) {
                    // Get the first result (closest match)
                    $place = $data['results'][0];
                    return $place['name'] ?? null;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Get place name from coordinates error: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Translate text to Arabic using OpenAI
     *
     * @param string $text
     * @return string|null
     */
    private function translateToArabic(string $text)
    {
        $model = env('OPENAI_MODEL', 'gpt-4o-mini');

        try {
            $prompt = "Translate the following text to Arabic. Return only the Arabic translation, nothing else.

Text: {$text}

Arabic translation:";

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional translator. Translate the given text to Arabic and return only the translation.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            
            if (!empty($content)) {
                return $content;
            }
        } catch (\Exception $e) {
            Log::error('OpenAI translation error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Match two names and return match percentage
     *
     * @param string $name1
     * @param string $name2
     * @return array
     */
    private function matchNames(string $name1, string $name2)
    {
        $model = env('OPENAI_MODEL', 'gpt-4o-mini');

        try {
            $prompt = "Compare these two place names and determine how similar they are. Consider:
- Exact matches
- Similar spellings (including Arabic, transliterations, or any language)
- Partial matches
- Same meaning but different wording
- Common variations or abbreviations

Name 1: {$name1}
Name 2: {$name2}

Return a JSON object with:
- 'match_percentage': a number from 0-100 indicating how much the names match
- 'reason': a brief explanation in English of why this match percentage was assigned

Example format:
{
  \"match_percentage\": 85,
  \"reason\": \"Names are very similar with minor spelling variations\"
}

Only return valid JSON. Do not include any markdown formatting or additional text.";

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that compares names and returns JSON responses. Always respond with valid JSON only.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content ?? '';
            
            // Parse JSON response
            $data = json_decode($content, true);
            
            if (is_array($data) && isset($data['match_percentage'])) {
                return [
                    'match_percentage' => (int)$data['match_percentage'],
                    'reason' => $data['reason'] ?? 'No reason provided',
                ];
            }
        } catch (\Exception $e) {
            Log::error('OpenAI name matching error: ' . $e->getMessage());
        }

        // Default fallback
        return [
            'match_percentage' => 0,
            'reason' => 'Error occurred during matching',
        ];
    }

    /**
     * Extract coordinates (latitude, longitude) from Google Maps URL
     *
     * @param string $url
     * @return array|null Returns ['lat' => float, 'lng' => float] or null
     */
    private function extractCoordinatesFromUrl(string $url)
    {
        // Try to extract coordinates from various Google Maps URL formats
        // Pattern 1: @lat,lng (most common)
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            $lat = (float)$matches[1];
            $lng = (float)$matches[2];
            return ['lat' => $lat, 'lng' => $lng];
        }

        // Pattern 2: /@lat,lng,zoom
        if (preg_match('/\/@(-?\d+\.\d+),(-?\d+\.\d+),(\d+[z]?)/', $url, $matches)) {
            $lat = (float)$matches[1];
            $lng = (float)$matches[2];
            return ['lat' => $lat, 'lng' => $lng];
        }

        // Pattern 3: query parameter with coordinates
        if (preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            $lat = (float)$matches[1];
            $lng = (float)$matches[2];
            return ['lat' => $lat, 'lng' => $lng];
        }

        // Pattern 4: ll=lat,lng
        if (preg_match('/[?&]ll=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            $lat = (float)$matches[1];
            $lng = (float)$matches[2];
            return ['lat' => $lat, 'lng' => $lng];
        }

        // Pattern 5: center=lat,lng
        if (preg_match('/[?&]center=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            $lat = (float)$matches[1];
            $lng = (float)$matches[2];
            return ['lat' => $lat, 'lng' => $lng];
        }

        // Pattern 6: Just lat,lng in the URL (fallback)
        if (preg_match('/(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            // Validate that these look like coordinates (not part of a longer number)
            $lat = (float)$matches[1];
            $lng = (float)$matches[2];
            
            // Basic validation: coordinates should be within valid ranges
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // If we have a place_id, try to get coordinates from Google Places API
        $placeId = null;
        if (preg_match('/place_id=([^&]+)/', $url, $matches)) {
            $placeId = urldecode($matches[1]);
        } elseif (preg_match('/[?&]q=place_id:([^&]+)/', $url, $matches)) {
            $placeId = urldecode($matches[1]);
        }

        if ($placeId) {
            $placesApiKey = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));
            if ($placesApiKey) {
                $placeDetails = $this->getPlaceDetailsWithGeometry($placeId, $placesApiKey);
                if ($placeDetails && isset($placeDetails['geometry']['location'])) {
                    return [
                        'lat' => (float)$placeDetails['geometry']['location']['lat'],
                        'lng' => (float)$placeDetails['geometry']['location']['lng'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get place details including geometry (coordinates) from Google Places API
     *
     * @param string $placeId
     * @param string $apiKey
     * @return array|null
     */
    private function getPlaceDetailsWithGeometry(string $placeId, string $apiKey)
    {
        try {
            $url = "https://maps.googleapis.com/maps/api/place/details/json?" .
                   "place_id={$placeId}&" .
                   "fields=name,geometry,formatted_address&" .
                   "key={$apiKey}";
            
            $response = Http::timeout(10)->get($url);
            
            if (!$response->successful()) {
                return null;
            }
            
            $data = $response->json();
            
            if ($data && isset($data['status']) && $data['status'] === 'OK' && isset($data['result'])) {
                return $data['result'];
            }
        } catch (\Exception $e) {
            Log::warning('Place details with geometry error: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Geocode a location to get latitude and longitude
     * Uses cache to avoid unnecessary API calls for the same location
     *
     * @param string $location
     * @param string $apiKey
     * @return array|null Returns ['lat' => float, 'lng' => float] or null
     */
    private function geocodeLocation(string $location, string $apiKey)
    {
        // Normalize location name for cache key (lowercase, trim)
        $cacheKey = 'geocode_' . md5(strtolower(trim($location)) . '_kuwait');
        
        // Check cache first - coordinates don't change, so cache forever
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $address = urlencode($location . ', Kuwait');
            $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$apiKey}";
            
            $response = Http::timeout(10)->get($url);
            
            if (!$response->successful()) {
                return null;
            }
            
            $data = $response->json();
            
            if ($data && isset($data['status']) && $data['status'] === 'OK' && !empty($data['results'])) {
                $locationData = $data['results'][0]['geometry']['location'];
                $result = [
                    'lat' => $locationData['lat'],
                    'lng' => $locationData['lng'],
                ];
                
                // Cache the result forever (coordinates don't change)
                // Cache for 1 year (31536000 seconds) - practically forever
                Cache::put($cacheKey, $result, now()->addYear());
                
                return $result;
            }
        } catch (\Exception $e) {
            Log::warning('Geocoding error: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Determine location_verified status based on confidence score
     *
     * @param int $confidence
     * @return string
     */
    private function determineLocationVerified(int $confidence): string
    {
        if ($confidence >= 80) {
            return 'verified';
        } elseif ($confidence >= 50) {
            return 'human_verification_required';
        } else {
            return 'unverified';
        }
    }
}

