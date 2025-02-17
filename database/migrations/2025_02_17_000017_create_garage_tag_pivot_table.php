<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGarageTagPivotTable extends Migration
{
    public function up()
    {
        Schema::create('garage_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('garage_id');
            $table->foreign('garage_id', 'garage_id_fk_10450084')->references('id')->on('garages')->onDelete('cascade');
            $table->unsignedBigInteger('tag_id');
            $table->foreign('tag_id', 'tag_id_fk_10450084')->references('id')->on('tags')->onDelete('cascade');
        });
    }
}
