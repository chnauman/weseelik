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
            Schema::table('garages', function (Blueprint $table) {
                $table->string('maps_share_link')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            Schema::table('garages', function (Blueprint $table) {
                $table->dropColumn('maps_share_link');
            });
        });
    }
};
