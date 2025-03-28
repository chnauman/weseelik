<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingsTable extends Migration
{
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reference_no')->unique();
            $table->datetime('appointment_time');
            $table->string('status')->nullable();
            $table->string('processing_status')->nullable();
            $table->string('garage_remarks')->nullable();
            $table->string('client_remarks')->nullable();
            $table->integer('client_rating')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
