<?php

Route::group(['prefix' => 'v1', 'as' => 'api.', 'namespace' => 'Api\V1\Admin', 'middleware' => ['auth:sanctum']], function () {
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
});
