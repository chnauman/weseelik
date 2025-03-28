<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGarageServicePivotTable extends Migration
{
    public function up()
    {
        Schema::create('garage_service', function (Blueprint $table) {
            $table->unsignedBigInteger('garage_id');
            $table->foreign('garage_id', 'garage_id_fk_10450087')->references('id')->on('garages')->onDelete('cascade');
            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id', 'service_id_fk_10450087')->references('id')->on('services')->onDelete('cascade');
        });
    }
}
