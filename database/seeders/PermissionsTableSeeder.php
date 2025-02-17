<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            [
                'id'    => 1,
                'title' => 'user_management_access',
            ],
            [
                'id'    => 2,
                'title' => 'permission_create',
            ],
            [
                'id'    => 3,
                'title' => 'permission_edit',
            ],
            [
                'id'    => 4,
                'title' => 'permission_show',
            ],
            [
                'id'    => 5,
                'title' => 'permission_delete',
            ],
            [
                'id'    => 6,
                'title' => 'permission_access',
            ],
            [
                'id'    => 7,
                'title' => 'role_create',
            ],
            [
                'id'    => 8,
                'title' => 'role_edit',
            ],
            [
                'id'    => 9,
                'title' => 'role_show',
            ],
            [
                'id'    => 10,
                'title' => 'role_delete',
            ],
            [
                'id'    => 11,
                'title' => 'role_access',
            ],
            [
                'id'    => 12,
                'title' => 'user_create',
            ],
            [
                'id'    => 13,
                'title' => 'user_edit',
            ],
            [
                'id'    => 14,
                'title' => 'user_show',
            ],
            [
                'id'    => 15,
                'title' => 'user_delete',
            ],
            [
                'id'    => 16,
                'title' => 'user_access',
            ],
            [
                'id'    => 17,
                'title' => 'service_create',
            ],
            [
                'id'    => 18,
                'title' => 'service_edit',
            ],
            [
                'id'    => 19,
                'title' => 'service_show',
            ],
            [
                'id'    => 20,
                'title' => 'service_delete',
            ],
            [
                'id'    => 21,
                'title' => 'service_access',
            ],
            [
                'id'    => 22,
                'title' => 'language_create',
            ],
            [
                'id'    => 23,
                'title' => 'language_edit',
            ],
            [
                'id'    => 24,
                'title' => 'language_show',
            ],
            [
                'id'    => 25,
                'title' => 'language_delete',
            ],
            [
                'id'    => 26,
                'title' => 'language_access',
            ],
            [
                'id'    => 27,
                'title' => 'tag_create',
            ],
            [
                'id'    => 28,
                'title' => 'tag_edit',
            ],
            [
                'id'    => 29,
                'title' => 'tag_show',
            ],
            [
                'id'    => 30,
                'title' => 'tag_delete',
            ],
            [
                'id'    => 31,
                'title' => 'tag_access',
            ],
            [
                'id'    => 32,
                'title' => 'country_create',
            ],
            [
                'id'    => 33,
                'title' => 'country_edit',
            ],
            [
                'id'    => 34,
                'title' => 'country_show',
            ],
            [
                'id'    => 35,
                'title' => 'country_delete',
            ],
            [
                'id'    => 36,
                'title' => 'country_access',
            ],
            [
                'id'    => 37,
                'title' => 'specialization_create',
            ],
            [
                'id'    => 38,
                'title' => 'specialization_edit',
            ],
            [
                'id'    => 39,
                'title' => 'specialization_show',
            ],
            [
                'id'    => 40,
                'title' => 'specialization_delete',
            ],
            [
                'id'    => 41,
                'title' => 'specialization_access',
            ],
            [
                'id'    => 42,
                'title' => 'garage_create',
            ],
            [
                'id'    => 43,
                'title' => 'garage_edit',
            ],
            [
                'id'    => 44,
                'title' => 'garage_show',
            ],
            [
                'id'    => 45,
                'title' => 'garage_delete',
            ],
            [
                'id'    => 46,
                'title' => 'garage_access',
            ],
            [
                'id'    => 47,
                'title' => 'vehicle_brand_create',
            ],
            [
                'id'    => 48,
                'title' => 'vehicle_brand_edit',
            ],
            [
                'id'    => 49,
                'title' => 'vehicle_brand_show',
            ],
            [
                'id'    => 50,
                'title' => 'vehicle_brand_delete',
            ],
            [
                'id'    => 51,
                'title' => 'vehicle_brand_access',
            ],
            [
                'id'    => 52,
                'title' => 'user_favorite_create',
            ],
            [
                'id'    => 53,
                'title' => 'user_favorite_edit',
            ],
            [
                'id'    => 54,
                'title' => 'user_favorite_show',
            ],
            [
                'id'    => 55,
                'title' => 'user_favorite_delete',
            ],
            [
                'id'    => 56,
                'title' => 'user_favorite_access',
            ],
            [
                'id'    => 57,
                'title' => 'message_create',
            ],
            [
                'id'    => 58,
                'title' => 'message_edit',
            ],
            [
                'id'    => 59,
                'title' => 'message_show',
            ],
            [
                'id'    => 60,
                'title' => 'message_delete',
            ],
            [
                'id'    => 61,
                'title' => 'message_access',
            ],
            [
                'id'    => 62,
                'title' => 'booking_create',
            ],
            [
                'id'    => 63,
                'title' => 'booking_edit',
            ],
            [
                'id'    => 64,
                'title' => 'booking_show',
            ],
            [
                'id'    => 65,
                'title' => 'booking_delete',
            ],
            [
                'id'    => 66,
                'title' => 'booking_access',
            ],
            [
                'id'    => 67,
                'title' => 'profile_password_edit',
            ],
        ];

        Permission::insert($permissions);
    }
}
