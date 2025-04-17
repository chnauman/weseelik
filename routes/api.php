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
    Route::get('garages/top-visited', 'GaragesApiController@topVisited')->name('garages.topVisited');
    Route::get('garages/nearby', 'GaragesApiController@nearby')->name('garages.nearby');
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('garages/get-blocked-garages', 'UserBlockApiController@index')->name('garages.index');
        Route::post('garages/block', 'UserBlockApiController@blockGarage')->name('garages.block');
        Route::post('garages/unblock', 'UserBlockApiController@unblockGarage')->name('garages.unblock');
        Route::get('garages/is-blocked', 'UserBlockApiController@isBlocked')->name('garages.isBlocked');

        Route::get('garages/by-user', 'GaragesApiController@getUserGarages')->name('garages.getUserGarages');
    });
    Route::apiResource('garages', 'GaragesApiController');

    // Vehicle Brand
    Route::post('vehicle-brands/media', 'VehicleBrandApiController@storeMedia')->name('vehicle-brands.storeMedia');
    Route::apiResource('vehicle-brands', 'VehicleBrandApiController');

    // User Favorite
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('user-favorites/add', 'UserFavoriteApiController@addToFavorites')->name('user-favorites.add');
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
    Route::apiResource('bookings', 'BookingApiController');

    // Category
    Route::apiResource('categories', 'CategoryApiController');

    // Contact
    Route::apiResource('contacts', 'ContactApiController');

    // Work Shift
    Route::apiResource('work-shifts', 'WorkShiftApiController');
});
