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
        Schema::create('garages_scraped', function (Blueprint $table) {
            // Primary key
            $table->bigIncrements('id');
            
            // Scraping metadata fields (useful for tracking)
            $table->string('google_place_id')->unique()->nullable()->comment('Unique Google Place ID');
            $table->timestamp('scraped_at')->nullable()->comment('When the data was scraped');
            $table->enum('scraping_status', ['pending', 'completed', 'failed'])->default('pending')->comment('Scraping status');
            $table->text('scraping_errors')->nullable()->comment('Any errors encountered during scraping');
            
            // Original garage fields
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('surveyor_id')->nullable();
            $table->string('title');
            $table->string('description');
            $table->string('service_description')->nullable();
            $table->string('contact_no')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('status')->default('pending');
            $table->integer('rating')->nullable();
            $table->string('registered_by')->default('scraper');
            $table->integer('no_of_visits')->default(0);
            $table->integer('no_of_likes')->default(0);
            $table->string('governate')->nullable();
            $table->string('area')->nullable();
            $table->string('block')->nullable();
            $table->string('maps_share_link')->nullable();
            $table->boolean('home_service')->default(false);
            $table->boolean('pickup_service')->default(false);
            $table->boolean('is_available')->default(true);
            $table->integer('confidence')->nullable();
            $table->enum('location_verified', ['verified', 'human_verification_required', 'unverified'])
                  ->nullable()
                  ->default('unverified');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for better query performance
            $table->index('google_place_id');
            $table->index('scraping_status');
            $table->index('scraped_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garages_scraped');
    }
};

