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
        Schema::create('user_vehicle_brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('vehicle_brand_id');
            $table->boolean('is_primary')->default(false); // Flag to mark a vehicle as primary
            $table->string('model')->nullable(); // Optional model information
            $table->string('year')->nullable(); // Optional year of manufacture
            $table->string('color')->nullable(); // Optional color
            $table->string('license_plate')->nullable(); // Optional license plate
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key relationships
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('vehicle_brand_id')->references('id')->on('vehicle_brands')->onDelete('cascade');
            
            // Prevent duplicate vehicle brands for a user
            $table->unique(['user_id', 'vehicle_brand_id', 'license_plate'], 'user_vehicle_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_vehicle_brands');
    }
};
