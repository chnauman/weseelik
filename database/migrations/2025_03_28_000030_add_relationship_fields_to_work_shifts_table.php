<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToWorkShiftsTable extends Migration
{
    public function up()
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('garage_id')->nullable();
            $table->foreign('garage_id', 'garage_fk_10513394')->references('id')->on('garages');
        });
    }
}
