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
    Route::apiResource('garages', 'GaragesApiController');

    // Vehicle Brand
    Route::post('vehicle-brands/media', 'VehicleBrandApiController@storeMedia')->name('vehicle-brands.storeMedia');
    Route::apiResource('vehicle-brands', 'VehicleBrandApiController');

    // User Favorite
    Route::apiResource('user-favorites', 'UserFavoriteApiController');

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
