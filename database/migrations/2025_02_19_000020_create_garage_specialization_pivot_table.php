<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGarageSpecializationPivotTable extends Migration
{
    public function up()
    {
        Schema::create('garage_specialization', function (Blueprint $table) {
            $table->unsignedBigInteger('garage_id');
            $table->foreign('garage_id', 'garage_id_fk_10450086')->references('id')->on('garages')->onDelete('cascade');
            $table->unsignedBigInteger('specialization_id');
            $table->foreign('specialization_id', 'specialization_id_fk_10450086')->references('id')->on('specializations')->onDelete('cascade');
        });
    }
}
