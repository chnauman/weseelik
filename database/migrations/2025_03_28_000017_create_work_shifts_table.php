<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkShiftsTable extends Migration
{
    public function up()
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->time('opening_time');
            $table->time('closing_time');
            $table->string('day_of_week')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
