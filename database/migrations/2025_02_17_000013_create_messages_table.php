<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('message');
            $table->string('status');
            $table->integer('tagged_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
