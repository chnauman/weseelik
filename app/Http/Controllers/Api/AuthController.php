<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Twilio\Rest\Client;
use Carbon\Carbon;
use App\Models\Message;

class AuthController extends Controller
{
    protected $twilioClient;

    public function __construct()
    {
        $this->twilioClient = new Client(
            config('services.twilio.sid'),
            config('services.twilio.auth_token')
        );
    }

    /**
     * Login user and create token
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        // Check if user credentials are valid
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials'
            ], 401);
        }

        // Get authenticated user
        $user = User::where('email', $request->email)->first();

        // Use device name from request or default to 'mobile'
        $deviceName = $request->device_name ?? 'mobile';

        // Revoke existing tokens if needed
        // $user->tokens()->where('name', $deviceName)->delete();

        // Create new token
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type,
                'is_profile_complete' => $user->is_profile_complete,
                'is_phone_verified' => $user->detail ? $user->detail->is_phone_verified : false,
                'roles' => $user->roles->pluck('title'),
                'created_at' => $user->created_at,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Logout user (revoke the token)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Get the authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()->load('roles'),
        ]);
    }

    /**
     * Register a new user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $validator = validator($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'phone_number' => 'required|string',
                'password' => 'required|string|min:8',
                'device_name' => 'nullable|string',
                'type' => 'required|string|in:client,business',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors();

                if ($errors->has('email') && str_contains($errors->first('email'), 'taken')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The email address already exists',
                        'error_code' => 'duplicate_email'
                    ], 200);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ], 422);
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'type' => $request->type,
            ]);

            // Generate verification code for business users
            $verificationCode = null;
            $verificationCodeExpiresAt = null;
            
            if ($request->type === 'business') {
                $verificationCode = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $verificationCodeExpiresAt = now()->addMinutes(10);
                
                // Send verification code via Twilio
                try {

                    /*
                    $result = $this->twilioClient->verify->v2->services("VAf0b70ad112078266cdbcac423c9769d1")
                    ->verifications
                    ->create($request->phone_number, "sms");
                    \Log::info('Twilio SMS Result: ' . json_encode($result));
                    */
                    
                    $this->twilioClient->messages->create(
                        $request->phone_number,
                        [
                            'from' => "+12393651789",
                            'body' => "Your Weseelik verification code is: {$verificationCode}. This code will expire in 10 minutes."
                        ]
                    );
                    
                } catch (\Exception $e) {
                    // Log the error but continue with user creation
                    \Log::error('Twilio SMS Error: ' . $e->getMessage());
                }
            }

            // Create user details with phone number and verification data
            $user->detail()->create([
                'phone_number' => $request->phone_number,
                'verification_code' => $verificationCode,
                'verification_code_expires_at' => $verificationCodeExpiresAt,
                'is_phone_verified' => $request->type === 'client', // Auto-verify client users
            ]);

            $deviceName = $request->device_name ?? 'mobile';
            $token = $user->createToken($deviceName)->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User successfully registered',
                'user_id' => $user->id,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => $user->type,
                    'verification_code' => $verificationCode,
                    'verification_code_expires_at' => $verificationCodeExpiresAt,
                    'is_profile_complete' => $user->is_profile_complete,
                    'is_phone_verified' => $user->detail->is_phone_verified,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                return response()->json([
                    'success' => false,
                    'message' => 'The email address already exists',
                    'error_code' => 'duplicate_email'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify phone number with verification code
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPhone(Request $request)
    {
        $request->validate([
            'verification_code' => 'required|string|size:4',
        ]);

        $user = $request->user();
        
        // Check if user is a business user
        if ($user->type !== 'business') {
            return response()->json([
                'success' => false,
                'message' => 'Phone verification is only required for business users'
            ], 400);
        }

        $userDetail = $user->detail;

        if (!$userDetail) {
            return response()->json([
                'success' => false,
                'message' => 'User details not found'
            ], 404);
        }

        // Check if verification code matches and hasn't expired
        if ($userDetail->verification_code !== $request->verification_code) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code'
            ], 400);
        }

        if (Carbon::parse($userDetail->verification_code_expires_at)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code has expired'
            ], 400);
        }

        // Update verification status
        $userDetail->update([
            'is_phone_verified' => true,
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully',
            'is_phone_verified' => true
        ]);
    }

    /**
     * Send a password reset link to the given user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['success' => true, 'message' => __($status)])
            : response()->json(['success' => false, 'message' => __($status)], 400);
    }

    /**
     * Reset the user's password
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['success' => true, 'message' => __($status)])
            : response()->json(['success' => false, 'message' => __($status)], 400);
    }

    /**
     * Update user details
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUserDetails(Request $request)
    {
        // Validate request
        $validator = validator($request->all(), [
            'phone_number' => 'sometimes|string',
            'age' => 'sometimes|integer',
            'address' => 'sometimes|string',
            'latitude' => 'sometimes|string',
            'longitude' => 'sometimes|string',
            'dob' => 'sometimes|date|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if at least one field is provided
        /*
        if (!$request->has(['phone_number', 'age', 'address', 'latitude', 'longitude'])) {
            return response()->json([
                'success' => false,
                'message' => 'At least one field must be provided'
            ], 422);
        }
        */

        try {
            $user = $request->user();
            $userDetail = $user->detail;

            // Create user detail if it doesn't exist
            if (!$userDetail) {
                $userDetail = $user->detail()->create([]);
            }

            // Update only provided fields
            $updateData = [];

            if ($request->has('phone_number')) {
                $updateData['phone_number'] = $request->phone_number;
            }

            if ($request->has('brand_id')) {
                $user->vehicleBrands()->sync($request->input('brand_id', []));
            }

            if ($request->has('age')) {
                $updateData['age'] = $request->age;
            }

            if ($request->has('address')) {
                $updateData['address'] = $request->address;
            }

            if ($request->has('latitude')) {
                $updateData['latitude'] = $request->latitude;
            }

            if ($request->has('longitude')) {
                $updateData['longitude'] = $request->longitude;
            }

            if ($request->has('dob')) {
                $updateData['dob'] = $request->dob;
            }

            if ($request->hasFile('avatar')) {
                if ($userDetail->avatar && Storage::disk('public')->exists($userDetail->avatar)) {
                    Storage::disk('public')->delete($userDetail->avatar);
                }

                $avatar = $request->file('avatar');
                $path = $avatar->store('avatars', 'public');
                $updateData['avatar'] = $path;
            }

            // Update the user details
            $userDetail->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'User details updated successfully',
                'user_details' => $userDetail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get complete user profile
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserProfile(Request $request)
    {
        try {
            $user = $request->user();

            // Load user details and vehicle brands relations if they exist
            $user->load(['detail', 'vehicleBrands']);

            $profileData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ];

            // Add user details if they exist
            if ($user->detail) {
                $profileData['phone_number'] = $user->detail->phone_number;
                $profileData['age'] = $user->detail->age;
                $profileData['address'] = $user->detail->address;
                $profileData['latitude'] = $user->detail->latitude;
                $profileData['longitude'] = $user->detail->longitude;
                $profileData['avatar'] = $user->detail->avatar 
                    ? asset('storage/' . $user->detail->avatar) 
                    : null;
            }

            // Add user brands
            $profileData['brands'] = $user->vehicleBrands->map(function ($brand) {
                return [
                    'id' => $brand->id,
                    'name' => $brand->title,
                ];
            });

            return response()->json([
                'success' => true,
                'profile' => $profileData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function googleLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->id_token);

            // Find user or create
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make(Str::random(16)), // Dummy password
                ]);

                $user->detail()->create(); // create empty detail record
            }

            $token = $user->createToken($request->device_name ?? 'mobile')->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend verification code to user's phone number
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerificationCode(Request $request)
    {
        $user = $request->user();
        
        // Check if user is a business user
        if ($user->type !== 'business') {
            return response()->json([
                'success' => false,
                'message' => 'Phone verification is only required for business users'
            ], 400);
        }

        $userDetail = $user->detail;

        if (!$userDetail) {
            return response()->json([
                'success' => false,
                'message' => 'User details not found'
            ], 404);
        }

        // Check if phone is already verified
        if ($userDetail->is_phone_verified) {
            return response()->json([
                'success' => true,
                'message' => 'Phone number is already verified',
                'is_phone_verified' => true
            ]);
        }

        // Generate new verification code
        $verificationCode = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $verificationCodeExpiresAt = now()->addMinutes(10);

        // Send verification code via Twilio
        try {
            
            $result = $this->twilioClient->messages->create(
                $userDetail->phone_number,
                [
                    'from' => "+12393651789",
                    'body' => "Your Weseelik verification code is: {$verificationCode}. This code will expire in 10 minutes."
                ]
            );
            //

            // Update user details with new verification code
            $userDetail->update([
                'verification_code' => $verificationCode,
                'verification_code_expires_at' => $verificationCodeExpiresAt,
                'is_phone_verified' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Verification code has been resent',
                'verification_code' => config('services.twilio.test_mode', false) ? $verificationCode : null
            ]);
        } catch (\Exception $e) {
            \Log::error('Twilio SMS Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code'
            ], 500);
        }
    }

    /**
     * Change user's phone number and send verification code
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePhoneNumber(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
        ]);

        $user = $request->user();
        $userDetail = $user->detail;

        if (!$userDetail) {
            return response()->json([
                'success' => false,
                'message' => 'User details not found'
            ], 404);
        }

        // Generate new verification code
        $verificationCode = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $verificationCodeExpiresAt = now()->addMinutes(10);

        try {
            // Send verification code via Twilio
            $this->twilioClient->messages->create(
                $request->phone_number,
                [
                    'from' => "+12393651789",
                    'body' => "Your Weseelik verification code is: {$verificationCode}. This code will expire in 10 minutes."
                ]
            );

            // Update user details with new phone number and verification code
            $userDetail->update([
                'phone_number' => $request->phone_number,
                'verification_code' => $verificationCode,
                'verification_code_expires_at' => $verificationCodeExpiresAt,
                'is_phone_verified' => false // Reset verification status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phone number updated and verification code sent',
                'verification_code' => config('services.twilio.test_mode', false) ? $verificationCode : null
            ]);

        } catch (\Exception $e) {
            \Log::error('Twilio SMS Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code'
            ], 500);
        }
    }

    public function loadChats(Request $request)
    {
        $user = $request->user();
        
        // Get all unique chat participants for the authenticated user
        $chatParticipants = Message::where(function($query) use ($user) {
                $query->where('sender_id', $user->id)
                      ->orWhere('receiver_id', $user->id);
            })
            ->select('sender_id', 'receiver_id')
            ->distinct()
            ->get()
            ->map(function($message) use ($user) {
                // Get the other participant's ID
                return $message->sender_id === $user->id ? $message->receiver_id : $message->sender_id;
            })
            ->unique();

        // Get chat summaries
        $chats = [];
        foreach ($chatParticipants as $participantId) {
            $participant = User::with('garages')->find($participantId);
            if (!$participant) continue;

            // Count unread messages
            $unreadCount = Message::where('sender_id', $participantId)
                ->where('receiver_id', $user->id)
                ->where('status', '!=', 'read')
                ->count();

            // Get display name - garage title for business users, name for others
            $displayName = $participant->type === 'business' && $participant->garages->isNotEmpty() 
                ? $participant->garages->first()->title 
                : $participant->name;

            $chats[] = [
                'id' => $participant->id,
                'name' => $displayName,
                'unread_count' => $unreadCount
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $chats
        ]);
    }

    /**
     * Load all messages between authenticated user and a specific participant
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $participant_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadChatMessages(Request $request, $participant_id)
    {
        $user = $request->user();
        
        // Verify participant exists
        $participant = User::find($participant_id);
        if (!$participant) {
            return response()->json([
                'success' => false,
                'message' => 'Participant not found'
            ], 404);
        }

        // Get all messages between the two users
        $messages = Message::where(function($query) use ($user, $participant_id) {
                $query->where(function($q) use ($user, $participant_id) {
                    $q->where('sender_id', $user->id)
                      ->where('receiver_id', $participant_id);
                })->orWhere(function($q) use ($user, $participant_id) {
                    $q->where('sender_id', $participant_id)
                      ->where('receiver_id', $user->id);
                });
            })
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'sender_id' => $message->sender_id,
                    'receiver_id' => $message->receiver_id,
                    'sender_name' => $message->sender->name,
                    'receiver_name' => $message->receiver->name,
                    'status' => $message->status,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'participant' => [
                    'id' => $participant->id,
                    'name' => $participant->name
                ],
                'messages' => $messages
            ]
        ]);
    }

    public function markAsRead(Request $request)
    {
        $user = $request->user();
        $participant_id = $request->participant_id;
        $message = Message::where('sender_id', $participant_id)
            ->where('receiver_id', $user->id)
            ->where('status', '!=', 'read')
            ->update(['status' => 'read']);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read'
        ]);
    }

    /**
     * Send a chat message to a participant
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendChatMessage(Request $request)
    {
        $request->validate([
            'participant_id' => 'required|exists:users,id',
            'message' => 'required|string'
        ]);

        $user = $request->user();
        
        // Create the message
        $message = Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $request->participant_id,
            'message' => $request->message,
            'status' => 'unread'
        ]);

        // Load the relationships for the response
        $message->load(['sender', 'receiver']);

        // Prepare data for broadcasting
        $messageData = [
            'id' => $message->id,
            'message' => $message->message,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'sender_name' => $message->sender->name,
            'receiver_name' => $message->receiver->name,
            'status' => $message->status,
            'created_at' => $message->created_at,
            'updated_at' => $message->updated_at
        ];

        // Broadcast the message to the participant's channel
        event(new \App\Events\NewMessage($messageData));

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $messageData
        ]);
    }
}
