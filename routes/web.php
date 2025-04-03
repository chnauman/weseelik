<?php

use App\Models\User;

Route::redirect('/', '/login');
Route::get('/home', function () {
    if (session('status')) {
        return redirect()->route('admin.home')->with('status', session('status'));
    }

    return redirect()->route('admin.home');
});

Auth::routes(['register' => false]);

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'namespace' => 'Admin', 'middleware' => ['auth']], function () {
    Route::get('/', 'HomeController@index')->name('home');
    // Permissions
    Route::delete('permissions/destroy', 'PermissionsController@massDestroy')->name('permissions.massDestroy');
    Route::resource('permissions', 'PermissionsController');

    // Roles
    Route::delete('roles/destroy', 'RolesController@massDestroy')->name('roles.massDestroy');
    Route::resource('roles', 'RolesController');

    // Users
    Route::delete('users/destroy', 'UsersController@massDestroy')->name('users.massDestroy');
    Route::resource('users', 'UsersController');

    // Service
    Route::delete('services/destroy', 'ServiceController@massDestroy')->name('services.massDestroy');
    Route::resource('services', 'ServiceController');

    // Language
    Route::delete('languages/destroy', 'LanguageController@massDestroy')->name('languages.massDestroy');
    Route::resource('languages', 'LanguageController');

    // Tags
    Route::delete('tags/destroy', 'TagsController@massDestroy')->name('tags.massDestroy');
    Route::resource('tags', 'TagsController');

    // Countries
    Route::delete('countries/destroy', 'CountriesController@massDestroy')->name('countries.massDestroy');
    Route::resource('countries', 'CountriesController');

    // Specialization
    Route::delete('specializations/destroy', 'SpecializationController@massDestroy')->name('specializations.massDestroy');
    Route::resource('specializations', 'SpecializationController');

    // Garages
    Route::delete('garages/destroy', 'GaragesController@massDestroy')->name('garages.massDestroy');
    Route::post('garages/media', 'GaragesController@storeMedia')->name('garages.storeMedia');
    Route::post('garages/ckmedia', 'GaragesController@storeCKEditorImages')->name('garages.storeCKEditorImages');
    Route::resource('garages', 'GaragesController');

    // Vehicle Brand
    Route::delete('vehicle-brands/destroy', 'VehicleBrandController@massDestroy')->name('vehicle-brands.massDestroy');
    Route::post('vehicle-brands/media', 'VehicleBrandController@storeMedia')->name('vehicle-brands.storeMedia');
    Route::post('vehicle-brands/ckmedia', 'VehicleBrandController@storeCKEditorImages')->name('vehicle-brands.storeCKEditorImages');
    Route::resource('vehicle-brands', 'VehicleBrandController');

    // User Favorite
    Route::delete('user-favorites/destroy', 'UserFavoriteController@massDestroy')->name('user-favorites.massDestroy');
    Route::resource('user-favorites', 'UserFavoriteController');

    // Message
    Route::delete('messages/destroy', 'MessageController@massDestroy')->name('messages.massDestroy');
    Route::resource('messages', 'MessageController');

    // Booking
    Route::delete('bookings/destroy', 'BookingController@massDestroy')->name('bookings.massDestroy');
    Route::resource('bookings', 'BookingController');

    // Category
    Route::delete('categories/destroy', 'CategoryController@massDestroy')->name('categories.massDestroy');
    Route::resource('categories', 'CategoryController');

    // Contact
    Route::delete('contacts/destroy', 'ContactController@massDestroy')->name('contacts.massDestroy');
    Route::resource('contacts', 'ContactController');

    // Work Shift
    Route::delete('work-shifts/destroy', 'WorkShiftController@massDestroy')->name('work-shifts.massDestroy');
    Route::resource('work-shifts', 'WorkShiftController');
});
Route::group(['prefix' => 'profile', 'as' => 'profile.', 'namespace' => 'Auth', 'middleware' => ['auth']], function () {
    // Change password
    if (file_exists(app_path('Http/Controllers/Auth/ChangePasswordController.php'))) {
        Route::get('password', 'ChangePasswordController@edit')->name('password.edit');
        Route::post('password', 'ChangePasswordController@update')->name('password.update');
        Route::post('profile', 'ChangePasswordController@updateProfile')->name('password.updateProfile');
        Route::post('profile/destroy', 'ChangePasswordController@destroy')->name('password.destroyProfile');
    }
});


Route::get('/token', function () {
   $user = User::find(1);// Auth::user(1);
   $token = $user->createToken('auth_token')->plainTextToken;
   echo $token;
});
