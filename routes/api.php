<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JsonEditorController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GarageMatchController;

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});


Route::get('/welcome', function () {
    echo "Welcome to the API";
});

// Garage Match API (public, no authentication required)
Route::get('/garage-match-url/{id}', [GarageMatchController::class, 'matchGarageWithUrl']);

Route::group(['prefix' => 'v1', 'as' => 'api.'], function () {
    Route::post('chat', [ChatController::class, 'handleChat'])->name('chat');
    Route::get('ping', function () {
        return response()->json(['status' => 'ok']);
    });
});

Route::group(['prefix' => 'v2', 'as' => 'api.v2.'], function () {
    Route::post('chat', [App\Http\Controllers\ExperimentalChatController::class, 'handleChat'])->name('chat');
});

Route::group(['prefix' => 'v1', 'as' => 'api.', 'namespace' => 'Api\V1\Admin'], function () {

    // Public Auth Routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/google-login', [AuthController::class, 'googleLogin']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Service
    Route::apiResource('services', 'ServiceApiController');

    // Language
    Route::apiResource('languages', 'LanguageApiController');

    // Tags
    Route::apiResource('tags', 'TagsApiController');

    // Countries
    Route::apiResource('countries', 'CountriesApiController');

    // Specialization
    Route::apiResource('specializations', 'SpecializationApiController');

    Route::post('/check-channel', [AuthController::class, 'checkChannel']);

    // Garages
    Route::get('garages/map-markers', 'GaragesApiController@mapMarkers')->name('garages.mapMarkers'); // Public route for map plotting
    Route::post('garages/media', 'GaragesApiController@storeMedia')->name('garages.storeMedia');
    Route::middleware('optional.auth')->get('garages/top-visited', 'GaragesApiController@topVisited')->name('garages.topVisited');   // by user
    Route::middleware('optional.auth')->get('garages/nearby', 'GaragesApiController@nearby')->name('garages.nearby');                // by user
    Route::middleware('optional.auth')->get('garages/search', 'GaragesApiController@search')->name('garages.search');                // by user

    Route::middleware('auth:sanctum')->group(function () {
        // Garages - Protected Routes
        Route::get('garages/get-blocked-garages', 'UserBlockApiController@index')->name('garages.get-blocked-garages');
        Route::post('garages/block', 'UserBlockApiController@blockGarage')->name('garages.block');
        Route::post('garages/unblock', 'UserBlockApiController@unblockGarage')->name('garages.unblock');
        Route::post('garages/is-blocked', 'UserBlockApiController@isBlocked')->name('garages.isBlocked');
        Route::post('garages/update-availability', 'GaragesApiController@updateGarageAvailability')->name('garages.updateAvailability');
        Route::post('garages/update-business-garage', 'GaragesApiController@updateBusinessGarage')->name('garages.updateBusinessGarage');

        Route::get('garages/by-user', 'GaragesApiController@getUserGarages')->name('garages.getUserGarages');
        Route::get('garages/business-details', 'GaragesApiController@getBusinessDetails')->name('garages.getBusinessDetails');

        Route::post('/verify-phone', [AuthController::class, 'verifyPhone']);
        Route::post('/resend-verification-code', [AuthController::class, 'resendVerificationCode']);
        Route::post('/change-phone-number', [AuthController::class, 'changePhoneNumber']);
        Route::post('/update-user-details', [AuthController::class, 'updateUserDetails']);
        Route::get('/user-profile', [AuthController::class, 'getUserProfile']);
        Route::post('/delete-account', [AuthController::class, 'deleteAccount']);

        Route::post('bookings/create', 'BookingApiController@createBooking')->name('bookings.createBooking');
        Route::get('bookings/get-business-bookings', 'BookingApiController@getBusinessBookings')->name('bookings.getBusinessBookings');
        Route::post('bookings/update-status', 'BookingApiController@updateStatus')->name('bookings.updateStatus');
        Route::apiResource('bookings', 'BookingApiController');

        Route::get('/load-chats', [AuthController::class, 'loadChats']);
        Route::get('/load-chat-messages/{participant_id}', [AuthController::class, 'loadChatMessages']);
        Route::post('/mark-as-read', [AuthController::class, 'markAsRead']);
        Route::post('/send-chat-message', [AuthController::class, 'sendChatMessage']);
        Route::post('/update-device-token', [AuthController::class, 'updateDeviceToken']);

    });

    Route::middleware('optional.auth')->get('garages/{garage}', 'GaragesApiController@show');
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('garages', 'GaragesApiController')->except(['show']);
    });

    // Vehicle Brand
    Route::post('vehicle-brands/media', 'VehicleBrandApiController@storeMedia')->name('vehicle-brands.storeMedia');
    Route::apiResource('vehicle-brands', 'VehicleBrandApiController');

    // User Favorite
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('user-favorites/toggle', 'UserFavoriteApiController@toggleFavorites')->name('user-favorites.toggle');
        Route::post('user-favorites/update-note', 'UserFavoriteApiController@updateNote')->name('user-favorites.updateNote');
        Route::get('user-favorites/get-favorties', 'UserFavoriteApiController@getUserFavorties')->name('user-favorites.get-favorties');
    });
    Route::apiResource('user-favorites', 'UserFavoriteApiController');
    // User Block
    /*
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user-blocks', 'UserBlockApiController@index')->name('user-blocks.index');
        Route::post('user-blocks/block', 'UserBlockApiController@blockGarage')->name('user-blocks.block');
        Route::post('user-blocks/unblock', 'UserBlockApiController@unblockGarage')->name('user-blocks.unblock');
        Route::get('user-blocks/is-blocked', 'UserBlockApiController@isBlocked')->name('user-blocks.isBlocked');
    });
    */

    // Message
    Route::apiResource('messages', 'MessageApiController');

    // Category
    Route::apiResource('categories', 'CategoryApiController');

    // Contact
    Route::apiResource('contacts', 'ContactApiController');

    // Work Shift
    Route::apiResource('work-shifts', 'WorkShiftApiController');

    Route::post('/send-notification', [App\Http\Controllers\NotificationController::class, 'sendNotification']);

    Route::get('translations/ar', [JsonEditorController::class, 'getArJson'])->name('translations.ar');
    Route::get('translations/en', [JsonEditorController::class, 'getEnJson'])->name('translations.en');
});
