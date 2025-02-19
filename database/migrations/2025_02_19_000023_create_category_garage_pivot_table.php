<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryGaragePivotTable extends Migration
{
    public function up()
    {
        Schema::create('category_garage', function (Blueprint $table) {
            $table->unsignedBigInteger('garage_id');
            $table->foreign('garage_id', 'garage_id_fk_10454329')->references('id')->on('garages')->onDelete('cascade');
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id', 'category_id_fk_10454329')->references('id')->on('categories')->onDelete('cascade');
        });
    }
}
