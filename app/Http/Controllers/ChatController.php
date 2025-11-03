<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // TODO: Replace with real implementation (persist lost item report)
        return [
            'status' => 'ok',
            'action' => 'find_garage',
            'received' => $args,
            'message' => 'Garage found. A case reference will be emailed shortly.',
        ];
    }

    private function toolListGarages(array $args)
    {
        // TODO: Replace with real implementation (create claim)
        return [
            'status' => 'ok',
            'action' => 'list_garages',
            'received' => $args,
            'message' => 'Garages listed. We will verify ownership and get back to you.',
        ];
    }

    private function toolGetGarageDetails(array $args)
    {
        // TODO: Replace with real implementation (lookup by reference/email)
        return [
            'status' => 'ok',
            'action' => 'get_garage_details',
            'received' => $args,
            'result' => 'Garage details not found. Please ensure the garage id is correct.',
        ];
    }

    private function toolGetGarageServices(array $args)
    {
        // TODO: Replace with real implementation (schedule appointment)
        return [
            'status' => 'ok',
            'action' => 'get_garage_services',
            'received' => $args,
            'message' => 'Garage services not found. Please ensure the garage id is correct.',
        ];
    }

    private function toolGetGarageReviews(array $args)
    {
        // TODO: Replace with real implementation (query recent found items)
        return [
            'status' => 'ok',
            'action' => 'get_garage_reviews',
            'received' => $args,
            'message' => 'Garage reviews not found. Please ensure the garage id is correct.',
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

        // Use OpenAI to get location-based services in Kuwait
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
            foreach ($locations as $locationItem) {
                if (is_array($locationItem)) {
                    $name = $locationItem['name'] ?? $locationItem['Name'] ?? 'Unknown';
                    $address = $locationItem['address'] ?? $locationItem['Address'] ?? 'Address not available';
                    $phone = $locationItem['phone'] ?? $locationItem['Phone'] ?? $locationItem['phone_number'] ?? null;
                    
                    // Generate Google Maps URL for this location
                    $mapsQuery = urlencode($address . ', ' . $location . ', Kuwait');
                    $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query=" . $mapsQuery;
                    
                    $formattedResults[] = [
                        'name' => $name,
                        'address' => $address,
                        'phone' => $phone,
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
