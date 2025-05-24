<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;



// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
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

    // Garages
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

        Route::get('garages/by-user', 'GaragesApiController@getUserGarages')->name('garages.getUserGarages');

        Route::post('/verify-phone', [AuthController::class, 'verifyPhone']);
        Route::post('/update-user-details', [AuthController::class, 'updateUserDetails']);
        Route::get('/user-profile', [AuthController::class, 'getUserProfile']);
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

    // Booking
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('bookings/create', 'BookingApiController@createBooking')->name('bookings.createBooking');
        Route::apiResource('bookings', 'BookingApiController');
    });

    // Category
    Route::apiResource('categories', 'CategoryApiController');

    // Contact
    Route::apiResource('contacts', 'ContactApiController');

    // Work Shift
    Route::apiResource('work-shifts', 'WorkShiftApiController');
});
