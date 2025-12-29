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
        
        // Check if this is a "more" request and extract number if specified
        $moreRequestResult = $this->isMoreRequest($userMessage);
        $isMoreRequest = $moreRequestResult['isMore'];
        $moreRequestLimit = $moreRequestResult['limit'] ?? null; // Number extracted from "3 more", "10 more", etc.
        
        // Check if user mentioned a new location (different from previous search)
        $hasNewLocation = $this->detectNewLocation($userMessage, $conversation->id);
        
        // Get stored search state if this is a "more" request
        $searchState = null;
        if ($isMoreRequest) {
            // For "more" requests, prioritize preserving search state
            // Only treat as new search if there's a CLEAR new location mention
            if ($hasNewLocation) {
                // User explicitly mentioned a different location - this is a new search
                $this->clearSearchState($conversation->id);
                $isMoreRequest = false;
            } else {
                // It's a "more" request with no new location - use previous search state
                $searchState = $this->getSearchState($conversation->id);
                if (!$searchState) {
                    // No previous search state found - can't do "more"
                    $isMoreRequest = false;
                } else {
                    // Store the limit override for this "more" request
                    if ($moreRequestLimit !== null) {
                        $searchState['_more_request_limit'] = $moreRequestLimit;
                    }
                }
            }
        } else {
            // Not a "more" request - clear search state for new searches
            // But only clear if there's actually a new location or it's a completely new query
            if ($hasNewLocation) {
                $this->clearSearchState($conversation->id);
            } else {
                // For regular queries, we'll store new search state after the search
                // Don't clear here - let the new search create its own state
            }
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

        // Get available tags, services, specializations, and categories
        $tags = $this->getDisplayTitles('tags');
        $specializations = $this->getDisplayTitles('specializations');
        $services = $this->getDisplayTitles('services');
        $categories = $this->getDisplayTitles('categories');

        // Get unique areas from database (cached) - show all areas for intelligent matching
        $uniqueAreas = $this->getUniqueAreas();
        $areas = !empty($uniqueAreas) ? implode(', ', $uniqueAreas) : ''; // Show all areas for better matching

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

        // Build previous search info for "more" requests
        $previousSearchInfo = '';
        if ($isMoreRequest) {
            // Try to get search state if not already retrieved
            if (!$searchState) {
                $searchState = $this->getSearchState($conversation->id);
            }
            
            // If we have search state, use it
            if ($searchState && isset($searchState['search_params'])) {
                $prevParams = $searchState['search_params'];
                $prevLocation = $prevParams['location'] ?? 'not specified';
                
                // Use the override limit if specified (e.g., "3 more", "10 more"), otherwise use original limit
                $limitToUse = $moreRequestLimit ?? ($prevParams['limit'] ?? 5);
                
                $additionalParams = [];
                if (isset($prevParams['selected_services']) && !empty($prevParams['selected_services'])) {
                    $additionalParams[] = "selected_services: " . json_encode($prevParams['selected_services']);
                }
                if (isset($prevParams['selected_tags']) && !empty($prevParams['selected_tags'])) {
                    $additionalParams[] = "selected_tags: " . json_encode($prevParams['selected_tags']);
                }
                if (isset($prevParams['selected_categories']) && !empty($prevParams['selected_categories'])) {
                    $additionalParams[] = "selected_categories: " . json_encode($prevParams['selected_categories']);
                }
                if (isset($prevParams['selected_specializations']) && !empty($prevParams['selected_specializations'])) {
                    $additionalParams[] = "selected_specializations: " . json_encode($prevParams['selected_specializations']);
                }
                
                $paramsList = !empty($additionalParams) ? "\n                " . implode("\n                ", $additionalParams) : "";
                $limitNote = $moreRequestLimit !== null ? " (user requested {$moreRequestLimit} more)" : "";
                
                $previousSearchInfo = "
                
                ═══════════════════════════════════════════════════════════════
                PREVIOUS SEARCH PARAMETERS - USE THESE EXACTLY (DO NOT ASK QUESTIONS):
                ═══════════════════════════════════════════════════════════════
                The user is asking for MORE results from the previous search in \"{$prevLocation}\".
                
                MANDATORY ACTION: Call findGarage IMMEDIATELY with these EXACT parameters:
                - location: \"{$prevLocation}\" (MUST USE THIS - DO NOT ASK FOR LOCATION)
                - limit: {$limitToUse}{$limitNote}{$paramsList}
                
                CRITICAL RULES:
                ❌ DO NOT ask for location - use \"{$prevLocation}\" from previous search
                ❌ DO NOT ask any questions - just call findGarage immediately
                ✅ Use the SAME location \"{$prevLocation}\" - it persists until user mentions a DIFFERENT location
                ✅ Only change location if user explicitly says a different location name (e.g., \"in al shuaikh\", \"in hawalli\")
                
                The user said \"more\" or \"find more\" - this means they want MORE results from the SAME search in \"{$prevLocation}\".
                ACTION REQUIRED: Call findGarage NOW with location=\"{$prevLocation}\". Do NOT ask questions.
                ═══════════════════════════════════════════════════════════════";
            } elseif ($isMoreRequest) {
                // Fallback: If search state is missing but it's a "more" request,
                // extract location from conversation history and instruct AI to use it
                $lastLocation = $this->extractLocationFromHistory($history);
                if ($lastLocation) {
                    $limitToUse = $moreRequestLimit ?? 5;
                    $previousSearchInfo = "
                
                ═══════════════════════════════════════════════════════════════
                USER REQUESTED MORE RESULTS - USE PREVIOUS LOCATION:
                ═══════════════════════════════════════════════════════════════
                The user said \"more\" or \"find more\" - they want MORE results from the PREVIOUS search.
                
                Based on conversation history, the previous location was: \"{$lastLocation}\"
                
                MANDATORY ACTION: Call findGarage IMMEDIATELY with:
                - location: \"{$lastLocation}\" (USE THIS - DO NOT ASK FOR LOCATION)
                - limit: {$limitToUse}
                
                CRITICAL: Do NOT ask \"Could you please provide the location\". Use \"{$lastLocation}\" from the previous search.
                ═══════════════════════════════════════════════════════════════";
                }
            }
        }

        array_unshift($history, [
            'role' => 'system',
            'content' => "
                You are a polite, human-like Garage Finder chatbot agent for the Kuwait Region.
                Your job is to help people find a garage based on their needs. Always greet the user warmly but casually.
                Be calm, concise, and human — usually reply in 1–2 lines, or rarely 3 short lines when it feels natural.

                You can use the following information to understand the user's intent and help find a garage:
                - Available Areas/Locations in Kuwait: $areas
                - Tags: $tags
                - Specializations: $specializations
                - Services: $services
                - Categories: $categories

                CRITICAL - INTELLIGENT AREA MATCHING:
                When the user mentions a location or area, you MUST intelligently match it to one of the available areas listed above. This is crucial for finding garages.
                
                Matching rules:
                1. Look for EXACT matches first (case-insensitive)
                2. Look for PARTIAL matches - if user says \"shuaikh\" or \"shuaikh area\", match it to \"shuwaikh industrial\" or \"shuwaikh\" if those exist in the list
                3. Handle spelling variations - \"shuaikh\" = \"shuwaikh\", \"salmiya\" = \"salmiyah\", etc.
                4. Handle area suffixes - if user says \"hawalli\" and list has \"hawalli industrial\" or \"hawalli residential\", use the one that best matches
                5. Handle common abbreviations and variations
                6. If multiple matches exist (e.g., \"shuwaikh\" and \"shuwaikh industrial\"), prefer the more specific one if the user's request is specific, otherwise use the broader match
                7. If you find a match (exact or partial), ALWAYS use the EXACT area name from the list above when calling findGarage function
                8. Only use the user's exact wording if NO match can be found in the list above
                
                Examples:
                - User: \"shuaikh area\" → Match to \"shuwaikh industrial\" or \"shuwaikh\" (if in list)
                - User: \"salmiya\" → Match to \"salmiya\" or \"salmiyah\" (if in list)
                - User: \"hawalli\" → Match to \"hawalli\", \"hawalli industrial\", or \"hawalli residential\" (whichever exists)
                - User: \"kuwait city\" → Match to \"kuwait city\" or similar variation in list

                CONTEXT AWARENESS - ABSOLUTE PRIORITY:
                STEP 1: ALWAYS check conversation history FIRST before asking ANY question. Look at ALL previous user messages.
                STEP 2: If location exists in conversation history OR current message → call findGarage IMMEDIATELY with that location.
                STEP 3: Service filters are OPTIONAL. If user says \"any\", \"all services\", \"everything\", \"just tell me any garage\" → proceed WITHOUT service filter.
                
                CRITICAL DECISION TREE:
                1. User provides NEW location (different from previous search) → This is a NEW search. Clear previous search state and call findGarage with new location.
                2. User says \"any\", \"all services\", \"everything\" → Call findGarage with location from context, no service filter. Do NOT ask questions.
                3. User says \"more\", \"find more\", \"show more\", \"3 more\", \"10 more\", etc. → Use previous search parameters (see PREVIOUS SEARCH PARAMETERS section if available). 
                   - If user specifies a number (e.g., \"3 more\", \"show 10 more\"), extract that number and pass it as 'limit' parameter
                   - Otherwise, use the previous limit or default to 5
                   - Call findGarage immediately with SAME location and parameters. Do NOT ask questions.
                4. Location missing AND not in history → Ask for location ONLY.
                
                PAGINATION RULES:
                - When user says \"more\" or \"show more\" → Return next batch using SAME location and filters from previous search
                - When user says \"3 more\" or \"10 more\" → Return that specific number using SAME location and filters
                - Location persists until user explicitly mentions a DIFFERENT location (e.g., if last search was \"al ahmadi\" and user says \"more\", use \"al ahmadi\" again)
                - Only start a NEW search when user explicitly mentions a different location
                
                MANDATORY RULES - NEVER VIOLATE:
                ❌ NEVER ask for location if it exists in conversation history
                ❌ NEVER ask for service if user said \"any\", \"all services\", \"everything\"
                ❌ NEVER ask questions when user says \"more\" or \"find more\"
                ❌ NEVER ask for service when location is provided - service is optional
                ✅ ALWAYS use location from conversation history if available
                ✅ ALWAYS proceed immediately when user provides valid responses like \"any\" or \"all services\"
                ✅ ALWAYS call findGarage when you have location (from message OR history)
                
                EXAMPLES:
                - User: \"find garages in al ahmadi\" → findGarage(location=\"al ahmadi\")
                - User: \"find more\" → findGarage with previous parameters (no questions)
                - User: \"al ahmadi\" → findGarage(location=\"al ahmadi\") immediately
                - User: \"any\" (after location mentioned) → findGarage(location from context, no service filter)
                - User: \"all services just tell me any garage\" → findGarage(location from context, no service filter)
                
                When the user describes a problem (e.g., 'my car's AC is not cooling'), intelligently map it to relevant tags, services, specializations, or categories, and pass them in the function call as arrays:
                - 'selected_tags': inferred issue tags
                - 'selected_specializations': relevant expertise or brand specialization
                - 'selected_services': relevant garage services
                - 'selected_categories': relevant garage category
                
                FUNCTION SELECTION - CRITICAL RULES:
                - CRITICAL: You MUST use findExternalService (NOT findGarage) for the following services: petrol stations, gas stations, fuel stations, ATMs, restaurants, cafes, hotels, shopping malls, shopping centers, hospitals, clinics, pharmacies, parking lots, car rentals, car insurance companies, gyms, supermarkets, grocery stores, banks, or ANY other business/service that is NOT a garage/automotive repair shop. These services are NEVER in the garage database, so you MUST use findExternalService immediately when users ask for them.
                - For garage-related queries ONLY: If the user asks for automotive repair services, car maintenance, vehicle services, or anything related to fixing/maintaining vehicles that matches the available database services/tags/categories/specializations, use the findGarage function.
                - Examples: 
                  * \"petrol station in Salmiya\" → MUST use findExternalService (NOT findGarage)
                  * \"ATM near me\" → MUST use findExternalService (NOT findGarage)
                  * \"restaurant in Kuwait City\" → MUST use findExternalService (NOT findGarage)
                  * \"car AC repair in Hawalli\" → use findGarage (this is a garage service)
                  * \"mechanic for Toyota\" → use findGarage (this is a garage service)
                - For findExternalService: Location is ALWAYS REQUIRED. Always ask for location first if user doesn't provide it.
                - The findExternalService function works ONLY for location-based services in Kuwait region - use it ONLY when a user asks for finding a specific location or business.
                
                RESPONSE FORMATTING:
                - CRITICAL: When garage results are found (via findGarage, listGarages, or getGarageDetails), your response MUST be EXTREMELY MINIMAL. DO NOT list any garage names, addresses, phone numbers, or any garage details in your message. DO NOT format garages as numbered lists. DO NOT include any garage information in your response. Simply say something brief like 'Here are the garages I found in [location]:' or 'Found these garages for you:' and STOP. The garage details are provided separately in a structured format, so you must NEVER include them in your message text. If you see garage results in the tool response, acknowledge with ONLY a brief confirmation sentence - nothing more.
                - RESULT LIMIT: By default, findGarage returns 5 garages. If the user specifically asks for a number (e.g., \"3 garages\", \"10 garages\", \"show me 7 garages\"), you MUST extract that number and pass it as the 'limit' parameter. If the user doesn't specify a number, omit the 'limit' parameter to use the default of 5.
                - PAGINATION - CRITICAL: When user asks for more (e.g., 'more', 'find more', 'show more', '3 more', '10 more', 'next'):
                  * If PREVIOUS SEARCH PARAMETERS section exists above → Use those EXACT parameters immediately (MANDATORY)
                  * If user specifies a number (e.g., \"3 more\", \"show 10 more\", \"find 7 more\"), extract that number and pass it as 'limit' parameter
                  * If PREVIOUS SEARCH PARAMETERS section doesn't exist → Look at conversation history to find the MOST RECENT location mentioned
                  * CRITICAL: The location from the previous search MUST be preserved. If user said \"find garages in salmiya\" and then \"find 7 more\", use \"salmiya\" again.
                  * IMPORTANT: Use the SAME location from previous search. Do NOT ask for location again. Location persists until user explicitly mentions a DIFFERENT location.
                  * Call findGarage IMMEDIATELY with those parameters (location from previous search, filters, and limit if specified)
                  * DO NOT ask any questions - just call the function
                  * Keep response minimal: \"Found these garages for you:\" or similar
                  * NEVER ask \"Could you please provide the location\" when user says \"more\" - use the previous location
                - NO RESULTS HANDLING: If no garages are found matching the user's criteria, politely inform them and ask if they would like to search in a different area. Example: \"I couldn't find any garages matching your criteria in [location]. Would you like to search in a different area?\"
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
                {$previousSearchInfo}
                "
        ]);

        //return response()->json($history);

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'findGarage',
                    'description' => 'Find a garage based on the user\'s provided details, filters, and preferences. Location is REQUIRED. Other filters (service, vehicle details) are optional.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => [
                                'type' => 'string',
                                'description' => 'REQUIRED: The location or area name where the user wants to find a garage. CRITICAL: You MUST intelligently match the user\'s location to one of the available areas listed in the system prompt. Handle spelling variations (e.g., "shuaikh" → "shuwaikh industrial"), partial matches, and area suffixes. Always use the EXACT area name from the available areas list when a match is found. Only use the user\'s exact wording if no match exists in the available areas list. Never call this function without a location.'
                            ],
                            'vehicle_brand' => [
                                'type' => 'string',
                                'description' => 'Optional: Brand of the vehicle, e.g., Toyota, Honda, Nissan, etc. If user says "any", "all", or doesn\'t specify, omit this parameter.'
                            ],
                            'vehicle_model' => [
                                'type' => 'string',
                                'description' => 'Optional: Model of the vehicle. If user says "any", "all", or doesn\'t specify, omit this parameter.'
                            ],
                            'vehicle_year' => [
                                'type' => 'string',
                                'description' => 'Optional: Manufacturing year of the vehicle. If user says "any", "all", or doesn\'t specify, omit this parameter.'
                            ],
                            'selected_tags' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ],
                                'description' => 'Optional: Tags inferred from the user\'s problem or request (e.g., "oil change", "flat tire"). If user says "all services" or "any service", omit this parameter.'
                            ],
                            'selected_specializations' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ],
                                'description' => 'Optional: Specific vehicle or service specializations relevant to the user\'s need. If user says "any" or "all", omit this parameter.'
                            ],
                            'selected_services' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ],
                                'description' => 'Optional: Specific garage services (e.g., AC repair, engine tuning, car wash). If user says "all services", "any service", or "everything", omit this parameter to show all garages in the location.'
                            ],
                            'selected_categories' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ],
                                'description' => 'Optional: Garage business categories, such as "mechanic", "electrician", or "tire shop". If user says "any" or "all", omit this parameter.'
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Optional: Number of garages to return. Default is 5 if not specified. If user specifically asks for a number (e.g., "3 garages", "10 garages"), use that number. Otherwise, omit this parameter to use the default of 5.'
                            ],
                        ],
                        'required' => ['location'],
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
                    'description' => 'Find location-based services and businesses in Kuwait ONLY. CRITICAL: Use this function (NOT findGarage) when users ask for: petrol stations, gas stations, fuel stations, ATMs, restaurants, cafes, hotels, shopping malls, hospitals, clinics, pharmacies, parking lots, car rentals, car insurance companies, gyms, supermarkets, banks, or ANY business/service that is NOT a garage/automotive repair shop. These services are NEVER in the garage database. DO NOT use findGarage for these queries. DO NOT use this function for non-location queries like jokes, general knowledge, weather, news, or any conversation that is not about finding a location in Kuwait. ALWAYS ask for location first if user doesn\'t provide it.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'service_type' => [
                                'type' => 'string',
                                'description' => 'The type of service or place the user is looking for (e.g., "petrol station", "ATM", "car insurance company", "parking lot", "car rental").'
                            ],
                            'location' => [
                                'type' => 'string',
                                'description' => 'REQUIRED: The area or location in Kuwait where the user wants to find the service (e.g., "Salmiya", "Kuwait City", "Hawalli"). Always ask for location first if user doesn\'t provide it. Never call this function without a location.'
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

            // Process tool calls
            $processResult = $this->processToolCalls(
                $toolCalls,
                $history,
                $conversation->id,
                $isMoreRequest,
                $searchState,
                $garagesData,
                $hasExternalServiceResults,
                $noMoreResults,
                $latitude,
                $longitude
            );
            
            $history = $processResult['history'];
            $garagesData = $processResult['garagesData'];
            $hasExternalServiceResults = $processResult['hasExternalServiceResults'];
            $noMoreResults = $processResult['noMoreResults'];
            $latitude = $processResult['latitude'];
            $longitude = $processResult['longitude'];
            $tokensUsed += $processResult['tokensUsed'];

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
            
            // Update message based on results
            $assistantText = $this->updateAssistantMessage($assistantText, $noMoreResults, $garagesData);
            
            // Check if second response also has tool calls (unlikely but possible)
            $secondToolCalls = $second->choices[0]->message->toolCalls
                ?? ($second->choices[0]->message->tool_calls ?? []);
            
            if (!empty($secondToolCalls)) {
                $processResult = $this->processToolCalls(
                    $secondToolCalls,
                    $history,
                    $conversation->id,
                    $isMoreRequest,
                    $searchState,
                    $garagesData,
                    $hasExternalServiceResults,
                    $noMoreResults,
                    $latitude,
                    $longitude
                );
                
                $history = $processResult['history'];
                $garagesData = $processResult['garagesData'];
                $hasExternalServiceResults = $processResult['hasExternalServiceResults'];
                $noMoreResults = $processResult['noMoreResults'];
                $latitude = $processResult['latitude'];
                $longitude = $processResult['longitude'];
                $tokensUsed += $processResult['tokensUsed'];
                
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
                
                // Update message based on results
                $assistantText = $this->updateAssistantMessage($assistantText, $noMoreResults, $garagesData);
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
            'title' => $this->getPreferredValue($garage['title'] ?? null, $garage['title_ar'] ?? null),
            'address' => $garage['address'] ?? null,
            'contact_no' => $garage['contact_no'] ?? null,
            'maps_share_link' => $garage['maps_share_link'] ?? null,
            'image' => $garage['image'] ?? null,
            'area' => $garage['area'] ?? null,
            'city' => $garage['city'] ?? null,
            'rating' => $garage['rating'] ?? null,
            'no_of_visits' => $garage['no_of_visits'] ?? null,
            'description' => $this->getPreferredValue($garage['description'] ?? null, $garage['description_ar'] ?? null),
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
     * Detect if user mentioned a new location (different from previous search)
     * This should ONLY return true if there's an explicit location mention that's different from previous
     */
    private function detectNewLocation(string $message, int $conversationId): bool
    {
        // Get previous search state
        $searchState = $this->getSearchState($conversationId);
        
        // If no previous search exists, we can't detect a "new" location
        if (!$searchState || !isset($searchState['search_params']['location'])) {
            return false;
        }
        
        $previousLocation = strtolower(trim($searchState['search_params']['location']));
        $messageLower = strtolower(trim($message));
        
        // If message is just "more", "find more", "show more", "2 more", etc. - it's NOT a new location
        // First check for simple number + "more" pattern (e.g., "2 more", "10 more")
        if (preg_match('/^(\d+)\s+more(?:\s+garages?)?$/i', $messageLower)) {
            return false; // Definitely a "more" request, not a new location
        }
        
        $moreKeywords = ['more', 'next', 'load more', 'show more', 'find more'];
        $isJustMoreRequest = false;
        foreach ($moreKeywords as $keyword) {
            // Check if message is primarily a "more" request (with optional numbers)
            if (preg_match('/^(?:\d+\s*)?(?:' . preg_quote($keyword, '/') . ')(?:\s+garages?)?$/i', $messageLower) ||
                preg_match('/^(?:find|show|get|give|bring|fetch)\s+(?:\d+\s*)?(?:more|garages?|results?|options?)(?:\s+garages?)?$/i', $messageLower)) {
                $isJustMoreRequest = true;
                break;
            }
        }
        
        // If it's just a "more" request, it's NOT a new location
        if ($isJustMoreRequest) {
            return false;
        }
        
        // Only check for explicit location mentions with "in", "at", "near"
        $locationPatterns = [
            '/\bin\s+([a-z\s]+?)(?:\s|$|,|\.)/i',
            '/\bat\s+([a-z\s]+?)(?:\s|$|,|\.)/i',
            '/\bnear\s+([a-z\s]+?)(?:\s|$|,|\.)/i'
        ];
        
        foreach ($locationPatterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $mentionedLocation = strtolower(trim($matches[1]));
                // Remove common words that might be captured
                $mentionedLocation = preg_replace('/\b(garages?|garage|find|search|for|a|the|in|at|near)\b/i', '', $mentionedLocation);
                $mentionedLocation = trim($mentionedLocation);
                
                // If mentioned location is different from previous and not empty, it's a new location
                if (!empty($mentionedLocation) && $mentionedLocation !== $previousLocation) {
                    // Additional check: make sure it's not just a partial match
                    if (strpos($previousLocation, $mentionedLocation) === false && 
                        strpos($mentionedLocation, $previousLocation) === false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Extract location from conversation history (fallback when search state is missing)
     */
    private function extractLocationFromHistory(array $history): ?string
    {
        // Look through history in reverse (most recent first) to find location mentions
        $historyReversed = array_reverse($history);
        
        foreach ($historyReversed as $message) {
            if ($message['role'] === 'user') {
                $content = strtolower($message['content'] ?? '');
                
                // Look for location patterns: "in [location]", "at [location]", etc.
                if (preg_match('/\b(?:in|at|near)\s+([a-z\s]+?)(?:\s|$|,|\.)/i', $content, $matches)) {
                    $location = trim($matches[1]);
                    // Remove common words
                    $location = preg_replace('/\b(garages?|garage|find|search|for|a|the|in|at|near)\b/i', '', $location);
                    $location = trim($location);
                    
                    if (!empty($location) && strlen($location) > 2) {
                        return $location;
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Check if the user message is a "more" request and extract number if specified
     * Returns array with 'isMore' boolean and optional 'limit' number
     */
    private function isMoreRequest(string $message): array
    {
        $messageLower = strtolower(trim($message));
        
        // First, check for number + "more" pattern (e.g., "2 more", "10 more", "3 more garages")
        // This catches simple patterns like "2 more" that might not match keyword list
        if (preg_match('/^(\d+)\s+more/i', $messageLower, $matches)) {
            return [
                'isMore' => true,
                'limit' => (int)$matches[1]
            ];
        }
        
        $moreKeywords = [
            'show more', 'more garages', 'load more', 'next', 'more results',
            'i need more', 'give me more', 'more options', 'more please',
            'show me more', 'get more', 'fetch more', 'bring more', 'more'
        ];
        
        $isMore = false;
        foreach ($moreKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                $isMore = true;
                break;
            }
        }
        
        // Extract number from messages like "3 more", "10 more", "show 5 more", etc.
        $limit = null;
        if ($isMore) {
            // Pattern to match numbers before "more" keywords
            // Matches: "3 more", "10 more", "show 5 more garages", "give me 7 more", etc.
            if (preg_match('/(\d+)\s*(?:more|garages?|results?|options?)/i', $messageLower, $matches)) {
                $limit = (int)$matches[1];
            }
        }
        
        return [
            'isMore' => $isMore,
            'limit' => $limit
        ];
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
            
            // Determine how many to return:
            // 1. Use _more_request_limit if specified (e.g., "3 more", "10 more")
            // 2. Otherwise use the original limit from search_params
            // 3. Default to 5 if neither exists
            $perPage = 5; // Default
            if (isset($searchState['_more_request_limit']) && $searchState['_more_request_limit'] > 0) {
                $perPage = (int)$searchState['_more_request_limit'];
            } elseif (isset($searchState['search_params']['limit']) && $searchState['search_params']['limit'] > 0) {
                $perPage = (int)$searchState['search_params']['limit'];
            }
            
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
        $limit = isset($args['limit']) && is_numeric($args['limit']) && $args['limit'] > 0 
            ? (int)$args['limit'] 
            : 5; // Default to 5 if not specified

        // Kuwait country ID is 112
        $query = Garage::query()
            ->where('status', 'approved')
            ->where('is_available', true)
            ->where('country_id', 112) // Only show garages in Kuwait
            ->whereNull('deleted_at');

        // Filter by location using smart area matching
        if ($location) {
            $matchedAreas = $this->matchAreasFromUserRequest($location);
            
            // Log for debugging
            Log::info('Area matching result', [
                'user_request' => $location,
                'matched_areas' => $matchedAreas,
                'match_count' => count($matchedAreas)
            ]);
            
            if (!empty($matchedAreas)) {
                // Use matched areas for precise search
                // Also search in address field to catch garages that might have the location in address
                $query->where(function ($q) use ($matchedAreas, $location) {
                    // First, try exact matches on area, city, governate
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
                    
                    // Also search in address field for the matched areas (in case area/city/governate don't match but address does)
                    foreach ($matchedAreas as $matchedArea) {
                        $q->orWhere('address', 'like', "%{$matchedArea}%");
                    }
                    
                    // Also do a LIKE search on the original location as a fallback
                    // This catches cases where the match might not be perfect
                    $q->orWhere('address', 'like', "%{$location}%");
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

        // Log query details for debugging
        Log::info('Garage search query executed', [
            'location' => $location,
            'matched_areas' => $matchedAreas ?? [],
            'selected_tags' => $selectedTags,
            'selected_services' => $selectedServices,
            'selected_specializations' => $selectedSpecializations,
            'selected_categories' => $selectedCategories,
            'garages_found' => $garages->count(),
        ]);

        // If no garages found, try a more lenient search
        if ($garages->isEmpty() && $location) {
            // Try a broader search - search in all location fields with LIKE
            $lenientQuery = Garage::query()
                ->where('status', 'approved')
                ->where('is_available', true)
                ->where('country_id', 112)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($location) {
                    $q->where('area', 'like', "%{$location}%")
                        ->orWhere('city', 'like', "%{$location}%")
                        ->orWhere('address', 'like', "%{$location}%")
                        ->orWhere('governate', 'like', "%{$location}%");
                });
            
            // Apply other filters if they exist
            if (!empty($selectedTags)) {
                $lenientQuery->whereHas('tags', function ($q) use ($selectedTags) {
                    $q->where(function ($subQ) use ($selectedTags) {
                        foreach ($selectedTags as $tag) {
                            $subQ->orWhere('title', 'like', "%{$tag}%")
                                ->orWhere('english_title', 'like', "%{$tag}%");
                        }
                    })->whereNull('tags.deleted_at');
                });
            }
            
            if (!empty($selectedServices)) {
                $lenientQuery->whereHas('services', function ($q) use ($selectedServices) {
                    $q->where(function ($subQ) use ($selectedServices) {
                        foreach ($selectedServices as $service) {
                            $subQ->orWhere('title', 'like', "%{$service}%")
                                ->orWhere('english_title', 'like', "%{$service}%");
                        }
                    })->whereNull('services.deleted_at');
                });
            }
            
            $lenientGarages = $lenientQuery->with(['tags', 'services', 'specializations', 'categories'])
                ->orderBy('rating', 'desc')
                ->orderBy('no_of_visits', 'desc')
                ->get();
            
            if (!$lenientGarages->isEmpty()) {
                Log::info('Lenient search found garages', ['count' => $lenientGarages->count()]);
                $garages = $lenientGarages;
            }
        }

        // If still no garages found
        if ($garages->isEmpty()) {
            Log::warning('No garages found after all search attempts', [
                'location' => $location,
                'filters' => [
                    'tags' => $selectedTags,
                    'services' => $selectedServices,
                    'specializations' => $selectedSpecializations,
                    'categories' => $selectedCategories,
                ]
            ]);
            
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

        // Limit results based on user's request (default 5)
        // Store all garages in cache for pagination (will be stored by handleChat after tool call)
        $limitedResults = array_slice($allResults, 0, $limit);
        
        return [
            'status' => 'success',
            'action' => 'find_garage',
            'count' => count($limitedResults),
            'total_count' => count($allResults),
            'results' => $limitedResults, // Return limited number of garages (default 5)
            '_all_garages' => $allResults, // Internal: all garages for caching
            '_offset' => count($limitedResults), // Internal: current offset (limited results shown)
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

        // Step 4: Try partial match - user request contained in area name (e.g., "shuaikh" in "shuwaikh industrial")
        $partialMatches = [];
        $userRequestNormalized = $normalize($userRequest);
        
        foreach ($uniqueAreas as $area) {
            $areaLower = strtolower(trim($area));
            $areaNormalized = $normalize($area);
            
            // Check if user request is contained in area name (normalized)
            if (strpos($areaNormalized, $userRequestNormalized) !== false) {
                $partialMatches[] = $area;
            }
            // Also check if area name is contained in user request (normalized)
            elseif (strpos($userRequestNormalized, $areaNormalized) !== false && strlen($areaNormalized) >= 5) {
                // Only if area is substantial (at least 5 chars) to avoid false matches
                $partialMatches[] = $area;
            }
            // Check word boundaries - if user says "shuaikh" and area is "shuwaikh industrial"
            elseif (preg_match('/\b' . preg_quote($userRequestNormalized, '/') . '/i', $areaNormalized)) {
                $partialMatches[] = $area;
            }
        }

        if (!empty($partialMatches)) {
            // Sort by relevance: exact word matches first, then by length (shorter = more specific)
            usort($partialMatches, function($a, $b) use ($userRequestLower, $userRequestNormalized) {
                $aLower = strtolower($a);
                $bLower = strtolower($b);
                $aNorm = preg_replace('/[^a-z0-9]/', '', $aLower);
                $bNorm = preg_replace('/[^a-z0-9]/', '', $bLower);
                
                // Prefer exact word match
                $aExactWord = ($aLower === $userRequestLower || $aNorm === $userRequestNormalized) ? 1 : 0;
                $bExactWord = ($bLower === $userRequestLower || $bNorm === $userRequestNormalized) ? 1 : 0;
                
                if ($aExactWord !== $bExactWord) {
                    return $bExactWord - $aExactWord;
                }
                
                // Prefer shorter matches (more specific)
                return strlen($a) - strlen($b);
            });
            
            return array_unique($partialMatches);
        }

        // Step 5: Final fallback - very loose partial match
        $looseMatches = [];
        foreach ($uniqueAreas as $area) {
            $areaLower = strtolower($area);
            // Check if any significant word from user request appears in area
            $userWords = array_filter(explode(' ', $userRequestLower), function($word) {
                return strlen(trim($word)) >= 4; // Only words with 4+ chars
            });
            
            foreach ($userWords as $word) {
                if (strpos($areaLower, $word) !== false) {
                    $looseMatches[] = $area;
                    break;
                }
            }
        }

        return array_unique($looseMatches);
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
            
            // Generate Google Maps URL
            $lat = $place['geometry']['location']['lat'] ?? null;
            $lng = $place['geometry']['location']['lng'] ?? null;
            $googleMapsUrl = $this->generateGoogleMapsUrl($name, $address, $placeId, $lat, $lng);
            
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
                    
                    // Generate Google Maps URL
                    $fullAddress = (!empty($address) && $address !== 'Address not available') 
                        ? $address . ', ' . $location . ', Kuwait' 
                        : $location . ', Kuwait';
                    $googleMapsUrl = $this->generateGoogleMapsUrl($name, $fullAddress, null, $lat, $lng);
                    
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

    /**
     * Get display titles from a table (tags, services, specializations, categories)
     */
    private function getDisplayTitles(string $table): string
    {
        return DB::table($table)
            ->whereNull('deleted_at')
            ->select(DB::raw('COALESCE(english_title, title) as display_title'))
            ->pluck('display_title')
            ->implode(', ');
    }

    /**
     * Get search state from cache
     */
    private function getSearchState(int $conversationId): ?array
    {
        return Cache::get('garage_search_state_' . $conversationId);
    }

    /**
     * Clear search state from cache
     */
    private function clearSearchState(int $conversationId): void
    {
        Cache::forget('garage_search_state_' . $conversationId);
    }

    /**
     * Process tool calls and extract data
     */
    private function processToolCalls(
        array $toolCalls,
        array &$history,
        int $conversationId,
        bool &$isMoreRequest,
        ?array &$searchState,
        array &$garagesData,
        bool &$hasExternalServiceResults,
        bool &$noMoreResults,
        ?float &$latitude,
        ?float &$longitude
    ): array {
        $tokensUsed = 0;

        foreach ($toolCalls as $tc) {
            $toolName = $tc->function->name ?? '';
            $argsJson = $tc->function->arguments ?? '{}';
            $args = json_decode($argsJson, true);
            if (!is_array($args)) {
                $args = [];
            }

            // Handle "more" request validation for findGarage
            if ($isMoreRequest && $searchState && $toolName === 'findGarage') {
                $validationResult = $this->validateMoreRequest($args, $searchState, $conversationId);
                if ($validationResult['isValid']) {
                    $args = $searchState['search_params'];
                } else {
                    $isMoreRequest = false;
                    $searchState = null;
                    $this->clearSearchState($conversationId);
                }
            }
            
            $result = $this->dispatchTool($toolName, $args, $isMoreRequest ? $searchState : null);

            // Track external service results
            if ($toolName === 'findExternalService' && is_array($result)) {
                if (isset($result['status']) && $result['status'] === 'success' && !empty($result['results'])) {
                    $hasExternalServiceResults = true;
                }
            }
            
            // Extract garage data and handle pagination
            if (in_array($toolName, ['findGarage', 'listGarages', 'getGarageDetails']) && is_array($result)) {
                if (isset($result['status']) && $result['status'] === 'no_more_results') {
                    $this->clearSearchState($conversationId);
                    $noMoreResults = true;
                } else {
                    $extractedGarages = $this->extractGarageDataFromToolResult($result, $toolName);
                    if (!empty($extractedGarages)) {
                        $garagesData = array_merge($garagesData, $extractedGarages);
                        
                        // Store search state for pagination
                        if ($toolName === 'findGarage' && isset($result['status']) && $result['status'] === 'success') {
                            $this->handleSearchStatePagination(
                                $conversationId,
                                $args,
                                $result,
                                $isMoreRequest,
                                $searchState
                            );
                        }
                    }
                }
            }

            // Extract coordinates
            $coords = $this->extractCoordinates($result);
            if ($coords['lat'] !== null && $coords['lng'] !== null) {
                $latitude = $coords['lat'];
                $longitude = $coords['lng'];
            }

            $history[] = [
                'role' => 'tool',
                'tool_call_id' => $tc->id ?? '',
                'content' => is_string($result) ? $result : json_encode($result),
            ];
        }

        return [
            'history' => $history,
            'garagesData' => $garagesData,
            'hasExternalServiceResults' => $hasExternalServiceResults,
            'noMoreResults' => $noMoreResults,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'tokensUsed' => $tokensUsed,
        ];
    }

    /**
     * Validate "more" request parameters
     */
    private function validateMoreRequest(array $currentParams, array $searchState, int $conversationId): array
    {
        $cachedParams = $searchState['search_params'] ?? [];
        
        $normalizeParams = function($params) {
            $normalized = [];
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    $normalized[$key] = array_filter(array_map('trim', $value));
                    sort($normalized[$key]);
                } else {
                    $normalized[$key] = trim($value ?? '');
                }
            }
            return $normalized;
        };
        
        $cachedNormalized = $normalizeParams($cachedParams);
        $currentNormalized = $normalizeParams($currentParams);
        
        if ($cachedNormalized == $currentNormalized) {
            Log::info('Using cached search params for "more" request', ['params' => $cachedParams]);
            return ['isValid' => true];
        }
        
        Log::info('Search params changed - treating as new search instead of "more"', [
            'cached' => $cachedParams,
            'current' => $currentParams
        ]);
        return ['isValid' => false];
    }

    /**
     * Handle search state pagination
     */
    private function handleSearchStatePagination(
        int $conversationId,
        array $args,
        array $result,
        bool $isMoreRequest,
        ?array &$searchState
    ): void {
        if ($isMoreRequest && $searchState) {
            if (isset($result['_new_offset'])) {
                $searchState['current_offset'] = $result['_new_offset'];
            } elseif (isset($result['results'])) {
                $newOffset = ($searchState['current_offset'] ?? 0) + count($result['results']);
                $searchState['current_offset'] = $newOffset;
            }
            Cache::put('garage_search_state_' . $conversationId, $searchState, now()->addHour());
        } elseif (!$isMoreRequest && isset($result['_all_garages'])) {
            $offset = $result['_offset'] ?? count($result['_all_garages']);
            $this->storeSearchState($conversationId, $args, $result['_all_garages'], $offset);
        }
    }

    /**
     * Extract coordinates from tool result
     */
    private function extractCoordinates($result): array
    {
        $lat = null;
        $lng = null;

        if (!is_array($result)) {
            return ['lat' => $lat, 'lng' => $lng];
        }

        // Check results array (findGarage, listGarages, findExternalService)
        if (isset($result['results']) && is_array($result['results']) && !empty($result['results'])) {
            $firstResult = $result['results'][0];
            if (isset($firstResult['latitude']) && isset($firstResult['longitude'])) {
                $latVal = $firstResult['latitude'];
                $lngVal = $firstResult['longitude'];
                if ($latVal !== null && $latVal !== '' && $lngVal !== null && $lngVal !== '') {
                    $lat = is_numeric($latVal) ? (float)$latVal : $latVal;
                    $lng = is_numeric($lngVal) ? (float)$lngVal : $lngVal;
                }
            }
        }
        // Check result object (getGarageDetails)
        elseif (isset($result['result']) && is_array($result['result'])) {
            if (isset($result['result']['latitude']) && isset($result['result']['longitude'])) {
                $latVal = $result['result']['latitude'];
                $lngVal = $result['result']['longitude'];
                if ($latVal !== null && $latVal !== '' && $lngVal !== null && $lngVal !== '') {
                    $lat = is_numeric($latVal) ? (float)$latVal : $latVal;
                    $lng = is_numeric($lngVal) ? (float)$lngVal : $lngVal;
                }
            }
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Update assistant message based on results
     */
    private function updateAssistantMessage(string $assistantText, bool $noMoreResults, array $garagesData): string
    {
        if ($noMoreResults && empty($garagesData)) {
            return 'All matching garages for your search have been provided to you.';
        } elseif (!empty($garagesData)) {
            return $this->ensureMinimalGarageMessage($assistantText, $garagesData);
        }
        return $assistantText;
    }

    /**
     * Generate Google Maps URL with priority: name+address > place_id > coordinates
     */
    private function generateGoogleMapsUrl(?string $name, ?string $address, ?string $placeId = null, ?float $lat = null, ?float $lng = null): ?string
    {
        // Priority 1: Use place name + address (most reliable)
        if (!empty($name) && $name !== 'Unknown') {
            $encodedName = urlencode($name);
            if (!empty($address) && trim($address) !== '') {
                $encodedAddress = urlencode($address);
                return "https://www.google.com/maps/search/?api=1&query={$encodedName},{$encodedAddress}";
            }
            return "https://www.google.com/maps/search/?api=1&query={$encodedName},Kuwait";
        }
        
        // Priority 2: Use place_id with name
        if (!empty($placeId) && !empty($name) && $name !== 'Unknown') {
            $encodedPlaceId = urlencode($placeId);
            $encodedName = urlencode($name);
            return "https://www.google.com/maps/search/?api=1&query={$encodedName}&query_place_id={$encodedPlaceId}";
        }
        
        // Priority 2b: Direct place_id format
        if (!empty($placeId)) {
            $encodedPlaceId = urlencode($placeId);
            return "https://www.google.com/maps/place/?q=place_id:{$encodedPlaceId}";
        }
        
        // Priority 3: Fallback to address only
        if (!empty($address) && trim($address) !== '' && $address !== 'Address not available') {
            $mapsQuery = urlencode($address);
            return "https://www.google.com/maps/search/?api=1&query={$mapsQuery}";
        }
        
        // Priority 4: Fallback to coordinates
        if ($lat !== null && $lng !== null) {
            return "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
        }
        
        return null;
    }
}
