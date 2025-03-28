<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToBookingsTable extends Migration
{
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id', 'user_fk_10450132')->references('id')->on('users');
            $table->unsignedBigInteger('garage_id')->nullable();
            $table->foreign('garage_id', 'garage_fk_10450133')->references('id')->on('users');
        });
    }
}
