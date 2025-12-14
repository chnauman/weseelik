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
        
        // Check if this is a "more" request
        $isMoreRequest = $this->isMoreRequest($userMessage);
        
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
        
        // Get stored search state if this is a "more" request
        $searchState = null;
        if ($isMoreRequest) {
            $cacheKey = 'garage_search_state_' . $conversation->id;
            $searchState = Cache::get($cacheKey);
            
            // If "more" requested but no stored state, it's not a valid "more" request
            // Let the AI handle it as a new search
            if (!$searchState) {
                $isMoreRequest = false;
            }
        } else {
            // Clear search state for new searches (not "more" requests)
            $cacheKey = 'garage_search_state_' . $conversation->id;
            Cache::forget($cacheKey);
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
            ->limit(60)
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
                
                FUNCTION SELECTION - CRITICAL RULES:
                - CRITICAL: You MUST use findExternalService (NOT findGarage) for the following services: petrol stations, gas stations, fuel stations, ATMs, restaurants, cafes, hotels, shopping malls, shopping centers, hospitals, clinics, pharmacies, parking lots, car rentals, car insurance companies, gyms, supermarkets, grocery stores, banks, or ANY other business/service that is NOT a garage/automotive repair shop. These services are NEVER in the garage database, so you MUST use findExternalService immediately when users ask for them.
                - For garage-related queries ONLY: If the user asks for automotive repair services, car maintenance, vehicle services, or anything related to fixing/maintaining vehicles that matches the available database services/tags/categories/specializations, use the findGarage function.
                - Examples: 
                  * \"petrol station in Salmiya\" → MUST use findExternalService (NOT findGarage)
                  * \"ATM near me\" → MUST use findExternalService (NOT findGarage)
                  * \"restaurant in Kuwait City\" → MUST use findExternalService (NOT findGarage)
                  * \"car AC repair in Hawalli\" → use findGarage (this is a garage service)
                  * \"mechanic for Toyota\" → use findGarage (this is a garage service)
                - Always ask for the location/area if it's missing when using findExternalService.
                - The findExternalService function works ONLY for location-based services in Kuwait region - use it ONLY when a user asks for finding a specific location or business.
                
                RESPONSE FORMATTING:
                - CRITICAL: When garage results are found (via findGarage, listGarages, or getGarageDetails), your response MUST be EXTREMELY MINIMAL. DO NOT list any garage names, addresses, phone numbers, or any garage details in your message. DO NOT format garages as numbered lists. DO NOT include any garage information in your response. Simply say something brief like 'Here are the garages I found in [location]:' or 'Found these garages for you:' and STOP. The garage details are provided separately in a structured format, so you must NEVER include them in your message text. If you see garage results in the tool response, acknowledge with ONLY a brief confirmation sentence - nothing more.
                - PAGINATION: If the user asks for more garages (e.g., 'show more', 'next', 'more garages', 'load more', 'I need more options'), you MUST call the findGarage function again with the EXACT SAME parameters from the previous search. The system will automatically exclude already-shown garages and return the next batch. When showing more results, keep your response minimal as above.
                - When displaying results from findExternalService, format them as a numbered list in this exact format (blank line after business name):
                  1. [Business Name](google_maps_url) - Make the business name clickable as a link that opens Google Maps
                  Address: Street name, Area
                  Phone: +965 XXXX XXXX (omit Phone line entirely if phone is null or not available)
    
                  (Continue numbering for all results, each result on separate lines)
                  IMPORTANT: Always format business names as markdown links using the format [Business Name](google_maps_url) where google_maps_url is provided in the results. This makes the business name clickable and opens Google Maps for that location.
                - STRICT BOUNDARIES: You are ONLY a location finder chatbot for Kuwait. You MUST decline and politely redirect any queries that are NOT related to finding locations or businesses in Kuwait. This includes:
                  * Jokes, humor, or entertainment requests
                  * General knowledge questions
                  * Weather, news, or current events
                  * Personal advice or conversations
                  * Non-location related queries of any kind
                - When declining non-location queries, politely say: 'I'm a location finder chatbot for Kuwait. I can only help you find garages or other businesses and services in Kuwait. How can I help you find a location?'
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
                    'description' => 'Find location-based services and businesses in Kuwait ONLY. CRITICAL: Use this function (NOT findGarage) when users ask for: petrol stations, gas stations, fuel stations, ATMs, restaurants, cafes, hotels, shopping malls, hospitals, clinics, pharmacies, parking lots, car rentals, car insurance companies, gyms, supermarkets, banks, or ANY business/service that is NOT a garage/automotive repair shop. These services are NEVER in the garage database. DO NOT use findGarage for these queries. DO NOT use this function for non-location queries like jokes, general knowledge, weather, news, or any conversation that is not about finding a location in Kuwait.',
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
        
        // Collect garage data from garage-related tool calls
        $garagesData = [];
        
        // Track if we got "no_more_results" status
        $noMoreResults = false;
        
        // Track if external service results were found (to prevent garage error messages)
        $hasExternalServiceResults = false;

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

                // If this is a "more" request and we have stored state, use stored params
                if ($isMoreRequest && $searchState && $toolName === 'findGarage') {
                    $args = $searchState['search_params'];
                }
                
                $result = $this->dispatchTool($toolName, $args, $isMoreRequest ? $searchState : null);

                // Track external service results
                if ($toolName === 'findExternalService' && is_array($result)) {
                    if (isset($result['status']) && $result['status'] === 'success' && !empty($result['results'])) {
                        $hasExternalServiceResults = true;
                    }
                }
                
                // Extract garage data from garage-related tool results
                if (in_array($toolName, ['findGarage', 'listGarages', 'getGarageDetails']) && is_array($result)) {
                    // Handle "no_more_results" status - clear search state
                    if (isset($result['status']) && $result['status'] === 'no_more_results') {
                        $cacheKey = 'garage_search_state_' . $conversation->id;
                        Cache::forget($cacheKey);
                        $noMoreResults = true;
                        // Message will be set at the end based on noMoreResults flag
                    } else {
                        $extractedGarages = $this->extractGarageDataFromToolResult($result, $toolName);
                        if (!empty($extractedGarages)) {
                            $garagesData = array_merge($garagesData, $extractedGarages);
                            
                            // Store search state for pagination (only for findGarage with success status)
                            if ($toolName === 'findGarage' && isset($result['status']) && $result['status'] === 'success') {
                                // If this is a "more" request, update offset in cache
                                if ($isMoreRequest && $searchState && isset($result['_new_offset'])) {
                                    $cacheKey = 'garage_search_state_' . $conversation->id;
                                    $searchState['current_offset'] = $result['_new_offset'];
                                    Cache::put($cacheKey, $searchState, now()->addHour());
                                } elseif (!$isMoreRequest && isset($result['_all_garages'])) {
                                    // New search - store all garages with initial offset (all shown)
                                    $this->storeSearchState($conversation->id, $args, $result['_all_garages'], $result['_offset'] ?? count($result['_all_garages']));
                                }
                            }
                        }
                    }
                }

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
            
            // If no more results, use the message from the tool result
            if ($noMoreResults && empty($garagesData)) {
                // Message already set from tool result
            } elseif (!empty($garagesData)) {
                // If garages were found, ensure the message is minimal
                $assistantText = $this->ensureMinimalGarageMessage($assistantText, $garagesData);
            }
            
            // Check if second response also has tool calls (unlikely but possible)
            $secondToolCalls = $second->choices[0]->message->toolCalls
                ?? ($second->choices[0]->message->tool_calls ?? []);
            
            if (!empty($secondToolCalls)) {
                foreach ($secondToolCalls as $tc) {
                    $toolName = $tc->function->name ?? '';
                    $argsJson = $tc->function->arguments ?? '{}';
                    $args = json_decode($argsJson, true);
                    if (!is_array($args)) {
                        $args = [];
                    }

                    // If this is a "more" request and we have stored state, use stored params
                    if ($isMoreRequest && $searchState && $toolName === 'findGarage') {
                        $args = $searchState['search_params'];
                    }
                    
                    $result = $this->dispatchTool($toolName, $args, $isMoreRequest ? $searchState : null);

                    // Track external service results
                    if ($toolName === 'findExternalService' && is_array($result)) {
                        if (isset($result['status']) && $result['status'] === 'success' && !empty($result['results'])) {
                            $hasExternalServiceResults = true;
                        }
                    }

                    // Extract garage data from garage-related tool results
                    if (in_array($toolName, ['findGarage', 'listGarages', 'getGarageDetails']) && is_array($result)) {
                        // Handle "no_more_results" status - clear search state
                        if (isset($result['status']) && $result['status'] === 'no_more_results') {
                            $cacheKey = 'garage_search_state_' . $conversation->id;
                            Cache::forget($cacheKey);
                            $noMoreResults = true;
                            // Message will be set at the end based on noMoreResults flag
                        } else {
                            $extractedGarages = $this->extractGarageDataFromToolResult($result, $toolName);
                            if (!empty($extractedGarages)) {
                                $garagesData = array_merge($garagesData, $extractedGarages);
                                
                                // Store search state for pagination (only for findGarage with success status)
                                if ($toolName === 'findGarage' && isset($result['status']) && $result['status'] === 'success') {
                                    // If this is a "more" request, update offset in cache
                                    if ($isMoreRequest && $searchState && isset($result['results'])) {
                                        $newOffset = ($searchState['current_offset'] ?? 0) + count($result['results']);
                                        $cacheKey = 'garage_search_state_' . $conversation->id;
                                        $searchState['current_offset'] = $newOffset;
                                        Cache::put($cacheKey, $searchState, now()->addHour());
                                    } elseif (!$isMoreRequest && isset($result['_all_garages'])) {
                                        // New search - store all garages with initial offset
                                        $this->storeSearchState($conversation->id, $args, $result['_all_garages'], $result['_offset'] ?? 10);
                                    }
                                }
                            }
                        }
                    }

                    // Extract lat/lng from tool results
                    if (is_array($result)) {
                        $tempLat = null;
                        $tempLng = null;
                        
                        // For findGarage, listGarages, findExternalService - extract from first result
                        if (isset($result['results']) && is_array($result['results']) && !empty($result['results'])) {
                            $firstResult = $result['results'][0];
                            if (isset($firstResult['latitude']) && isset($firstResult['longitude'])) {
                                $latVal = $firstResult['latitude'];
                                $lngVal = $firstResult['longitude'];
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
                
                // If there were tool calls, make one more call to get final response
                $third = OpenAI::chat()->create([
                    'model' => $model,
                    'messages' => $history,
                    'temperature' => $temperature,
                    'tools' => $tools,
                    'tool_choice' => 'auto',
                ]);
                
                $assistantText = $third->choices[0]->message->content ?? '';
                $tokensUsed += $third->usage->totalTokens ?? 0;
                
                // If no more results, use the message from the tool result
                if ($noMoreResults && empty($garagesData)) {
                    // Message already set from tool result
                } elseif (!empty($garagesData)) {
                    // If garages were found, ensure the message is minimal
                    $assistantText = $this->ensureMinimalGarageMessage($assistantText, $garagesData);
                }
            }
        }
        
        // Final check: handle message based on results
        // Don't override messages if external service results were found
        if ($hasExternalServiceResults) {
            // External service results found - keep the AI's message as is
            // Don't apply garage-specific message overrides
        } elseif ($noMoreResults && empty($garagesData)) {
            // Override AI message with the correct "no more results" message
            $assistantText = 'All matching garages for your search have been provided to you.';
        } elseif (!empty($garagesData)) {
            // If garages were found, ensure the message is minimal
            $assistantText = $this->ensureMinimalGarageMessage($assistantText, $garagesData);
        } elseif (empty($garagesData)) {
            // If no garages and not a "no_more_results" case, check if message incorrectly says garages were found
            $messageLower = strtolower($assistantText ?? '');
            $garageFoundPhrases = [
                'here are', 'found these', 'here are some', 'here are the',
                'found garages', 'garages found', 'here are more'
            ];
            foreach ($garageFoundPhrases as $phrase) {
                if (strpos($messageLower, $phrase) !== false) {
                    // Override incorrect message
                    $assistantText = 'No more garages found matching your criteria.';
                    break;
                }
            }
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
            'garage' => !empty($garagesData) ? $garagesData : null,
            'history' => $history,
        ]);
    }

    private function dispatchTool(string $name, array $args, ?array $searchState = null)
    {
        switch ($name) {
            case 'findGarage':
                return $this->toolFindGarage($args, $searchState);
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

    /**
     * Extract and format garage data from tool results
     * 
     * @param array $result Tool result array
     * @param string $toolName Name of the tool that was called
     * @return array Formatted garage data array
     */
    private function extractGarageDataFromToolResult(array $result, string $toolName): array
    {
        $garages = [];

        // Handle different tool result structures
        if ($toolName === 'getGarageDetails') {
            // getGarageDetails returns a single garage in 'result' key
            if (isset($result['status']) && $result['status'] === 'success' && isset($result['result'])) {
                $garage = $result['result'];
                $formatted = $this->formatGarageData($garage);
                if ($formatted) {
                    $garages[] = $formatted;
                }
            }
        } else {
            // findGarage and listGarages return multiple garages in 'results' key
            if (isset($result['status']) && $result['status'] === 'success' && isset($result['results']) && is_array($result['results'])) {
                foreach ($result['results'] as $garage) {
                    $formatted = $this->formatGarageData($garage);
                    if ($formatted) {
                        $garages[] = $formatted;
                    }
                }
            }
        }

        return $garages;
    }

    /**
     * Format a single garage data to consistent structure
     * 
     * @param array $garage Raw garage data
     * @return array|null Formatted garage data or null if invalid
     */
    private function formatGarageData(array $garage): ?array
    {
        // Ensure required fields exist
        if (!isset($garage['id']) || !isset($garage['title'])) {
            return null;
        }

        return [
            'id' => $garage['id'],
            'title' => $this->getPreferredValueFromArray($garage['title'] ?? null, $garage['title_ar'] ?? null),
            'address' => $garage['address'] ?? null,
            'contact_no' => $garage['contact_no'] ?? null,
            'maps_share_link' => $garage['maps_share_link'] ?? null,
            'image' => $garage['image'] ?? null,
            'area' => $garage['area'] ?? null,
            'city' => $garage['city'] ?? null,
            'rating' => $garage['rating'] ?? null,
            'no_of_visits' => $garage['no_of_visits'] ?? null,
            'description' => $this->getPreferredValueFromArray($garage['description'] ?? null, $garage['description_ar'] ?? null),
            'block' => $garage['block'] ?? null,
            'governate' => $garage['governate'] ?? null,
            'latitude' => $garage['latitude'] ?? null,
            'longitude' => $garage['longitude'] ?? null,
            'home_service' => $garage['home_service'] ?? false,
            'pickup_service' => $garage['pickup_service'] ?? false,
            'no_of_likes' => $garage['no_of_likes'] ?? null,
        ];
    }

    /**
     * Ensure the message is minimal when garages are found
     * If the message contains garage details (names, addresses, phone numbers), replace it with a minimal confirmation
     * 
     * @param string $message The assistant's message
     * @param array $garagesData Array of garage data
     * @return string Minimal message
     */
    private function ensureMinimalGarageMessage(string $message, array $garagesData): string
    {
        if (empty($message) || empty($garagesData)) {
            return $message;
        }

        $messageLower = strtolower($message);
        
        // Check if message contains garage details (names, addresses, phone numbers, numbered lists)
        $containsGarageDetails = false;
        
        // Check for numbered lists (1., 2., etc.) which indicate garage listing
        if (preg_match('/\d+\.\s*\[?[^\]]+\]?/i', $message)) {
            $containsGarageDetails = true;
        }
        
        // Check for patterns like "Address:" or "Phone:" which indicate detailed listing
        if (preg_match('/(Address|Phone|Contact):/i', $message)) {
            $containsGarageDetails = true;
        }
        
        // Check if message is too long (more than 100 characters suggests detailed listing)
        if (mb_strlen($message) > 100) {
            // But allow if it's just a simple sentence without garage details
            $hasMultipleLines = substr_count($message, "\n") > 2;
            if ($hasMultipleLines) {
                $containsGarageDetails = true;
            }
        }
        
        // Check if message contains any garage titles
        foreach ($garagesData as $garage) {
            if (!empty($garage['title'])) {
                $title = $garage['title'];
                // Check if message contains the garage title (or significant portion of it)
                if (mb_strlen($title) > 10 && mb_strpos($message, mb_substr($title, 0, 20)) !== false) {
                    $containsGarageDetails = true;
                    break;
                }
            }
            
            // Check for phone numbers in message
            if (!empty($garage['contact_no'])) {
                $phone = $garage['contact_no'];
                // Check if phone number appears in message (with or without formatting)
                $phoneFormatted = preg_replace('/\D/', '', $phone);
                $messagePhoneOnly = preg_replace('/\D/', '', $message);
                if (strpos($messagePhoneOnly, $phoneFormatted) !== false) {
                    $containsGarageDetails = true;
                    break;
                }
            }
            
            // Check for addresses
            if (!empty($garage['address'])) {
                $address = $garage['address'];
                // Check if address appears in message (check for significant portion)
                $addressParts = explode(',', $address);
                if (!empty($addressParts[0]) && mb_strlen($addressParts[0]) > 5) {
                    if (mb_stripos($message, trim($addressParts[0])) !== false) {
                        $containsGarageDetails = true;
                        break;
                    }
                }
            }
        }
        
        // If message contains garage details, replace with minimal message
        if ($containsGarageDetails) {
            // Try to extract location from first garage if available
            $location = null;
            if (!empty($garagesData[0])) {
                $firstGarage = $garagesData[0];
                $location = $firstGarage['area'] ?? $firstGarage['city'] ?? null;
                
                // Try to extract location from address
                if (!$location && !empty($firstGarage['address'])) {
                    $addressParts = explode(',', $firstGarage['address']);
                    if (count($addressParts) > 1) {
                        $location = trim($addressParts[count($addressParts) - 2] ?? '');
                    }
                }
            }
            
            if ($location) {
                return "Here are some garages I found in {$location}.";
            } else {
                return "Here are the garages I found.";
            }
        }
        
        return $message;
    }

    /**
     * Check if the user message is a "more" request
     */
    private function isMoreRequest(string $message): bool
    {
        $messageLower = strtolower(trim($message));
        $moreKeywords = [
            'show more', 'more garages', 'load more', 'next', 'more results',
            'i need more', 'give me more', 'more options', 'more please',
            'show me more', 'get more', 'fetch more', 'bring more'
        ];
        
        foreach ($moreKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Store search state for pagination - stores ALL garages and tracks offset
     */
    private function storeSearchState(int $conversationId, array $searchParams, array $allGarages, int $offset = 0): void
    {
        $cacheKey = 'garage_search_state_' . $conversationId;
        
        // Store search state with ALL garages and current offset
        $state = [
            'conversation_id' => $conversationId, // Store for reference
            'search_params' => $searchParams,
            'all_garages' => $allGarages, // Store all garage data
            'total_count' => count($allGarages),
            'current_offset' => $offset, // Track how many have been shown
            'last_updated' => now()->toDateTimeString(),
        ];
        
        Cache::put($cacheKey, $state, now()->addHour());
        
        Log::info('Storing search state with all garages', [
            'conversation_id' => $conversationId,
            'total_garages' => count($allGarages),
            'current_offset' => $offset
        ]);
    }

    private function toolFindGarage(array $args, ?array $searchState = null)
    {
        // If this is a "more" request and we have stored garages, return next batch
        if ($searchState && isset($searchState['all_garages']) && is_array($searchState['all_garages'])) {
            $allGarages = $searchState['all_garages'];
            $currentOffset = $searchState['current_offset'] ?? 0;
            $perPage = 10;
            
            // Get next batch
            $nextBatch = array_slice($allGarages, $currentOffset, $perPage);
            
            if (empty($nextBatch)) {
                // No more garages to show
                return [
                    'status' => 'no_more_results',
                    'message' => 'All matching garages for your search have been provided to you.',
                    'results' => [],
                ];
            }
            
            // Return result with offset info for cache update
            return [
                'status' => 'success',
                'action' => 'find_garage',
                'count' => count($nextBatch),
                'results' => $nextBatch,
                '_new_offset' => $currentOffset + count($nextBatch), // For cache update
            ];
        }
        
        // This is a new search - fetch ALL matching garages
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

        // Filter by location using smart area matching
        if ($location) {
            $matchedAreas = $this->matchAreasFromUserRequest($location);
            
            if (!empty($matchedAreas)) {
                // Use matched areas for precise search
                // Return garages from all matched areas
                $query->where(function ($q) use ($matchedAreas) {
                    $firstArea = true;
                    foreach ($matchedAreas as $matchedArea) {
                        if ($firstArea) {
                            $q->where(function ($subQ) use ($matchedArea) {
                                $subQ->where('area', $matchedArea)
                                    ->orWhere('city', $matchedArea)
                                    ->orWhere('governate', $matchedArea);
                            });
                            $firstArea = false;
                        } else {
                            $q->orWhere(function ($subQ) use ($matchedArea) {
                                $subQ->where('area', $matchedArea)
                                    ->orWhere('city', $matchedArea)
                                    ->orWhere('governate', $matchedArea);
                            });
                        }
                    }
                });
            } else {
                // Fallback to original LIKE search if no match found
                $query->where(function ($q) use ($location) {
                    $q->where('area', 'like', "%{$location}%")
                        ->orWhere('city', 'like', "%{$location}%")
                        ->orWhere('address', 'like', "%{$location}%")
                        ->orWhere('governate', 'like', "%{$location}%");
                });
            }
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

        // Fetch ALL matching garages (no limit)
        $garages = $query->with(['tags', 'services', 'specializations', 'categories'])
            ->orderBy('rating', 'desc')
            ->orderBy('no_of_visits', 'desc')
            ->get();

        // If no garages found
        if ($garages->isEmpty()) {
            return [
                'status' => 'not_found',
                'message' => 'No garages found matching your criteria.',
                'results' => [],
            ];
        }

        $geocodingApiKey = env('GOOGLE_MAPS_API_KEY');
        // Format ALL garages
        $allResults = $garages->map(function ($garage, $index) use ($geocodingApiKey) {
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
                'title' => $this->getPreferredValue($garage->title, $garage->title_ar),
                'description' => $this->getPreferredValue($garage->description, $garage->description_ar),
                'address' => $garage->address,
                'area' => $garage->area,
                'city' => $garage->city,
                'contact_no' => $garage->contact_no,
                'rating' => $garage->rating,
                'no_of_visits' => $garage->no_of_visits,
                'home_service' => $garage->home_service ?? false,
                'pickup_service' => $garage->pickup_service ?? false,
                'maps_share_link' => $garage->maps_share_link,
                'image' => $garage->getFirstMediaUrl('logo') ?: $garage->getFirstMediaUrl('storefront_image'),
                'latitude' => $lat !== null && $lat !== '' ? (is_numeric($lat) ? (float)$lat : $lat) : null,
                'longitude' => $lng !== null && $lng !== '' ? (is_numeric($lng) ? (float)$lng : $lng) : null,
                'services' => $garage->services->map(fn($s) => $s->english_title ?? $s->title)->toArray(),
                'tags' => $garage->tags->map(fn($t) => $t->english_title ?? $t->title)->toArray(),
                'specializations' => $garage->specializations->map(fn($sp) => $sp->english_title ?? $sp->title)->toArray(),
            ];
        })->toArray();

        // Return ALL matching garages in the first response
        // Store all garages in cache for pagination (will be stored by handleChat after tool call)
        
        return [
            'status' => 'success',
            'action' => 'find_garage',
            'count' => count($allResults),
            'total_count' => count($allResults),
            'results' => $allResults, // Return ALL garages, not just first 10
            '_all_garages' => $allResults, // Internal: all garages for caching
            '_offset' => count($allResults), // Internal: current offset (all shown)
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
                'title' => $this->getPreferredValue($garage->title, $garage->title_ar),
                'description' => $this->getPreferredValue($garage->description, $garage->description_ar),
                'address' => $garage->address,
                'area' => $garage->area,
                'city' => $garage->city,
                'contact_no' => $garage->contact_no,
                'rating' => $garage->rating,
                'no_of_visits' => $garage->no_of_visits,
                'maps_share_link' => $garage->maps_share_link,
                'image' => $garage->getFirstMediaUrl('logo') ?: $garage->getFirstMediaUrl('storefront_image'),
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
                'title' => $this->getPreferredValue($garage->title, $garage->title_ar),
                'description' => $this->getPreferredValue($garage->description, $garage->description_ar),
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
                'image' => $garage->getFirstMediaUrl('logo') ?: $garage->getFirstMediaUrl('storefront_image'),
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
            'garage_title' => $this->getPreferredValue($garage->title, $garage->title_ar),
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

        // Validate that this is a legitimate location-based query
        // Reject non-location queries (jokes, general knowledge, etc.)
        $nonLocationKeywords = [
            'joke', 'jokes', 'funny', 'humor', 'entertainment', 'story', 'stories',
            'weather', 'news', 'advice', 'opinion', 'tell me about', 'what is', 'who is',
            'how to', 'explain', 'define', 'meaning', 'chat', 'talk', 'conversation'
        ];
        
        $serviceTypeLower = strtolower($serviceType);
        foreach ($nonLocationKeywords as $keyword) {
            if (strpos($serviceTypeLower, $keyword) !== false) {
                return [
                    'status' => 'error',
                    'message' => 'I am a location finder chatbot for Kuwait only. I can only help you find garages or other businesses and services in Kuwait. Please ask me to find a specific location or business in Kuwait.',
                ];
            }
        }

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
     * Get preferred value (English first, Arabic fallback)
     * For model objects
     *
     * @param string|null $englishValue
     * @param string|null $arabicValue
     * @return string|null
     */
    private function getPreferredValue(?string $englishValue, ?string $arabicValue): ?string
    {
        // Prefer English if available and not empty
        if (!empty(trim($englishValue ?? ''))) {
            return $englishValue;
        }
        
        // Fallback to Arabic if English is not available
        if (!empty(trim($arabicValue ?? ''))) {
            return $arabicValue;
        }
        
        return null;
    }

    /**
     * Get preferred value from array (English first, Arabic fallback)
     * For array data
     *
     * @param string|null $englishValue
     * @param string|null $arabicValue
     * @return string|null
     */
    private function getPreferredValueFromArray(?string $englishValue, ?string $arabicValue): ?string
    {
        return $this->getPreferredValue($englishValue, $arabicValue);
    }

    /**
     * Get unique areas from database (cached)
     * Returns array of unique area, city, and governate values
     *
     * @return array
     */
    private function getUniqueAreas(): array
    {
        $cacheKey = 'unique_garage_areas';
        
        return Cache::remember($cacheKey, now()->addDays(7), function () {
            $areas = Garage::where('status', 'approved')
                ->whereNull('deleted_at')
                ->whereNotNull('area')
                ->where('area', '!=', '')
                ->distinct()
                ->pluck('area')
                ->filter()
                ->map(fn($area) => trim($area))
                ->filter(fn($area) => !empty($area))
                ->unique()
                ->values()
                ->toArray();

            $cities = Garage::where('status', 'approved')
                ->whereNull('deleted_at')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()
                ->pluck('city')
                ->filter()
                ->map(fn($city) => trim($city))
                ->filter(fn($city) => !empty($city))
                ->unique()
                ->values()
                ->toArray();

            $governates = Garage::where('status', 'approved')
                ->whereNull('deleted_at')
                ->whereNotNull('governate')
                ->where('governate', '!=', '')
                ->distinct()
                ->pluck('governate')
                ->filter()
                ->map(fn($governate) => trim($governate))
                ->filter(fn($governate) => !empty($governate))
                ->unique()
                ->values()
                ->toArray();

            // Combine and return unique values
            return array_values(array_unique(array_merge($areas, $cities, $governates)));
        });
    }

    /**
     * Match user's area request to database areas
     * Tries exact match first, then uses OpenAI for fuzzy matching
     *
     * @param string $userRequest The location/area mentioned by user
     * @return array Array of matched area names from database
     */
    private function matchAreasFromUserRequest(string $userRequest): array
    {
        if (empty(trim($userRequest))) {
            return [];
        }

        $userRequest = trim($userRequest);
        $uniqueAreas = $this->getUniqueAreas();

        if (empty($uniqueAreas)) {
            return [];
        }

        // Step 1: Try exact match (case-insensitive)
        $exactMatches = [];
        $userRequestLower = strtolower($userRequest);
        
        foreach ($uniqueAreas as $area) {
            $areaLower = strtolower(trim($area));
            if ($areaLower === $userRequestLower) {
                $exactMatches[] = $area;
            }
        }

        if (!empty($exactMatches)) {
            return array_unique($exactMatches);
        }

        // Step 2: Try fuzzy match (remove spaces, special chars for comparison)
        $normalize = function($str) {
            return preg_replace('/[^a-z0-9]/', '', strtolower($str));
        };
        
        $userRequestNormalized = $normalize($userRequest);
        $fuzzyMatches = [];
        
        foreach ($uniqueAreas as $area) {
            $areaNormalized = $normalize($area);
            if ($areaNormalized === $userRequestNormalized) {
                $fuzzyMatches[] = $area;
            }
        }

        if (!empty($fuzzyMatches)) {
            return array_unique($fuzzyMatches);
        }

        // Step 3: Use OpenAI to match user request to database areas
        try {
            $matchedAreas = $this->matchAreasWithOpenAI($userRequest, $uniqueAreas);
            if (!empty($matchedAreas)) {
                return array_unique($matchedAreas);
            }
        } catch (\Exception $e) {
            Log::warning("OpenAI area matching failed: " . $e->getMessage());
        }

        // Step 4: Fallback to partial match (contains)
        $partialMatches = [];
        foreach ($uniqueAreas as $area) {
            $areaLower = strtolower($area);
            if (strpos($areaLower, $userRequestLower) !== false || strpos($userRequestLower, $areaLower) !== false) {
                $partialMatches[] = $area;
            }
        }

        return array_unique($partialMatches);
    }

    /**
     * Use OpenAI to match user request to database areas
     * Handles spelling differences and variations
     *
     * @param string $userRequest
     * @param array $availableAreas
     * @return array
     */
    private function matchAreasWithOpenAI(string $userRequest, array $availableAreas): array
    {
        $apiKey = env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            return [];
        }

        $model = env('OPENAI_MODEL', 'gpt-4o-mini');
        
        // Limit areas list to avoid token limits (take first 200)
        $areasToCheck = array_slice($availableAreas, 0, 200);
        $areasList = implode(', ', $areasToCheck);

        $prompt = "The user is asking for garages in a specific area/location in Kuwait. 

Available areas in the database (exact names as stored):
{$areasList}

User's request: \"{$userRequest}\"

Your task: Match the user's request to one or more areas from the database list above. Consider:
- Spelling variations (e.g., 'al ahmedi' vs 'alahmadi' vs 'Al Ahmadi')
- Common abbreviations
- Alternative names
- Typos or misspellings

Return a JSON object with a 'matched_areas' key containing an array of matched area names exactly as they appear in the database list above. If no match is found, return an empty array.

Example response format:
{\"matched_areas\": [\"Salmiya\", \"Hawalli\"]}

Return ONLY the JSON object, no additional text or explanation.";

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a location matching assistant. Match user location requests to exact database area names. Return only valid JSON objects with a matched_areas array.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1, // Low temperature for consistent matching
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            
            if (empty($content)) {
                return [];
            }

            // Parse JSON response
            $data = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                // Extract matched_areas from response
                $matchedAreas = $data['matched_areas'] ?? [];
                
                if (!is_array($matchedAreas)) {
                    return [];
                }

                // Validate that all matched areas exist in our database
                $validatedAreas = [];
                foreach ($matchedAreas as $area) {
                    if (is_string($area) && in_array($area, $availableAreas)) {
                        $validatedAreas[] = $area;
                    }
                }

                return $validatedAreas;
            }

            // Fallback: try to extract matched_areas from text response
            if (preg_match('/"matched_areas"\s*:\s*\[(.*?)\]/', $content, $matches)) {
                $arrayContent = '[' . $matches[1] . ']';
                $data = json_decode($arrayContent, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    $validatedAreas = [];
                    foreach ($data as $area) {
                        if (is_string($area) && in_array($area, $availableAreas)) {
                            $validatedAreas[] = $area;
                        }
                    }
                    return $validatedAreas;
                }
            }

        } catch (\Exception $e) {
            Log::error("OpenAI area matching error: " . $e->getMessage());
        }

        return [];
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
            // Prioritize formatted_address from place data, then vicinity
            $address = $place['formatted_address'] ?? ($place['vicinity'] ?? null);
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
                        // Always use formatted_address from details if available (most accurate)
                        if (isset($details['formatted_address']) && !empty($details['formatted_address'])) {
                            $address = $details['formatted_address'];
                        } elseif (isset($details['vicinity']) && !empty($details['vicinity']) && empty($address)) {
                            $address = $details['vicinity'];
                        }
                    }
                } catch (\Exception $e) {
                    // Continue without details if API call fails
                }
            }
            
            // Generate Google Maps URL - prioritize name+address (most reliable), then place_id, then coordinates
            $lat = $place['geometry']['location']['lat'] ?? null;
            $lng = $place['geometry']['location']['lng'] ?? null;
            $googleMapsUrl = null;
            
            // Priority 1: Use place name + address (most reliable for opening exact location)
            if (!empty($name) && $name !== 'Unknown') {
                $encodedName = urlencode($name);
                $encodedAddress = '';
                
                // Use formatted address if available (most accurate)
                if (!empty($address) && trim($address) !== '') {
                    $encodedAddress = urlencode($address);
                }
                
                if ($encodedAddress) {
                    // Use name + address for precise search (this is the most accurate format)
                    $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$encodedName},{$encodedAddress}";
                } else {
                    // Just use name with Kuwait if no address available
                    $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$encodedName},Kuwait";
                }
            }
            // Priority 2: Use place_id with name (alternative format) - more accurate than coordinates
            if (empty($googleMapsUrl) && !empty($placeId) && !empty($name) && $name !== 'Unknown') {
                $encodedPlaceId = urlencode($placeId);
                $encodedName = urlencode($name);
                // Try format with both query and query_place_id (very accurate)
                $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$encodedName}&query_place_id={$encodedPlaceId}";
            }
            // Priority 2b: Alternative place_id format (direct place URL) - still accurate
            if (empty($googleMapsUrl) && !empty($placeId)) {
                $encodedPlaceId = urlencode($placeId);
                // Direct place format
                $googleMapsUrl = "https://www.google.com/maps/place/?q=place_id:{$encodedPlaceId}";
            }
            // Priority 3: Fallback to coordinates if nothing else available (least accurate)
            if (empty($googleMapsUrl) && !empty($lat) && !empty($lng)) {
                $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
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
                   "fields=formatted_phone_number,international_phone_number,formatted_address,vicinity,opening_hours&" .
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
                        'content' => 'You are a location finder assistant for Kuwait ONLY. Your ONLY purpose is to provide accurate location information about businesses and services in Kuwait. You MUST respond ONLY with valid JSON object format containing a "locations" array. Do NOT provide jokes, general knowledge, or any information unrelated to location finding in Kuwait. If you cannot find specific location information, return an empty locations array.'
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
                    
                    // Generate Google Maps URL for this location - prioritize name+address (most reliable), then coordinates
                    $googleMapsUrl = null;
                    
                    // Priority 1: Use place name + address (most reliable for opening exact location)
                    if (!empty($name) && $name !== 'Unknown') {
                        $encodedName = urlencode($name);
                        $encodedAddress = '';
                        
                        // Build full address with location and Kuwait
                        if (!empty($address) && $address !== 'Address not available') {
                            $fullAddress = $address . ', ' . $location . ', Kuwait';
                            $encodedAddress = urlencode($fullAddress);
                        } else {
                            $encodedAddress = urlencode($location . ', Kuwait');
                        }
                        
                        if ($encodedAddress) {
                            // Use name + address for precise search
                            $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$encodedName},{$encodedAddress}";
                        } else {
                            // Just use name with Kuwait if no address available
                            $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$encodedName},Kuwait";
                        }
                    }
                    // Priority 2: Fallback to address only if no name
                    if (empty($googleMapsUrl) && !empty($address) && $address !== 'Address not available') {
                        $fullAddress = $address . ', ' . $location . ', Kuwait';
                        $mapsQuery = urlencode($fullAddress);
                        $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query=" . $mapsQuery;
                    }
                    // Priority 3: Fallback to coordinates if nothing else available
                    if (empty($googleMapsUrl) && !empty($lat) && !empty($lng)) {
                        $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
                    }
                    
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
