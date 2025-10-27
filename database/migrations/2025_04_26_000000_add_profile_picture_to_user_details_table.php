<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProfilePictureToUserDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('user_details', function (Blueprint $table) {
            //$table->string('avatar')->nullable()->after('longitude');
        });
    }

    public function down()
    {
        Schema::table('user_details', function (Blueprint $table) {
            //$table->dropColumn('avatar');
        });
    }
}
