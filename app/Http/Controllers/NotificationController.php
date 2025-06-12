<?php

namespace App\Http\Controllers;

use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    public function sendNotification(Request $request): JsonResponse
    {
        //dd($request->all());
        $request->validate([
            'user_id' => 'required|exists:users,id',
            //'title' => 'required|string',
            //'body' => 'required|string',
        ]);

        try {
            $result = $this->fcmService->sendNotification(
                $request->user_id,
                "Hello Device",
                "This is a test notification from Laravel"
            );

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 