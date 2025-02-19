<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGaragesTable extends Migration
{
    public function up()
    {
        Schema::create('garages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('description');
            $table->string('service_description')->nullable();
            $table->string('contact_no');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('latitude');
            $table->string('longitude')->nullable();
            $table->string('working_hours');
            $table->string('opening_time');
            $table->string('closing_time');
            $table->string('status');
            $table->integer('rating')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
