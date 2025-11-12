<?php

namespace App\Console\Commands;

use App\Services\GooglePlacesScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScrapeGooglePlaces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:google-places 
                            {query : The search query (e.g., "car garage in Kuwait")}
                            {--location= : Location bias (e.g., "Kuwait")}
                            {--limit= : Maximum number of places to scrape}
                            {--no-images : Skip downloading images}
                            {--delay=1 : Delay between requests in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape garage data from Google Places API and save to garages_scraped table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = $this->argument('query');
        $location = $this->option('location');
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;
        $downloadImages = !$this->option('no-images');
        $delay = (int)$this->option('delay');

        $this->info("Starting Google Places scraper...");
        $this->info("Query: {$query}");
        
        if ($location) {
            $this->info("Location: {$location}");
        }
        
        if ($limit) {
            $this->info("Limit: {$limit} places");
        }
        
        $this->info("Download images: " . ($downloadImages ? 'Yes' : 'No'));
        $this->info("Delay between requests: {$delay} seconds");
        $this->newLine();

        try {
            $scraper = new GooglePlacesScraperService();
            $scraper->setDelay($delay);

            $this->info("Searching for places...");
            $results = $scraper->scrapePlaces($query, $location, $downloadImages, $limit);

            $this->newLine();
            $this->info("Scraping completed!");
            $this->info("Successfully scraped: " . count($results) . " places");

            if (count($results) > 0) {
                $this->newLine();
                $this->table(
                    ['ID', 'Title', 'Address', 'Rating', 'Status'],
                    array_map(function ($garage) {
                        return [
                            $garage->id,
                            $garage->title,
                            $garage->address ?? 'N/A',
                            $garage->rating ?? 'N/A',
                            $garage->scraping_status,
                        ];
                    }, $results)
                );
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("ScrapeGooglePlaces command error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

