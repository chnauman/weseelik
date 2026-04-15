<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Garage;
use App\Models\Tag;
use App\Models\Service;
use App\Models\Category;
use App\Models\Specialization;
use App\Models\VehicleBrand;

/**
 * ExperimentalChatController
 * 
 * Production-Grade Hybrid Search Engine
 * Architecture:
 *  - LLM acts ONLY as a Data Extractor — it returns structured JSON (IDs only), not text.
 *  - Laravel backend does ALL the heavy lifting (ranking, joins, filtering).
 *  - Stateful pagination via cache (excluded_ids, search context).
 *  - Fuzzy area matching at DB level (no exact enum required for locations).
 *  - Multi-parameter extraction: vehicle brand, areas, services, rating, problem description.
 *  - JSON-Only responses: AI never describes garages — it just extracts parameters.
 */
class ExperimentalChatController extends Controller
{
    // ---- Entry Point -------------------------------------------------------

    public function handleChat(Request $request)
    {
        $validated = $request->validate([
            'message'         => 'required|string|max:5000',
            'conversation_id' => 'nullable|string',
        ]);

        $userMessage    = $validated['message'];
        $conversationId = $validated['conversation_id'] ?? 'exp-' . uniqid();

        $context  = $this->getCtx($conversationId);
        $metadata = $this->getMetadata();

        // --- Step 1: AI Parameter Extraction ---
        $extractedParams = $this->extractParams($userMessage, $context, $metadata);

        // Safety Override: If we're waiting for an external service location and user gave one
        if ($extractedParams['intent'] === 'search' 
            && !empty($extractedParams['areas']) 
            && !empty($context['active_external_service'])
            && empty($extractedParams['services'])
            && empty($extractedParams['tags'])
            && empty($extractedParams['problem'])) 
        {
            $extractedParams['intent']       = 'external_service';
            $extractedParams['service_type'] = $context['active_external_service'];
        }

        // --- Step 2: Route based on extracted intent ---
        if ($extractedParams['intent'] === 'list_areas') {
            return $this->respondWithAreas($conversationId, $metadata);
        }

        if ($extractedParams['intent'] === 'ask_area') {
            // User wants to change area — save their current query intent, just ask which area
            return response()->json([
                'message'         => "Sure! Which area in Kuwait would you like to search?",
                'conversation_id' => $conversationId,
            ]);
        }

        if ($extractedParams['intent'] === 'name_search') {
            return $this->executeNameSearch($extractedParams['garage_name'] ?? '', $conversationId);
        }

        if ($extractedParams['intent'] === 'external_service') {
            $serviceType = $extractedParams['service_type'] ?? '';
            $area = $extractedParams['areas'][0] ?? '';

            // Persist the external service type in memory
            $context['active_external_service'] = $serviceType;
            $this->setCtx($conversationId, $context);

            return $this->executeExternalService(
                $serviceType,
                $area,
                $conversationId
            );
        }

        if ($extractedParams['intent'] === 'chitchat') {
            return response()->json([
                'message'         => $extractedParams['reply'] ?? "How can I help you find a garage in Kuwait?",
                'conversation_id' => $conversationId,
            ]);
        }

        // --- Step 3: Execute DB Search ---
        return $this->executeSearch($extractedParams, $conversationId, $context);
    }

    // ---- AI: Cheap Parameter Extraction ------------------------------------

    /**
     * Low-token call to AI. Returns structured JSON only — never garage cards.
     * The AI's ONLY job: understand intent and map it to parameters.
     */
    private function extractParams(string $message, array $context, array $metadata): array
    {
        $contextStr   = json_encode([
            'active_problem' => $context['active_problem'] ?? null,
            'last_areas'     => $context['last_query']['areas'] ?? [],
            'last_services'  => $context['last_query']['services'] ?? [],
            'active_external_service' => $context['active_external_service'] ?? null,
        ]);

        $tagsStr     = implode(', ', array_slice($metadata['tags'], 0, 30));
        $servicesStr = implode(', ', array_slice($metadata['services'], 0, 30));
        $areasStr    = implode(', ', array_slice($metadata['areas'], 0, 30));
        $brandsStr   = implode(', ', array_slice($metadata['brands'], 0, 25));

        $systemPrompt = <<<PROMPT
You are a strict JSON extractor for a Kuwait Garage Finder.
Return ONLY a JSON object. No prose. No explanation.

SAMPLE AREAS: $areasStr
SERVICES: $servicesStr
TAGS: $tagsStr
BRANDS: $brandsStr

CONVERSATION MEMORY: $contextStr

Return EXACTLY one of these shapes:

1. Search — ANY garage-related request (mechanic, AC repair, flat tyre, etc.):
{"intent":"search","areas":[],"services":[],"tags":[],"vehicle_brand":null,"min_rating":null,"problem":null,"is_more":false,"is_previous":false}

2. External Service — when asked for NON-garage car places (petrol stations, car wash, fuel, hospital, ATM, restaurants):
{"intent":"external_service","service_type":"petrol station","areas":["Salmiya"]}

3. Name search — user asks about a SPECIFIC garage by name:
{"intent":"name_search","garage_name":"Auto Salon"}

4. Ask area — user wants to change area OR says 'change location' WITHOUT providing a location:
{"intent":"ask_area"}

5. List areas — ONLY when explicitly asked "what areas" or "which areas do you cover":
{"intent":"list_areas"}

6. Chitchat — ONLY for greetings or clearly off-topic:
{"intent":"chitchat","reply":"<one concise friendly reply>"}

CRITICAL RULES:
- If CONVERSATION MEMORY has "active_external_service" (e.g. "car wash") and the user mentions a location (e.g "salmiya", "alnaeem", "in hawalli"), output intent=external_service with THAT location.
- "show some", "show me garages", "show anything", "just show" → always intent=search, areas=[]
- ANY request for fuel, petrol, gas, car wash, or car cleaning → always intent=external_service. Example: "petrol pump", "gas station", "carwash" (no space), "fuel"
- "in [Location]", "at [Location]", "[Location] area" → output THAT location in the 'areas' array and intent=search (unless an external service is active).
- NEVER output intent=ask_area if the user mentioned a specific location name or town name.
- "show engine" → intent=search, tags=["Engine Repair"]
- "do you have [Garage Name]" or just typing a garage name → intent=name_search
- "change area" (without specifying) → intent=ask_area
- "change area" + location (e.g 'change area to Salmiya') → intent=search with the new area
- is_more=true ONLY when user says "more", "next", "show more"
- is_previous=true when user says "previous", "go back", "first ones again"
- NEVER use list_areas for generic 'show' requests
- If outside Kuwait explicitly (e.g. "Gujrat") → intent=chitchat
PROMPT;

        try {
            $response = OpenAI::chat()->create([
                'model'       => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content'  => $message],
                ],
                'temperature' => 0,
                'max_tokens'  => 200, // Deliberately small — we only want JSON
            ]);

            $rawJson = trim($response->choices[0]->message->content ?? '{}');
            // Strip markdown code fences if any
            $rawJson = preg_replace('/^```json\s*|\s*```$/i', '', $rawJson);
            $parsed  = json_decode($rawJson, true);

            if (!is_array($parsed) || !isset($parsed['intent'])) {
                return ['intent' => 'search', 'areas' => [], 'services' => [], 'tags' => [], 'vehicle_brand' => null, 'min_rating' => null, 'problem' => null, 'is_more' => false, 'is_previous' => false];
            }

            return $parsed;
        } catch (\Exception $e) {
            Log::error('ExtractParams Error: ' . $e->getMessage());
            return ['intent' => 'chitchat', 'reply' => 'Sorry, I had trouble understanding that. Try again?'];
        }
    }

    // ---- DB Search (Laravel does ALL filtering & ranking) ------------------

    private function executeSearch(array $params, string $conversationId, array $context): \Illuminate\Http\JsonResponse
    {
        $areas       = $params['areas'] ?? [];
        $services    = $params['services'] ?? [];
        $tags        = $params['tags'] ?? [];
        $brand       = $params['vehicle_brand'] ?? null;
        $minRating   = $params['min_rating'] ?? null;
        $problem     = $params['problem'] ?? null;
        $isMore      = $params['is_more'] ?? false;
        $isPrevious  = $params['is_previous'] ?? false;
        $limit       = 5;

        // ---- Context Management ----
        // If "more", carry over last query
        if ($isMore && empty($areas)) {
            $areas    = $context['last_query']['areas'] ?? [];
            $services = $context['last_query']['services'] ?? [];
            $tags     = $context['last_query']['tags'] ?? [];
            $brand    = $context['last_query']['brand'] ?? null;
            $problem  = $context['active_problem'] ?? null;
        }

        // If "previous", reset exclusion list to show first results again
        $excludedIds = $context['excluded_ids'] ?? [];
        if ($isPrevious) {
            $excludedIds = [];
        }

        // If search params changed from last query → fresh page
        $lastQuery = $context['last_query'] ?? [];
        $queryChanged = ($areas !== ($lastQuery['areas'] ?? []))
                     || ($services !== ($lastQuery['services'] ?? []))
                     || ($tags !== ($lastQuery['tags'] ?? []))
                     || ($brand !== ($lastQuery['brand'] ?? null));

        if ($queryChanged && !$isMore && !$isPrevious) {
            $excludedIds = [];
        }

        // Save active problem to memory if a new one was mentioned
        $activeProblem = $problem ?? $context['active_problem'] ?? null;

        // ---- Build Query ----
        $query = Garage::query()
            ->where('status', 'approved')
            ->where('is_available', true)
            ->where('country_id', 112) // Kuwait
            ->whereNull('deleted_at');

        // Location: fuzzy match any of the provided areas
        if (!empty($areas)) {
            $query->where(function ($q) use ($areas) {
                foreach ($areas as $area) {
                    $q->orWhere('area', 'like', "%{$area}%")
                      ->orWhere('city', 'like', "%{$area}%")
                      ->orWhere('governate', 'like', "%{$area}%");
                }
            });
        }

        // Services filter
        if (!empty($services)) {
            foreach ($services as $svc) {
                $query->whereHas('services', fn($q) => $q
                    ->where('title', 'like', "%{$svc}%")
                    ->orWhere('english_title', 'like', "%{$svc}%")
                );
            }
        }

        // Tags filter
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $query->whereHas('tags', fn($q) => $q
                    ->where('title', 'like', "%{$tag}%")
                    ->orWhere('english_title', 'like', "%{$tag}%")
                );
            }
        }

        // Problem is saved in memory only — NOT used as a DB filter here
        // (raw problem text rarely matches tag titles exactly)

        // Min rating filter (optional, from extracted params)
        if ($minRating !== null) {
            $query->where('rating', '>=', $minRating);
        }

        // Stateful pagination: exclude already-seen garage IDs
        if (!empty($excludedIds)) {
            $query->whereNotIn('id', $excludedIds);
        }

        // --- Execute primary query ---
        $garages = $query
            ->orderBy('rating', 'desc')
            ->orderBy('no_of_visits', 'desc')
            ->limit($limit)
            ->with(['tags', 'services'])
            ->get();

        // If primary search returned nothing and we had filters, retry without filters (graceful degradation)
        if ($garages->isEmpty() && (!empty($services) || !empty($tags) || !empty($areas))) {
            $fallbackQuery = Garage::query()
                ->where('status', 'approved')
                ->where('is_available', true)
                ->where('country_id', 112)
                ->whereNull('deleted_at');
            
            if (!empty($areas)) {
                $fallbackQuery->where(function ($q) use ($areas) {
                    foreach ($areas as $area) {
                        $q->orWhere('area', 'like', "%{$area}%")
                          ->orWhere('city', 'like', "%{$area}%")
                          ->orWhere('governate', 'like', "%{$area}%");
                    }
                });
            }
            
            if (!empty($excludedIds)) {
                $fallbackQuery->whereNotIn('id', $excludedIds);
            }
            
            $garages = $fallbackQuery
                ->orderBy('rating', 'desc')
                ->orderBy('no_of_visits', 'desc')
                ->limit($limit)
                ->with(['tags', 'services'])
                ->get();
        }

        // Last resort: search all of Kuwait if still empty
        if ($garages->isEmpty()) {
            $garages = Garage::query()
                ->where('status', 'approved')
                ->where('is_available', true)
                ->where('country_id', 112)
                ->whereNull('deleted_at')
                ->whereNotIn('id', $excludedIds)
                ->orderBy('rating', 'desc')
                ->orderBy('no_of_visits', 'desc')
                ->inRandomOrder()
                ->limit($limit)
                ->with(['tags', 'services'])
                ->get();
        }

        // ---- Format lightweight response (no AI tokens wasted on descriptions) ----
        $useFallback = false;
        $formatted = $garages->map(function($g) use (&$useFallback, $areas) {
            // Track if we fell back to garages outside the requested area
            if (!empty($areas)) {
                $matched = collect($areas)->contains(fn($a) =>
                    stripos($g->area ?? '', $a) !== false ||
                    stripos($g->city ?? '', $a) !== false ||
                    stripos($g->governate ?? '', $a) !== false
                );
                if (!$matched) $useFallback = true;
            }

            return [
                'id'          => $g->id,
                'title'       => $g->title,
                'area'        => $g->area,
                'city'        => $g->city,
                'rating'      => $g->rating,
                'visits'      => $g->no_of_visits,
                'phone'       => $g->contact_no,
                'address'     => $g->address,
                'maps_link'   => $g->maps_share_link,
                // Show ONLY English titles to avoid Arabic bleed-through
                'services'    => $g->services->map(fn($s) => $s->english_title)->filter()->take(5)->values(),
                'tags'        => $g->tags->map(fn($t) => $t->english_title)->filter()->take(5)->values(),
                'home_service' => (bool) $g->home_service,
            ];
        });

        // ---- Update Context ----
        $newIds = $garages->pluck('id')->toArray();
        $updatedCtx = [
            'last_query'    => compact('areas', 'services', 'tags', 'brand'),
            'active_problem' => $activeProblem,
            'excluded_ids'  => array_unique(array_merge($excludedIds, $newIds)),
            'total_shown'   => count($excludedIds) + count($newIds),
        ];
        $this->setCtx($conversationId, $updatedCtx);

        // ---- Build response message ----
        $count = count($formatted);
        if ($count === 0) {
            $locStr = !empty($areas) ? implode(' or ', $areas) : 'Kuwait';
            $msg = "No garages found matching your criteria in {$locStr}. Try asking for a different area or service?";
        } else {
            $locStr  = !empty($areas) ? implode(' / ', $areas) : 'Kuwait';
            $svcStr  = !empty($services) ? ' for ' . implode(' & ', $services) : ($activeProblem ? " for {$activeProblem}" : '');
            $moreStr = $isMore ? 'more ' : '';

            if ($useFallback && !empty($areas)) {
                $msg = "I couldn't find exact results in {$locStr}, so here are top-rated garages nearby{$svcStr}:";
            } else {
                $msg = "Here are {$count} {$moreStr}results{$svcStr} in {$locStr}:";
            }
        }

        return response()->json([
            'message'         => $msg,
            'garages'         => $formatted,
            'conversation_id' => $conversationId,
        ]);
    }

    // ---- Name Search (specific garage lookup) --------------------------------

    private function executeNameSearch(string $name, string $conversationId): \Illuminate\Http\JsonResponse
    {
        if (empty(trim($name))) {
            return response()->json(['message' => 'Could you tell me the name of the garage you are looking for?', 'conversation_id' => $conversationId]);
        }

        $garages = Garage::query()
            ->where('status', 'approved')
            ->where('is_available', true)
            ->where('country_id', 112)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($name) {
                $q->where('title', 'like', "%{$name}%")
                  ->orWhere('title_ar', 'like', "%{$name}%");
            })
            ->orderBy('rating', 'desc')
            ->limit(5)
            ->with(['tags', 'services'])
            ->get();

        if ($garages->isEmpty()) {
            return response()->json([
                'message'         => "I couldn't find a garage named \"$name\" in Kuwait. Would you like me to search for a similar service instead?",
                'conversation_id' => $conversationId,
            ]);
        }

        $formatted = $garages->map(fn($g) => [
            'id'           => $g->id,
            'title'        => $g->title,
            'area'         => $g->area,
            'city'         => $g->city,
            'rating'       => $g->rating,
            'visits'       => $g->no_of_visits,
            'phone'        => $g->contact_no,
            'address'      => $g->address,
            'maps_link'    => $g->maps_share_link,
            'services'     => $g->services->map(fn($s) => $s->english_title)->filter()->take(5)->values(),
            'tags'         => $g->tags->map(fn($t) => $t->english_title)->filter()->take(5)->values(),
            'home_service' => (bool) $g->home_service,
        ]);

        return response()->json([
            'message'         => "Yes! Here's what I found for \"$name\":",
            'garages'         => $formatted,
            'conversation_id' => $conversationId,
        ]);
    }

    // ---- Area Listing -------------------------------------------------------

    private function respondWithAreas(string $conversationId, array $metadata): \Illuminate\Http\JsonResponse
    {
        $areas = collect($metadata['areas'])->sort()->values()->take(20);
        $msg   = "I cover these areas in Kuwait:\n" . implode(', ', $areas->toArray()) . "\n\nWhich area are you in?";
        return response()->json(['message' => $msg, 'conversation_id' => $conversationId]);
    }

    // ---- Metadata (Cached 24h) ----------------------------------------------

    private function getMetadata(): array
    {
        return Cache::remember('exp_metadata_v3', now()->addHours(24), function () {
            return [
                'areas'    => Garage::select('area')->distinct()->whereNotNull('area')->where('area', '!=', '')->pluck('area')->toArray(),
                'tags'     => Tag::whereNull('deleted_at')->pluck('english_title')->filter()->values()->toArray(),
                'services' => Service::whereNull('deleted_at')->pluck('english_title')->filter()->values()->toArray(),
                'brands'   => VehicleBrand::pluck('title')->filter()->values()->toArray(),
            ];
        });
    }

    // ---- Context Store (1hr TTL) --------------------------------------------

    private function getCtx(string $id): array
    {
        return Cache::get("ectx_{$id}", [
            'excluded_ids'   => [],
            'last_query'     => [],
            'active_problem' => null,
            'active_external_service' => null,
        ]);
    }

    private function setCtx(string $id, array $ctx): void
    {
        Cache::put("ectx_{$id}", $ctx, now()->addHours(1));
    }

    // ---- Google Places API Integration (External Services) -----------------

    private function executeExternalService(string $serviceType, string $location, string $conversationId): \Illuminate\Http\JsonResponse
    {
        if (empty($location)) {
            return response()->json([
                'message' => "Location is required. Which area in Kuwait would you like to find a $serviceType?",
                'conversation_id' => $conversationId,
            ]);
        }

        $geocodingApiKey = env('GOOGLE_MAPS_API_KEY');
        $placesApiKey    = env('GOOGLE_PLACES_API_KEY', $geocodingApiKey);

        if (!$geocodingApiKey) {
            return response()->json([
                'message' => "I'm sorry, my map features are currently offline.",
                'conversation_id' => $conversationId,
            ]);
        }

        try {
            $geocodeResult = $this->geocodeLocation($location, $geocodingApiKey);
            if ($geocodeResult && isset($geocodeResult['lat']) && isset($geocodeResult['lng'])) {
                $lat       = $geocodeResult['lat'];
                $lng       = $geocodeResult['lng'];
                $placeType = $this->mapServiceTypeToPlaceType($serviceType);
                $results   = $this->searchNearbyPlaces($lat, $lng, $placeType, $placesApiKey);

                if (!empty($results)) {
                    $formattedResults = $this->formatPlaceResults($results);
                    return response()->json([
                        'message'         => "Here are top-rated {$serviceType}s in or near {$location}:",
                        'garages'         => $formattedResults,
                        'conversation_id' => $conversationId,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Google Maps API error: ' . $e->getMessage());
        }

        return response()->json([
            'message'         => "Sorry, I couldn't find specific {$serviceType} locations near {$location} right now.",
            'conversation_id' => $conversationId,
        ]);
    }

    private function geocodeLocation(string $location, string $apiKey)
    {
        $cacheKey = 'geocode_' . md5(strtolower(trim($location)) . '_kuwait');
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        try {
            $address  = urlencode($location . ', Kuwait');
            $url      = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$apiKey}";
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                if ($data && isset($data['status']) && $data['status'] === 'OK' && !empty($data['results'])) {
                    $locData = $data['results'][0]['geometry']['location'];
                    $result  = ['lat' => $locData['lat'], 'lng' => $locData['lng']];
                    Cache::put($cacheKey, $result, now()->addYear());
                    return $result;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Geocoding error: ' . $e->getMessage());
        }
        return null;
    }

    private function mapServiceTypeToPlaceType(string $serviceType)
    {
        $serviceType = strtolower($serviceType);
        $mapping = [
            'restaurant' => 'restaurant', 'restaurants' => 'restaurant', 'food' => 'restaurant',
            'atm' => 'atm', 'atms' => 'atm', 'bank' => 'bank', 'banks' => 'bank',
            'hotel' => 'lodging', 'hotels' => 'lodging',
            'shopping' => 'shopping_mall', 'mall' => 'shopping_mall',
            'hospital' => 'hospital', 'hospitals' => 'hospital', 'clinic' => 'doctor',
            'pharmacy' => 'pharmacy', 'pharmacies' => 'pharmacy',
            'petrol station' => 'gas_station', 'gas station' => 'gas_station', 'petrol' => 'gas_station', 'petrol pump' => 'gas_station',
            'parking' => 'parking', 'parking lot' => 'parking',
            'car rental' => 'car_rental', 'rental' => 'car_rental',
            'car wash' => 'car_wash', 'wash' => 'car_wash', 'carwash' => 'car_wash',
            'insurance' => 'insurance_agency', 'car insurance' => 'insurance_agency',
            'gym' => 'gym', 'fitness' => 'gym',
            'cafe' => 'cafe', 'coffee' => 'cafe',
            'supermarket' => 'supermarket', 'grocery' => 'supermarket',
            'store' => 'store', 'shop' => 'store',
        ];

        if (isset($mapping[$serviceType])) return $mapping[$serviceType];

        foreach ($mapping as $key => $placeType) {
            if (strpos($serviceType, $key) !== false) return $placeType;
        }
        return 'establishment';
    }

    private function searchNearbyPlaces(float $lat, float $lng, string $placeType, string $apiKey)
    {
        $radius          = 2000;
        $maxRadius       = 10000;
        $minResults      = 2;
        $requiredResults = 4;
        $allResults      = [];

        while ($radius <= $maxRadius) {
            $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?location={$lat},{$lng}&radius={$radius}&type={$placeType}&key={$apiKey}";
            try {
                $response = Http::timeout(10)->get($url);
                if (!$response->successful()) break;
                
                $data = $response->json();
                if ($data && $data['status'] === 'OK' && !empty($data['results'])) {
                    foreach ($data['results'] as $result) {
                        $placeId = $result['place_id'] ?? '';
                        if (!isset($allResults[$placeId])) {
                            $allResults[$placeId] = $result;
                        }
                    }
                    if (count($allResults) >= $minResults) {
                        if (count($allResults) < $requiredResults && $radius < $maxRadius) {
                            $radius = min($radius * 2, $maxRadius);
                            continue;
                        }
                        break;
                    }
                } elseif ($data && $data['status'] === 'ZERO_RESULTS') {
                    $radius = min($radius * 2, $maxRadius);
                    continue;
                } else {
                    break;
                }
            } catch (\Exception $e) {
                Log::warning('Nearby search error: ' . $e->getMessage());
                break;
            }
            $radius = min($radius * 2, $maxRadius);
        }
        return array_values($allResults);
    }

    private function formatPlaceResults(array $places)
    {
        $formatted = [];
        $apiKey    = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));

        foreach (array_slice($places, 0, 5) as $place) {
            $placeId = $place['place_id'] ?? '';
            $name    = $place['name'] ?? 'Unknown';
            $address = $place['formatted_address'] ?? ($place['vicinity'] ?? null);
            $rating  = $place['rating'] ?? null;
            $visits  = $place['user_ratings_total'] ?? null;
            $phone   = null;

            if ($apiKey && $placeId) {
                try {
                    $details = $this->getPlaceDetails($placeId, $apiKey);
                    if ($details) {
                        $phone = $details['formatted_phone_number'] ?? $details['international_phone_number'] ?? null;
                        if (isset($details['formatted_address']) && !empty($details['formatted_address'])) {
                            $address = $details['formatted_address'];
                        } elseif (isset($details['vicinity']) && !empty($details['vicinity']) && empty($address)) {
                            $address = $details['vicinity'];
                        }
                    }
                } catch (\Exception $e) {}
            }

            $lat = $place['geometry']['location']['lat'] ?? null;
            $lng = $place['geometry']['location']['lng'] ?? null;
            $googleMapsUrl = $this->generateGoogleMapsUrl($name, $address, $placeId, $lat, $lng);

            // Structure aligns EXACTLY with what the UI blade template expects
            $formatted[] = [
                'id'          => 'place_' . $placeId,
                'title'       => $name,
                'area'        => $address, // Maps to the location pin icon on frontend
                'city'        => null,
                'rating'      => $rating,
                'visits'      => $visits, // used in UI as visits if it exists
                'phone'       => $phone,
                'address'     => $address,
                'maps_link'   => $googleMapsUrl,
                'services'    => [],
                'tags'        => ['External Service'], 
                'home_service'=> false,
            ];
        }

        return $formatted;
    }

    private function getPlaceDetails(string $placeId, string $apiKey)
    {
        try {
            $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id={$placeId}&fields=formatted_phone_number,international_phone_number,formatted_address,vicinity&key={$apiKey}";
            $response = Http::timeout(10)->get($url);
            if ($response->successful()) {
                $data = $response->json();
                if ($data && isset($data['status']) && $data['status'] === 'OK' && isset($data['result'])) {
                    return $data['result'];
                }
            }
        } catch (\Exception $e) {}
        return null;
    }

    private function generateGoogleMapsUrl(string $name, ?string $address, ?string $placeId, ?float $lat, ?float $lng): string
    {
        if ($placeId) {
            return "https://www.google.com/maps/search/?api=1&query=" . urlencode($name) . "&query_place_id={$placeId}";
        }
        if ($lat && $lng) {
            return "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
        }
        $query = !empty($address) ? $name . ', ' . $address : $name;
        return "https://www.google.com/maps/search/?api=1&query=" . urlencode($query);
    }
}
