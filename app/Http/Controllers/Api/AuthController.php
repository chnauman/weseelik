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

class AuthController extends Controller
{
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
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors();

                // Check specifically for duplicate email
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
            ]);

            // Create user details with phone number
            $user->detail()->create([
                'phone_number' => $request->phone_number,
            ]);

            // $user->vehicleBrands()->sync($request->input('brand_id', []));

            // Attach default role if needed
            // $user->roles()->attach(Role::where('title', 'User')->first());

            $deviceName = $request->device_name ?? 'mobile';
            $token = $user->createToken($deviceName)->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User successfully registered',
                'user_id' => $user->id,
                'token' => $token,
                'token_type' => 'Bearer',
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            // Catch unique constraint violation for email
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
}
