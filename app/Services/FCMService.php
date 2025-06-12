<?php

namespace App\Services;

use App\Models\User;
use Google\Auth\OAuth2;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FCMService
{
    protected $projectId;
    protected $clientEmail;
    protected $privateKey;

    public function __construct()
    {
        $credentialsJson = Storage::get('firebase-service-account.json');
        $credentials = json_decode($credentialsJson, true);
        
        $this->clientEmail = $credentials['client_email'];
        $this->privateKey = $credentials['private_key'];
        $this->projectId = $credentials['project_id'];
    }

    public function sendNotification(int $userId, string $title, string $body)
    {
        $user = User::findOrFail($userId);
        
        if (!$user->device_token) {
            throw new \Exception('User does not have a device token');
        }

        // Get OAuth2 access token
        $oauth = new OAuth2([
            'tokenCredentialUri' => 'https://oauth2.googleapis.com/token',
            'audience' => 'https://oauth2.googleapis.com/token',
            'issuer' => $this->clientEmail,
            'signingAlgorithm' => 'RS256',
            'signingKey' => $this->privateKey,
            'scope' => ['https://www.googleapis.com/auth/firebase.messaging'],
            'grantType' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        ]);

        $token = $oauth->fetchAuthToken()['access_token'] ?? null;

        if (!$token) {
            Log::error('Failed to get Firebase access token');
            throw new \Exception('Failed to get Firebase access token');
        }

        // Send message to v1 API
        $response = Http::withToken($token)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'message' => [
                    'token' => $user->device_token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'user_id' => (string) $userId,
                    ],
                ]
            ]);

            dd($response->json());

        if (!$response->successful()) {
            Log::error('FCM Notification failed', [
                'user_id' => $userId,
                'response' => $response->json(),
            ]);
            throw new \Exception('Failed to send FCM notification');
        }

        return $response->json();
    }
} 