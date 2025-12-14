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
            $table->string('title_ar')->nullable()->after('title');
            $table->text('description_ar')->nullable()->after('description');
            $table->boolean('is_google_maps')->default(false)->after('registered_by');
            $table->string('google_place_id')->nullable()->after('is_google_maps');
            
            // Add index for google_place_id for faster duplicate checking
            $table->index('google_place_id');
            $table->index('is_google_maps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->dropIndex(['google_place_id']);
            $table->dropIndex(['is_google_maps']);
            $table->dropColumn(['title_ar', 'description_ar', 'is_google_maps', 'google_place_id']);
        });
    }
};

