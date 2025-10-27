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
        $conversation = null;
        
        if (!empty($validated['conversation_id'])) {
            $conversation = DB::table('conversations')->where('session_id', $validated['conversation_id'])->first();
        }
        if (!$conversation) {
            $conversationId = DB::table('conversations')->insertGetId([
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
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
            'content' => "You are a polite, human-like Lost and Found chatbot agent for Miami International Airport.
             Your job is to help travelers report or claim lost items. Always greet user warmly but not too formal.
             Always stay calm, and concise — reply in 1-2  lines, or rarely 3 short lines when it feels natural.
             Sometimes you can give suggestions to the user about the immediate action to take. 
             like: if user says I lost my wallet, you can suggest the user to block the cards first.
             Use the tools provided to help the user.
             If the user asks a question that is not related to the tools, respond very politely and humanly.
             When you know to which tool the user is referring, collect all required details in conversational manner
             but before asking user see what you can infer from the chat history and the current message. If you have to 
             repeat the question, rephrase it. Do not just repeat the question verbatim until very necessary.
             Never ask the user to provide everything at once. Ask one question at a time but make it conversational and natural.
             if you require user's email, make sure that the user provides a valid email address in the correct format.
             **Never ask unnecessary or obvious questions.** If information can be inferred from chat history 
             or the current message, use it directly.
             Respond naturally like a human, not robotic. Be efficient and empathetic.
             **Once all parameters are gathered, call the function automatically and confirm politely.**
             "
        ]);

        //return response()->json($history);

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'reportLostItem',
                    'description' => 'Report a newly lost item at MIA Lost & Found.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'item_description' => [
                                'type' => 'string',
                                'description' => 'Describe the lost item.'
                            ],
                            'lost_location' => [
                                'type' => 'string',
                                'description' => 'Where it was last seen.'
                            ],
                            'date_lost' => [
                                'type' => 'string',
                            ],
                            'owner_name' => [
                                'type' => 'string',
                            ],
                            'contact_email' => [
                                'type' => 'string',
                                'description' => 'Please verify email address format. Example: example@example.com'
                            ],
                            'contact_phone' => [
                                'type' => 'string',
                            ],
                            'flight_number' => [
                                'type' => 'string',
                                'description' => 'Optional.'
                            ],
                            'additional_details' => [
                                'type' => 'string',
                                'description' => 'Optional notes.'
                            ],
                        ],
                        'required' => ['item_description', 'lost_location', 'date_lost', 'owner_name', 'contact_email'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'claimFoundItem',
                    'description' => 'Submit a claim for a found item.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'item_id' => ['type' => 'string'],
                            'owner_name' => ['type' => 'string'],
                            'contact_email' => ['type' => 'string', 'description' => 'Please verify email address format. Example: example@example.com'],
                            'contact_phone' => ['type' => 'string'],
                            'proof_of_ownership' => ['type' => 'string', 'description' => 'Optional.'],
                            'pickup_preference' => [
                                'type' => 'string',
                                'enum' => ['in_person', 'courier'],
                            ],
                        ],
                        'required' => ['item_id', 'owner_name', 'contact_email'],
                    ],
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'checkItemStatus',
                    'description' => 'Check the status of a report or claim.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reference_number' => ['type' => 'string'],
                            'contact_email' => ['type' => 'string', 'description' => 'Please verify email address format. Example: example@example.com'],
                        ],
                    ],
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'bookPickupAppointment',
                    'description' => 'Book an in-person pickup appointment.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reference_number' => ['type' => 'string'],
                            'date' => ['type' => 'string'],
                            'time_slot' => ['type' => 'string'],
                            'full_name' => ['type' => 'string'],
                            'id_type' => ['type' => 'string'],
                            'id_number' => ['type' => 'string'],
                        ],
                    ],
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listRecentFoundItems',
                    'description' => 'List recently found items.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'category' => ['type' => 'string'],
                            'date_range' => ['type' => 'string'],
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
            case 'reportLostItem':
                return $this->toolReportLostItem($args);
            case 'claimFoundItem':
                return $this->toolClaimFoundItem($args);
            case 'checkItemStatus':
                return $this->toolCheckItemStatus($args);
            case 'bookPickupAppointment':
                return $this->toolBookPickupAppointment($args);
            case 'listRecentFoundItems':
                return $this->toolListRecentFoundItems($args);
            default:
                return ['error' => 'Unknown tool: ' . $name];
        }
    }

    private function toolReportLostItem(array $args)
    {
        // TODO: Replace with real implementation (persist lost item report)
        return [
            'status' => 'ok',
            'action' => 'report_lost_item',
            'received' => $args,
            'message' => 'Lost item reported. A case reference will be emailed shortly.',
        ];
    }

    private function toolClaimFoundItem(array $args)
    {
        // TODO: Replace with real implementation (create claim)
        return [
            'status' => 'ok',
            'action' => 'claim_found_item',
            'received' => $args,
            'message' => 'Claim submitted. We will verify ownership and get back to you.',
        ];
    }

    private function toolCheckItemStatus(array $args)
    {
        // TODO: Replace with real implementation (lookup by reference/email)
        return [
            'status' => 'ok',
            'action' => 'check_item_status',
            'received' => $args,
            'result' => 'Reference not found in demo. Please ensure the reference is correct.',
        ];
    }

    private function toolBookPickupAppointment(array $args)
    {
        // TODO: Replace with real implementation (schedule appointment)
        return [
            'status' => 'ok',
            'action' => 'book_pickup_appointment',
            'received' => $args,
            'message' => 'Appointment request received. You will receive a confirmation email.',
        ];
    }

    private function toolListRecentFoundItems(array $args)
    {
        // TODO: Replace with real implementation (query recent found items)
        return [
            'status' => 'ok',
            'action' => 'list_recent_found_items',
            'received' => $args,
            'items' => [],
        ];
    }
}
