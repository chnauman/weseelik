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
            $table->integer('confidence')->nullable()->default(null)->comment('Match percentage from 0 to 100');
            $table->enum('location_verified', ['verified', 'human_verification_required', 'unverified'])
                  ->nullable()
                  ->default('unverified')
                  ->comment('Location verification status based on confidence score');
            
            // Add indexes for better query performance
            $table->index('confidence');
            $table->index('location_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->dropIndex(['confidence']);
            $table->dropIndex(['location_verified']);
            $table->dropColumn(['confidence', 'location_verified']);
        });
    }
};

