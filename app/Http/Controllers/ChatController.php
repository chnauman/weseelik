<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Garage;
use App\Models\Tag;
use App\Models\Service;
use App\Models\Category;
use App\Models\Specialization;

class ChatController extends Controller
{
    /**
     * Handle chat messages
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleChat(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|string',
        ]);

        $userMessage = $validated['message'];
        $model = env('OPENAI_MODEL', 'gpt-4o-mini');
        $temperature = env('OPENAI_TEMPERATURE', 0.7);
        // Resolve or create conversation (supports guests via session id)
        $conversation = "";
        
        if (!empty($validated['conversation_id'])) {
            $conversation = DB::table('conversations')->where('session_id', $validated['conversation_id'])->first();
        }
        if (!$conversation) {
            $conversationId = DB::table('conversations')->insertGetId([
                'user_id' => auth()->id(),
                'session_id' => $validated['conversation_id'], //session()->getId(),
                'title' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $conversation = DB::table('conversations')->where('id', $conversationId)->first();
        }

        // Save user message
        DB::table('conversation_messages')->insert([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'model' => $model,
            'tokens_used' => null,
            'created_at' => now(),
        ]);

        $tags = DB::table('tags')
            ->whereNull('deleted_at')
            ->select(DB::raw('COALESCE(english_title, title) as display_title'))
            ->pluck('display_title')
            ->implode(', ');

        $specializations = DB::table('specializations')
            ->whereNull('deleted_at')
            ->select(DB::raw('COALESCE(english_title, title) as display_title'))
            ->pluck('display_title')
            ->implode(', ');

        $services = DB::table('services')
            ->whereNull('deleted_at')
            ->select(DB::raw('COALESCE(english_title, title) as display_title'))
            ->pluck('display_title')
            ->implode(', ');

        $categories = DB::table('categories')
            ->whereNull('deleted_at')
            ->select(DB::raw('COALESCE(english_title, title) as display_title'))
            ->pluck('display_title')
            ->implode(', ');

        // Load recent history and prepend system prompt (typo fixed)
        $history = DB::table('conversation_messages')
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();
        // reverse the history
        $history = array_reverse($history);

        //return response()->json($history);

        array_unshift($history, [
            'role' => 'system',
            'content' => "
                You are a polite, human-like Garage Finder chatbot agent for the Kuwait Region.
                Your job is to help people find a garage based on their needs. Always greet the user warmly but casually.
                Be calm, concise, and human — usually reply in 1–2 lines, or rarely 3 short lines when it feels natural.

                You can use the following information to understand the user’s intent and help find a garage:
                - Tags: $tags
                - Specializations: $specializations
                - Services: $services
                - Categories: $categories

                When the user describes a problem (e.g., 'my car’s AC is not cooling'), intelligently map it to relevant tags, services, specializations, or categories, and pass them in the function call as arrays:
                - 'selected_tags': inferred issue tags
                - 'selected_specializations': relevant expertise or brand specialization
                - 'selected_services': relevant garage services
                - 'selected_categories': relevant garage category

                Rules:
                - Use whatever details are available from the user or chat history.
                - Never ask for all parameters at once. Gather naturally, one by one.
                - Never ask for obvious info (e.g., if the user says 'in Salmiya', don't ask 'Where are you located?').
                - Once enough info is available, call the function automatically and politely confirm.
                - For garage-related queries: If the user asks for automotive services that match the available database services/tags/categories/specializations, use the findGarage function.
                - For ANY location-based service query that is NOT in the garage database (including restaurants, ATMs, hotels, shopping centers, hospitals, pharmacies, petrol stations, parking lots, car rentals, or any other location-based service), automatically use the findExternalService function. This includes ALL types of businesses and services in Kuwait.
                - Always ask for the location/area if it's missing when using findExternalService.
                - The findExternalService function works for ANY location-based service in Kuwait region - be helpful and use it whenever a user asks for any type of location or business.
                - When displaying results from findExternalService, format them as a numbered list in this exact format (blank line after business name):
                  1. [Business Name](google_maps_url) - Make the business name clickable as a link that opens Google Maps
                  Address: Street name, Area
                  Phone: +965 XXXX XXXX (omit Phone line entirely if phone is null or not available)
    
                  (Continue numbering for all results, each result on separate lines)
                  IMPORTANT: Always format business names as markdown links using the format [Business Name](google_maps_url) where google_maps_url is provided in the results. This makes the business name clickable and opens Google Maps for that location.
                - Be helpful and assist users with any location-based service requests in Kuwait. Only decline if the query is completely unrelated to finding locations or services (e.g., general knowledge questions, non-location queries).
                "
        ]);

        //return response()->json($history);

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'findGarage',
                    'description' => 'Find a garage based on the user’s provided details, filters, and preferences.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => [
                                'type' => 'string',
                                'description' => 'The location or area name where the user wants to find a garage.'
                            ],
                            'vehicle_brand' => [
                                'type' => 'string',
                                'description' => 'Brand of the vehicle, e.g., Toyota, Honda, Nissan, etc.'
                            ],
                            'vehicle_model' => [
                                'type' => 'string',
                                'description' => 'Model of the vehicle.'
                            ],
                            'vehicle_year' => [
                                'type' => 'string',
                                'description' => 'Manufacturing year of the vehicle.'
                            ],
                            'selected_tags' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ],
                                'description' => 'Tags inferred from the user’s problem or request (e.g., "oil change", "flat tire").'
                            ],
                            'selected_specializations' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ],
                                'description' => 'Specific vehicle or service specializations relevant to the user’s need.'
                            ],
                            'selected_services' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ],
                                'description' => 'Specific garage services (e.g., AC repair, engine tuning, car wash).'
                            ],
                            'selected_categories' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ],
                                'description' => 'Garage business categories, such as "mechanic", "electrician", or "tire shop".'
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listGarages',
                    'description' => 'List all garages in the region.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => ['type' => 'string'],
                            'vehicle_brand' => ['type' => 'string'],
                            'vehicle_model' => ['type' => 'string'],
                            'vehicle_year' => ['type' => 'string'],
                        ],
                        'required' => ['location', 'vehicle_brand', 'vehicle_model', 'vehicle_year'],
                    ],
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'getGarageDetails',
                    'description' => 'Get the details of a garage.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'garage_id' => ['type' => 'string'],
                            'location' => ['type' => 'string'],
                            'garage_name' => ['type' => 'string'],
                            'garage_address' => ['type' => 'string'],
                            'garage_phone' => ['type' => 'string'],
                            'garage_email' => ['type' => 'string'],
                            'garage_website' => ['type' => 'string'],
                            'garage_logo' => ['type' => 'string'],
                            'garage_storefront_image' => ['type' => 'string'],
                            'garage_gallery' => ['type' => 'string'],
                        ],
                    ],
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'getGarageServices',
                    'description' => 'Get the services of a garage.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'garage_id' => ['type' => 'string'],
                            'service_name' => ['type' => 'string'],
                            'service_description' => ['type' => 'string'],
                            'service_price' => ['type' => 'string'],
                            'service_duration' => ['type' => 'string'],
                            'service_image' => ['type' => 'string'],
                        ],
                    ],
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'getGarageReviews',
                    'description' => 'Get the reviews of a garage.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'garage_id' => ['type' => 'string'],
                            'service_name' => ['type' => 'string'],
                            'date_range' => ['type' => 'string'],
                            'garage_name' => ['type' => 'string'],
                            'garage_address' => ['type' => 'string'],
                            'garage_phone' => ['type' => 'string'],
                        ],
                    ],
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'findExternalService',
                    'description' => 'Find ANY type of location-based services in Kuwait (restaurants, ATMs, hotels, shopping malls, hospitals, pharmacies, petrol stations, parking lots, car rentals, entertainment venues, or any other business/service location). Use this function for ANY location-based query that is not in the garage database services, tags, categories, or specializations. This function works for all types of businesses and services in Kuwait.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'service_type' => [
                                'type' => 'string',
                                'description' => 'The type of service or place the user is looking for (e.g., "petrol station", "ATM", "car insurance company", "parking lot", "car rental").'
                            ],
                            'location' => [
                                'type' => 'string',
                                'description' => 'The area or location in Kuwait where the user wants to find the service (e.g., "Salmiya", "Kuwait City", "Hawalli"). If not provided by user, ask for it.'
                            ],
                        ],
                        'required' => ['service_type', 'location'],
                    ],
                ]
            ]
        ];
        
        

        $response = OpenAI::chat()->create([
            'model' => $model,
            'messages' => $history,
            'temperature' => $temperature,
            'tools' => $tools,
            "tool_choice" => "auto"
        ]);

        // Handle tool calls if present
        $assistantText = $response->choices[0]->message->content ?? '';
        $toolCalls = $response->choices[0]->message->toolCalls
            ?? ($response->choices[0]->message->tool_calls ?? []);

        $tokensUsed = $response->usage->totalTokens ?? 0;

        //return response()->json($response);

        // Extract latitude and longitude from tool results
        $latitude = null;
        $longitude = null;

        if (!empty($toolCalls)) {
            // Append assistant message with tool_calls to the history
            $history[] = [
                'role' => 'assistant',
                'content' => $response->choices[0]->message->content ?? '',
                'tool_calls' => array_map(function ($tc) {
                    return [
                        'id' => $tc->id ?? null,
                        'type' => 'function',
                        'function' => [
                            'name' => $tc->function->name ?? '',
                            'arguments' => $tc->function->arguments ?? '{}',
                        ],
                    ];
                }, $toolCalls),
            ];

            foreach ($toolCalls as $tc) {
                $toolName = $tc->function->name ?? '';
                $argsJson = $tc->function->arguments ?? '{}';
                $args = json_decode($argsJson, true);
                if (!is_array($args)) {
                    $args = [];
                }

                $result = $this->dispatchTool($toolName, $args);

                // Extract lat/lng from tool results (only if we don't have them yet or if these are valid)
                if (is_array($result)) {
                    $tempLat = null;
                    $tempLng = null;
                    
                    // For findGarage, listGarages, findExternalService - extract from first result
                    if (isset($result['results']) && is_array($result['results']) && !empty($result['results'])) {
                        $firstResult = $result['results'][0];
                        if (isset($firstResult['latitude']) && isset($firstResult['longitude'])) {
                            $latVal = $firstResult['latitude'];
                            $lngVal = $firstResult['longitude'];
                            // Check if values are not null and not empty strings
                            if ($latVal !== null && $latVal !== '' && $lngVal !== null && $lngVal !== '') {
                                $tempLat = is_numeric($latVal) ? (float)$latVal : $latVal;
                                $tempLng = is_numeric($lngVal) ? (float)$lngVal : $lngVal;
                            }
                        }
                    }
                    // For getGarageDetails - extract from result
                    elseif (isset($result['result']) && is_array($result['result'])) {
                        if (isset($result['result']['latitude']) && isset($result['result']['longitude'])) {
                            $latVal = $result['result']['latitude'];
                            $lngVal = $result['result']['longitude'];
                            // Check if values are not null and not empty strings
                            if ($latVal !== null && $latVal !== '' && $lngVal !== null && $lngVal !== '') {
                                $tempLat = is_numeric($latVal) ? (float)$latVal : $latVal;
                                $tempLng = is_numeric($lngVal) ? (float)$lngVal : $lngVal;
                            }
                        }
                    }
                    
                    // Only update if we found valid coordinates
                    if ($tempLat !== null && $tempLng !== null) {
                        $latitude = $tempLat;
                        $longitude = $tempLng;
                    }
                }

                $history[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc->id ?? '',
                    'content' => is_string($result) ? $result : json_encode($result),
                ];
            }

            // Follow-up call to get the final assistant answer
            $second = OpenAI::chat()->create([
                'model' => $model,
                'messages' => $history,
                'temperature' => $temperature,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ]);

            $assistantText = $second->choices[0]->message->content ?? '';
            $tokensUsed += $second->usage->totalTokens ?? 0;
        }

        // Save assistant response
        DB::table('conversation_messages')->insert([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $assistantText,
            'model' => $model,
            'tokens_used' => $tokensUsed,
            'created_at' => now(),
        ]);

        return response()->json([
            'id' => $conversation->id,
            'message' => $assistantText,
            'conversation_id' => request()->conversation_id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'history' => $history,
        ]);
    }

    private function dispatchTool(string $name, array $args)
    {
        switch ($name) {
            case 'findGarage':
                return $this->toolFindGarage($args);
            case 'listGarages':
                return $this->toolListGarages($args);
            case 'getGarageDetails':
                return $this->toolGetGarageDetails($args);
            case 'getGarageServices':
                return $this->toolGetGarageServices($args);
            case 'getGarageReviews':
                return $this->toolGetGarageReviews($args);
            case 'findExternalService':
                return $this->toolFindExternalService($args);
            default:
                return ['error' => 'Unknown tool: ' . $name];
        }
    }

    private function toolFindGarage(array $args)
    {
        $location = $args['location'] ?? null;
        $selectedTags = $args['selected_tags'] ?? [];
        $selectedServices = $args['selected_services'] ?? [];
        $selectedSpecializations = $args['selected_specializations'] ?? [];
        $selectedCategories = $args['selected_categories'] ?? [];

        // Kuwait country ID is 112
        $query = Garage::query()
            ->where('status', 'approved')
            ->where('is_available', true)
            ->where('country_id', 112) // Only show garages in Kuwait
            ->whereNull('deleted_at');

        // Filter by location (area, city, address)
        if ($location) {
            $query->where(function ($q) use ($location) {
                $q->where('area', 'like', "%{$location}%")
                    ->orWhere('city', 'like', "%{$location}%")
                    ->orWhere('address', 'like', "%{$location}%")
                    ->orWhere('governate', 'like', "%{$location}%");
            });
        }

        // Filter by tags
        if (!empty($selectedTags)) {
            $query->whereHas('tags', function ($q) use ($selectedTags) {
                $q->where(function ($subQ) use ($selectedTags) {
                    foreach ($selectedTags as $tag) {
                        $subQ->orWhere('title', 'like', "%{$tag}%")
                            ->orWhere('english_title', 'like', "%{$tag}%");
                    }
                })->whereNull('tags.deleted_at');
            });
        }

        // Filter by services
        if (!empty($selectedServices)) {
            $query->whereHas('services', function ($q) use ($selectedServices) {
                $q->where(function ($subQ) use ($selectedServices) {
                    foreach ($selectedServices as $service) {
                        $subQ->orWhere('title', 'like', "%{$service}%")
                            ->orWhere('english_title', 'like', "%{$service}%");
                    }
                })->whereNull('services.deleted_at');
            });
        }

        // Filter by specializations
        if (!empty($selectedSpecializations)) {
            $query->whereHas('specializations', function ($q) use ($selectedSpecializations) {
                $q->where(function ($subQ) use ($selectedSpecializations) {
                    foreach ($selectedSpecializations as $specialization) {
                        $subQ->orWhere('title', 'like', "%{$specialization}%")
                            ->orWhere('english_title', 'like', "%{$specialization}%");
                    }
                })->whereNull('specializations.deleted_at');
            });
        }

        // Filter by categories
        if (!empty($selectedCategories)) {
            $query->whereHas('categories', function ($q) use ($selectedCategories) {
                $q->where(function ($subQ) use ($selectedCategories) {
                    foreach ($selectedCategories as $category) {
                        $subQ->orWhere('title', 'like', "%{$category}%")
                            ->orWhere('english_title', 'like', "%{$category}%");
                    }
                })->whereNull('categories.deleted_at');
            });
        }

        $garages = $query->with(['tags', 'services', 'specializations', 'categories'])
            ->orderBy('rating', 'desc')
            ->orderBy('no_of_visits', 'desc')
            ->limit(10)
            ->get();

        if ($garages->isEmpty()) {
            return [
                'status' => 'not_found',
                'message' => 'No garages found matching your criteria.',
                'results' => [],
            ];
        }

        $geocodingApiKey = env('GOOGLE_MAPS_API_KEY');
        $results = $garages->map(function ($garage, $index) use ($geocodingApiKey) {
            $lat = $garage->latitude;
            $lng = $garage->longitude;
            
            // If coordinates are missing, try to geocode the address (only for first result to avoid performance issues)
            if ($index === 0 && ($lat === null || $lat === '' || $lng === null || $lng === '') && $geocodingApiKey) {
                $addressToGeocode = null;
                if ($garage->address) {
                    $addressToGeocode = $garage->address;
                    if ($garage->area) {
                        $addressToGeocode .= ', ' . $garage->area;
                    }
                    if ($garage->city) {
                        $addressToGeocode .= ', ' . $garage->city;
                    }
                    $addressToGeocode .= ', Kuwait';
                    
                    $geocodeResult = $this->geocodeLocation($addressToGeocode, $geocodingApiKey);
                    if ($geocodeResult && isset($geocodeResult['lat']) && isset($geocodeResult['lng'])) {
                        $lat = $geocodeResult['lat'];
                        $lng = $geocodeResult['lng'];
                    }
                }
            }
            
            return [
                'id' => $garage->id,
                'title' => $garage->title,
                'description' => $garage->description,
                'address' => $garage->address,
                'area' => $garage->area,
                'city' => $garage->city,
                'contact_no' => $garage->contact_no,
                'rating' => $garage->rating,
                'no_of_visits' => $garage->no_of_visits,
                'home_service' => $garage->home_service ?? false,
                'pickup_service' => $garage->pickup_service ?? false,
                'maps_share_link' => $garage->maps_share_link,
                'latitude' => $lat !== null && $lat !== '' ? (is_numeric($lat) ? (float)$lat : $lat) : null,
                'longitude' => $lng !== null && $lng !== '' ? (is_numeric($lng) ? (float)$lng : $lng) : null,
                'services' => $garage->services->map(fn($s) => $s->english_title ?? $s->title)->toArray(),
                'tags' => $garage->tags->map(fn($t) => $t->english_title ?? $t->title)->toArray(),
                'specializations' => $garage->specializations->map(fn($sp) => $sp->english_title ?? $sp->title)->toArray(),
            ];
        })->toArray();

        return [
            'status' => 'success',
            'action' => 'find_garage',
            'count' => count($results),
            'results' => $results,
        ];
    }

    private function toolListGarages(array $args)
    {
        $location = $args['location'] ?? null;

        // Kuwait country ID is 112
        $query = Garage::query()
            ->where('status', 'approved')
            ->where('is_available', true)
            ->where('country_id', 112) // Only show garages in Kuwait
            ->whereNull('deleted_at');

        // Filter by location if provided
        if ($location) {
            $query->where(function ($q) use ($location) {
                $q->where('area', 'like', "%{$location}%")
                    ->orWhere('city', 'like', "%{$location}%")
                    ->orWhere('address', 'like', "%{$location}%")
                    ->orWhere('governate', 'like', "%{$location}%");
            });
        }

        $garages = $query->with(['tags', 'services', 'specializations', 'categories'])
            ->orderBy('rating', 'desc')
            ->orderBy('no_of_visits', 'desc')
            ->limit(20)
            ->get();

        if ($garages->isEmpty()) {
            return [
                'status' => 'not_found',
                'action' => 'list_garages',
                'message' => 'No garages found.',
                'results' => [],
            ];
        }

        $results = $garages->map(function ($garage) {
            return [
                'id' => $garage->id,
                'title' => $garage->title,
                'description' => $garage->description,
                'address' => $garage->address,
                'area' => $garage->area,
                'city' => $garage->city,
                'contact_no' => $garage->contact_no,
                'rating' => $garage->rating,
                'no_of_visits' => $garage->no_of_visits,
                'latitude' => $garage->latitude,
                'longitude' => $garage->longitude,
            ];
        })->toArray();

        return [
            'status' => 'success',
            'action' => 'list_garages',
            'count' => count($results),
            'results' => $results,
        ];
    }

    private function toolGetGarageDetails(array $args)
    {
        $garageId = $args['garage_id'] ?? null;

        if (!$garageId) {
            return [
                'status' => 'error',
                'action' => 'get_garage_details',
                'message' => 'Garage ID is required.',
            ];
        }

        // Kuwait country ID is 112
        $garage = Garage::with(['tags', 'services', 'specializations', 'categories', 'work_shifts', 'contacts', 'country'])
            ->where('status', 'approved')
            ->where('country_id', 112) // Only show garages in Kuwait
            ->whereNull('deleted_at')
            ->find($garageId);

        if (!$garage) {
            return [
                'status' => 'not_found',
                'action' => 'get_garage_details',
                'message' => 'Garage not found. Please ensure the garage ID is correct.',
            ];
        }

        return [
            'status' => 'success',
            'action' => 'get_garage_details',
            'result' => [
                'id' => $garage->id,
                'title' => $garage->title,
                'description' => $garage->description,
                'service_description' => $garage->service_description,
                'address' => $garage->address,
                'area' => $garage->area,
                'city' => $garage->city,
                'block' => $garage->block,
                'governate' => $garage->governate,
                'contact_no' => $garage->contact_no,
                'rating' => $garage->rating,
                'no_of_visits' => $garage->no_of_visits,
                'no_of_likes' => $garage->no_of_likes,
                'home_service' => $garage->home_service ?? false,
                'pickup_service' => $garage->pickup_service ?? false,
                'maps_share_link' => $garage->maps_share_link,
                'latitude' => $garage->latitude,
                'longitude' => $garage->longitude,
                'services' => $garage->services->map(fn($s) => [
                    'id' => $s->id,
                    'title' => $s->english_title ?? $s->title,
                ])->toArray(),
                'tags' => $garage->tags->map(fn($t) => [
                    'id' => $t->id,
                    'title' => $t->english_title ?? $t->title,
                ])->toArray(),
                'specializations' => $garage->specializations->map(fn($sp) => [
                    'id' => $sp->id,
                    'title' => $sp->english_title ?? $sp->title,
                ])->toArray(),
                'categories' => $garage->categories->map(fn($c) => [
                    'id' => $c->id,
                    'title' => $c->english_title ?? $c->title,
                ])->toArray(),
            ],
        ];
    }

    private function toolGetGarageServices(array $args)
    {
        $garageId = $args['garage_id'] ?? null;

        if (!$garageId) {
            return [
                'status' => 'error',
                'action' => 'get_garage_services',
                'message' => 'Garage ID is required.',
            ];
        }

        // Kuwait country ID is 112
        $garage = Garage::with(['services'])
            ->where('status', 'approved')
            ->where('country_id', 112) // Only show garages in Kuwait
            ->whereNull('deleted_at')
            ->find($garageId);

        if (!$garage) {
            return [
                'status' => 'not_found',
                'action' => 'get_garage_services',
                'message' => 'Garage not found. Please ensure the garage ID is correct.',
            ];
        }

        $services = $garage->services->map(function ($service) {
            return [
                'id' => $service->id,
                'title' => $service->english_title ?? $service->title,
                'name' => $service->english_title ?? $service->title,
            ];
        })->toArray();

        if (empty($services)) {
            return [
                'status' => 'not_found',
                'action' => 'get_garage_services',
                'message' => 'No services found for this garage.',
                'results' => [],
            ];
        }

        return [
            'status' => 'success',
            'action' => 'get_garage_services',
            'garage_id' => $garage->id,
            'garage_title' => $garage->title,
            'count' => count($services),
            'results' => $services,
        ];
    }

    private function toolGetGarageReviews(array $args)
    {
        // Reviews feature is not implemented
        return [
            'status' => 'not_available',
            'action' => 'get_garage_reviews',
            'message' => 'Review feature is currently not available.',
        ];
    }

    private function toolFindExternalService(array $args)
    {
        $serviceType = $args['service_type'] ?? '';
        $location = $args['location'] ?? '';

        // If location is missing, return an error asking for it
        if (empty($location)) {
            return [
                'status' => 'error',
                'message' => 'Location is required. Please provide the area or location in Kuwait where you want to find ' . $serviceType . '.',
            ];
        }

        // Get Google Maps API keys from environment
        $geocodingApiKey = env('GOOGLE_MAPS_API_KEY');
        $placesApiKey = env('GOOGLE_PLACES_API_KEY', $geocodingApiKey); // Use same key if Places key not set

        // Try Google Maps APIs first
        if ($geocodingApiKey) {
            try {
                // Step 1: Geocode the location to get lat/lng
                $geocodeResult = $this->geocodeLocation($location, $geocodingApiKey);
                if ($geocodeResult && isset($geocodeResult['lat']) && isset($geocodeResult['lng'])) {
                    $lat = $geocodeResult['lat'];
                    $lng = $geocodeResult['lng'];
                    
                    // Step 2: Map service_type to Google place types
                    $placeType = $this->mapServiceTypeToPlaceType($serviceType);
                    
                    // Step 3: Search nearby places with expanding radius
                    $results = $this->searchNearbyPlaces($lat, $lng, $placeType, $placesApiKey);
                    
                    if (!empty($results) && count($results) >= 2) {
                        // Format results with all available fields
                        $formattedResults = $this->formatPlaceResults($results);
                        
                        return [
                            'status' => 'success',
                            'service_type' => $serviceType,
                            'location' => $location,
                            'results' => $formattedResults,
                            'count' => count($formattedResults),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Log error but continue to fallback
                Log::warning('Google Maps API error: ' . $e->getMessage());
            }
        }

        // Fallback to OpenAI if Google APIs fail or return insufficient results
        return $this->fallbackToOpenAI($serviceType, $location);
    }

    /**
     * Geocode a location to get latitude and longitude
     * Uses cache to avoid unnecessary API calls for the same location
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
     * Map service type to Google Places API place type
     */
    private function mapServiceTypeToPlaceType(string $serviceType)
    {
        $serviceType = strtolower($serviceType);
        
        $mapping = [
            'restaurant' => 'restaurant',
            'restaurants' => 'restaurant',
            'food' => 'restaurant',
            'atm' => 'atm',
            'atms' => 'atm',
            'bank' => 'bank',
            'banks' => 'bank',
            'hotel' => 'lodging',
            'hotels' => 'lodging',
            'shopping' => 'shopping_mall',
            'mall' => 'shopping_mall',
            'shopping center' => 'shopping_mall',
            'shopping centre' => 'shopping_mall',
            'hospital' => 'hospital',
            'hospitals' => 'hospital',
            'clinic' => 'doctor',
            'pharmacy' => 'pharmacy',
            'pharmacies' => 'pharmacy',
            'petrol station' => 'gas_station',
            'gas station' => 'gas_station',
            'fuel station' => 'gas_station',
            'petrol' => 'gas_station',
            'parking' => 'parking',
            'parking lot' => 'parking',
            'car rental' => 'car_rental',
            'car rentals' => 'car_rental',
            'rental' => 'car_rental',
            'insurance' => 'insurance_agency',
            'car insurance' => 'insurance_agency',
            'gym' => 'gym',
            'fitness' => 'gym',
            'cafe' => 'cafe',
            'coffee' => 'cafe',
            'supermarket' => 'supermarket',
            'grocery' => 'supermarket',
            'store' => 'store',
            'shop' => 'store',
        ];
        
        // Check exact matches first
        if (isset($mapping[$serviceType])) {
            return $mapping[$serviceType];
        }
        
        // Check partial matches
        foreach ($mapping as $key => $placeType) {
            if (strpos($serviceType, $key) !== false) {
                return $placeType;
            }
        }
        
        // Default to generic establishment
        return 'establishment';
    }

    /**
     * Search for nearby places with expanding radius
     */
    private function searchNearbyPlaces(float $lat, float $lng, string $placeType, string $apiKey)
    {
        $radius = 2000; // Start with 2000 meters
        $maxRadius = 10000; // Maximum radius to try
        $minResults = 2; // Minimum 2-3 results required
        $requiredResults = 4; // Target 4-5 results
        
        $allResults = [];
        
        while ($radius <= $maxRadius) {
            $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?" .
                   "location={$lat},{$lng}&" .
                   "radius={$radius}&" .
                   "type={$placeType}&" .
                   "key={$apiKey}";
            
            try {
                $response = Http::timeout(10)->get($url);
                
                if (!$response->successful()) {
                    break;
                }
                
                $data = $response->json();
            } catch (\Exception $e) {
                Log::warning('Nearby search error: ' . $e->getMessage());
                break;
            }
            
            if ($data && $data['status'] === 'OK' && !empty($data['results'])) {
                // Merge new results, avoiding duplicates
                foreach ($data['results'] as $result) {
                    $placeId = $result['place_id'] ?? '';
                    if (!isset($allResults[$placeId])) {
                        $allResults[$placeId] = $result;
                    }
                }
                
                // If we have at least minimum required results, we can stop
                if (count($allResults) >= $minResults) {
                    // But if we have less than target, try one more expansion
                    if (count($allResults) < $requiredResults && $radius < $maxRadius) {
                        $radius = min($radius * 2, $maxRadius);
                        continue;
                    }
                    break;
                }
            } elseif ($data && $data['status'] === 'ZERO_RESULTS') {
                // No results at this radius, expand
                $radius = min($radius * 2, $maxRadius);
                continue;
            } else {
                // API error, break
                break;
            }
            
            // Expand radius for next iteration
            $radius = min($radius * 2, $maxRadius);
        }
        
        return array_values($allResults);
    }

    /**
     * Format place results with all available fields
     */
    private function formatPlaceResults(array $places)
    {
        $formatted = [];
        $apiKey = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));
        
        foreach ($places as $place) {
            $placeId = $place['place_id'] ?? '';
            $name = $place['name'] ?? 'Unknown';
            $address = $place['vicinity'] ?? ($place['formatted_address'] ?? 'Address not available');
            $rating = $place['rating'] ?? null;
            $userRatingsTotal = $place['user_ratings_total'] ?? null;
            
            // Get detailed place info including phone number
            $phone = null;
            $openingHours = null;
            
            if ($apiKey && $placeId) {
                try {
                    $details = $this->getPlaceDetails($placeId, $apiKey);
                    if ($details) {
                        $phone = $details['formatted_phone_number'] ?? $details['international_phone_number'] ?? null;
                        if (isset($details['opening_hours']['weekday_text'])) {
                            $openingHours = $details['opening_hours']['weekday_text'];
                        } elseif (isset($details['opening_hours']['open_now'])) {
                            $openingHours = $details['opening_hours']['open_now'] ? 'Open now' : 'Closed now';
                        }
                        // Use detailed address if available
                        if (isset($details['formatted_address'])) {
                            $address = $details['formatted_address'];
                        }
                    }
                } catch (\Exception $e) {
                    // Continue without details if API call fails
                }
            }
            
            // Generate Google Maps URL
            $lat = $place['geometry']['location']['lat'] ?? null;
            $lng = $place['geometry']['location']['lng'] ?? null;
            $googleMapsUrl = null;
            
            if ($lat && $lng) {
                $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
            } elseif ($placeId) {
                $googleMapsUrl = "https://www.google.com/maps/place/?q=place_id:" . $placeId;
            } else {
                $mapsQuery = urlencode($address);
                $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query=" . $mapsQuery;
            }
            
            $formatted[] = [
                'name' => $name,
                'address' => $address,
                'phone' => $phone,
                'rating' => $rating,
                'user_ratings_total' => $userRatingsTotal,
                'opening_hours' => $openingHours,
                'latitude' => $lat,
                'longitude' => $lng,
                'google_maps_url' => $googleMapsUrl,
            ];
        }
        
        return $formatted;
    }

    /**
     * Get detailed place information including phone number
     */
    private function getPlaceDetails(string $placeId, string $apiKey)
    {
        try {
            $url = "https://maps.googleapis.com/maps/api/place/details/json?" .
                   "place_id={$placeId}&" .
                   "fields=formatted_phone_number,international_phone_number,formatted_address,opening_hours&" .
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
     * Fallback to OpenAI if Google Maps APIs fail
     */
    private function fallbackToOpenAI(string $serviceType, string $location)
    {
        $model = env('OPENAI_MODEL', 'gpt-4o-mini');
        $temperature = env('OPENAI_TEMPERATURE', 0.7);

        $prompt = "Provide information about 3-4 {$serviceType} locations in {$location}, Kuwait. For each location, provide:
                    1. Name of the place/business
                    2. Address (street name and area)
                    3. Phone number (if available)

                    Format the response as a JSON object with a 'locations' array containing objects with 'name', 'address', and 'phone' fields. If phone is not available, use null.
                    Example format:
                    {
                    \"locations\": [
                        {\"name\": \"Example Name\", \"address\": \"Street name, Area\", \"phone\": \"+965 XXXX XXXX\"},
                        ...
                    ]
                    }

                    Only include real, known locations in Kuwait. If you don't have enough information about specific locations, provide the best available information.";

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that provides accurate location information about businesses and services in Kuwait. Always respond with valid JSON object format containing a "locations" array.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3, // Lower temperature for more factual responses
                'response_format' => ['type' => 'json_object'], // Force JSON response
            ]);

            $content = $response->choices[0]->message->content ?? '';
            
            // Try to parse as JSON
            $data = json_decode($content, true);
            
            // Extract locations array from response
            if (is_array($data)) {
                if (isset($data['locations']) && is_array($data['locations'])) {
                    $locations = $data['locations'];
                } elseif (isset($data['results']) && is_array($data['results'])) {
                    $locations = $data['results'];
                } elseif (isset($data[0]) && is_array($data[0])) {
                    // If it's already an array of objects
                    $locations = $data;
                } else {
                    // Fallback: try to extract JSON array from markdown or text
                    $jsonMatch = [];
                    if (preg_match('/\[[\s\S]*\]/', $content, $jsonMatch)) {
                        $locations = json_decode($jsonMatch[0], true) ?? [];
                    } else {
                        $locations = [];
                    }
                }
            } else {
                $locations = [];
            }

            // Ensure we have an array
            if (!is_array($locations)) {
                $locations = [];
            }

            // Limit to 4 results
            $locations = array_slice($locations, 0, 4);

            // Format and validate the response
            $formattedResults = [];
            $geocodingApiKey = env('GOOGLE_MAPS_API_KEY');
            
            foreach ($locations as $locationItem) {
                if (is_array($locationItem)) {
                    $name = $locationItem['name'] ?? $locationItem['Name'] ?? 'Unknown';
                    $address = $locationItem['address'] ?? $locationItem['Address'] ?? 'Address not available';
                    $phone = $locationItem['phone'] ?? $locationItem['Phone'] ?? $locationItem['phone_number'] ?? null;
                    
                    // Geocode the address to get lat/lng
                    $lat = null;
                    $lng = null;
                    if ($geocodingApiKey) {
                        $fullAddress = $address . ', ' . $location . ', Kuwait';
                        $geocodeResult = $this->geocodeLocation($fullAddress, $geocodingApiKey);
                        if ($geocodeResult && isset($geocodeResult['lat']) && isset($geocodeResult['lng'])) {
                            $lat = $geocodeResult['lat'];
                            $lng = $geocodeResult['lng'];
                        }
                    }
                    
                    // Generate Google Maps URL for this location
                    $mapsQuery = urlencode($address . ', ' . $location . ', Kuwait');
                    $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query=" . $mapsQuery;
                    
                    $formattedResults[] = [
                        'name' => $name,
                        'address' => $address,
                        'phone' => $phone,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'google_maps_url' => $googleMapsUrl,
                    ];
                }
            }

            if (empty($formattedResults)) {
                return [
                    'status' => 'not_found',
                    'message' => "Sorry, I couldn't find specific {$serviceType} locations in {$location}, Kuwait. The information may not be available in my database.",
                    'results' => [],
                ];
            }

            return [
                'status' => 'success',
                'service_type' => $serviceType,
                'location' => $location,
                'results' => $formattedResults,
                'count' => count($formattedResults),
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Unable to fetch service information at the moment. Please try again later.',
                'error' => $e->getMessage(),
            ];
        }
    }
}
