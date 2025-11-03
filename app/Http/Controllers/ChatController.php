<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;

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

        $tags = DB::table('tags')->pluck('english_title')->implode(', ');

        $specializations = DB::table('specializations')->pluck('title')->implode(', ');

        $services = DB::table('services')->pluck('title')->implode(', ');

        $categories = DB::table('categories')->pluck('title')->implode(', ');

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
                - Never ask for obvious info (e.g., if the user says 'in Salmiya', don’t ask 'Where are you located?').
                - Once enough info is available, call the function automatically and politely confirm.
                - If the query is unrelated to garage services, reply politely that you can only help with the available tools.
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
            'message' => $assistantText,
            'conversation_id' => $conversation->id,
            'session_id' => session()->getId(),
            'partner_id' => 'miami_airport',
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
}
