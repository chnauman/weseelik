<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTagIdToVehicleBrandsTable extends Migration
{
    public function up()
    {
        Schema::table('vehicle_brands', function (Blueprint $table) {
            $table->unsignedBigInteger('tag_id')->nullable()->after('title');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('vehicle_brands', function (Blueprint $table) {
            $table->dropForeign(['tag_id']);
            $table->dropColumn('tag_id');
        });
    }
} 