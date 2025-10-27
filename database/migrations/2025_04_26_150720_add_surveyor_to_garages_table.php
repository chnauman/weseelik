<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->unsignedBigInteger('surveyor_id')->nullable()->after('user_id');
            $table->foreign('surveyor_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->dropForeign(['surveyor_id']);
            $table->dropColumn('surveyor_id');
        });
    }
};
