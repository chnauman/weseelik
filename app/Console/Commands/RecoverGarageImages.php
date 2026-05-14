<?php

namespace App\Console\Commands;

use App\Models\Garage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecoverGarageImages extends Command
{
    protected $signature = 'garages:recover-images
                            {--dry-run : Show what would be done without downloading anything}
                            {--garage= : Only process a specific garage ID}
                            {--skip-existing : Skip garages that already have at least one image}
                            {--force : Re-download even if garage already has images}
                            {--strategy=auto : Image strategy: auto|places|streetview|fallback}';

    protected $description = 'Recover garage images using Google Places Photos and/or Street View';

    private string $apiKey;

    // Strategy precedence (auto mode):
    //   1. Google Places Photos  — if google_place_id is set
    //   2. Street View Static    — if lat/lng are set
    //   3. Fallback PNG          — always available

    public function handle(): int
    {
        $this->apiKey = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));

        if (empty($this->apiKey)) {
            $this->error('GOOGLE_PLACES_API_KEY or GOOGLE_MAPS_API_KEY is not set in .env');
            return 1;
        }

        $isDryRun  = $this->option('dry-run');
        $force     = $this->option('force');
        $strategy  = $this->option('strategy'); // auto|places|streetview|fallback

        $query = Garage::query();
        if ($this->option('garage')) {
            $query->where('id', $this->option('garage'));
        }
        $garages = $query->get();

        $this->info("Found {$garages->count()} garage(s). Strategy: {$strategy}");
        if ($isDryRun) {
            $this->warn('DRY-RUN — nothing will be downloaded.');
        }

        $stats = ['places' => 0, 'streetview' => 0, 'fallback' => 0, 'skipped' => 0, 'failed' => 0];
        $bar   = $this->output->createProgressBar($garages->count());
        $bar->start();

        foreach ($garages as $garage) {
            $bar->advance();

            // Skip if already has valid images and not forced
            if (!$force && !$isDryRun && $this->hasValidImages($garage)) {
                if ($this->option('skip-existing')) {
                    $stats['skipped']++;
                    continue;
                }
            }

            if (!$isDryRun) {
                $this->cleanBrokenMedia($garage);
                // Refresh so getMedia() sees the cleaned state
                $garage->refresh();
            }

            $used = $this->processGarage($garage, $strategy, $isDryRun);
            $stats[$used]++;

            if ($used === 'failed') {
                $this->line("\n  [FAIL]       #{$garage->id} {$garage->title}");
            } else {
                $this->line("\n  [{$used}] #{$garage->id} {$garage->title}");
            }

            if (!$isDryRun && $used !== 'fallback') {
                sleep(1); // Respectful API rate limiting
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Strategy Used', 'Count'], [
            ['Google Places Photos', $stats['places']],
            ['Street View Static',   $stats['streetview']],
            ['Fallback PNG',         $stats['fallback']],
            ['Skipped (has images)', $stats['skipped']],
            ['Failed',               $stats['failed']],
        ]);

        return 0;
    }

    // -------------------------------------------------------------------------
    // Core dispatcher
    // -------------------------------------------------------------------------

    private function processGarage(Garage $garage, string $strategy, bool $dry): string
    {
        $hasPlaceId = !empty($garage->google_place_id);
        $hasLatLng  = !empty($garage->latitude) && !empty($garage->longitude);

        if ($strategy === 'auto') {
            if ($hasPlaceId)  return $this->tryPlaces($garage, $dry) ? 'places'     : ($hasLatLng ? ($this->tryStreetView($garage, $dry) ? 'streetview' : $this->doFallback($garage, $dry)) : $this->doFallback($garage, $dry));
            if ($hasLatLng)   return $this->tryStreetView($garage, $dry) ? 'streetview' : $this->doFallback($garage, $dry);
            return $this->doFallback($garage, $dry);
        }

        if ($strategy === 'places')     return $this->tryPlaces($garage, $dry)      ? 'places'      : 'failed';
        if ($strategy === 'streetview') return $this->tryStreetView($garage, $dry)  ? 'streetview'  : 'failed';
        if ($strategy === 'fallback')   return $this->doFallback($garage, $dry);

        return 'failed';
    }

    // -------------------------------------------------------------------------
    // Strategy 1: Google Places Photos
    // -------------------------------------------------------------------------

    private function tryPlaces(Garage $garage, bool $dry): bool
    {
        if (empty($garage->google_place_id)) {
            return false;
        }

        if ($dry) {
            return true; // Assume it would work
        }

        try {
            $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $garage->google_place_id,
                'fields'   => 'photos',
                'key'      => $this->apiKey,
            ]);

            if (!$response->successful() || ($response->json()['status'] ?? '') !== 'OK') {
                return false;
            }

            $photos = $response->json()['result']['photos'] ?? [];

            if (empty($photos)) {
                return false;
            }

            $downloaded = 0;
            $map = [0 => 'logo', 1 => 'storefront_image'];

            foreach (array_slice($photos, 0, 3) as $index => $photo) {
                $ref = $photo['photo_reference'] ?? null;
                if (!$ref) continue;

                $url        = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=1200&photoreference={$ref}&key={$this->apiKey}";
                $collection = $map[$index] ?? 'gallery';
                $filename   = "garage_{$garage->id}_{$collection}.jpg";

                try {
                    $garage->addMediaFromUrl($url)
                        ->usingFileName($filename)
                        ->toMediaCollection($collection);
                    $downloaded++;
                } catch (\Exception $e) {
                    Log::warning("Places photo download failed for garage #{$garage->id}: " . $e->getMessage());
                }

                usleep(400000);
            }

            return $downloaded > 0;

        } catch (\Exception $e) {
            Log::error("tryPlaces exception for garage #{$garage->id}: " . $e->getMessage());
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Strategy 2: Google Street View Static API
    // Returns a real photo of the street at the garage's exact lat/lng.
    // Works for ALL garages that have coordinates — even without a place_id.
    // -------------------------------------------------------------------------

    private function tryStreetView(Garage $garage, bool $dry): bool
    {
        if (empty($garage->latitude) || empty($garage->longitude)) {
            return false;
        }

        if ($dry) {
            return true;
        }

        $lat = $garage->latitude;
        $lng = $garage->longitude;

        // Street View metadata check — confirms a panorama actually exists at this location
        // (avoids downloading a grey "no imagery" placeholder)
        $metaUrl = "https://maps.googleapis.com/maps/api/streetview/metadata?location={$lat},{$lng}&key={$this->apiKey}";
        try {
            $meta = Http::timeout(10)->get($metaUrl)->json();
            if (($meta['status'] ?? '') !== 'OK') {
                Log::info("No Street View imagery at garage #{$garage->id} ({$lat},{$lng})");
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        // Download two angles — front view and a slight turn
        $views = [
            ['heading' => 0,   'collection' => 'logo',             'label' => 'front'],
            ['heading' => 90,  'collection' => 'storefront_image',  'label' => 'side'],
        ];

        $downloaded = 0;

        foreach ($views as $view) {
            $photoUrl = "https://maps.googleapis.com/maps/api/streetview"
                . "?size=1200x800"
                . "&location={$lat},{$lng}"
                . "&fov=80"
                . "&heading={$view['heading']}"
                . "&pitch=0"
                . "&key={$this->apiKey}";

            $filename = "garage_{$garage->id}_streetview_{$view['label']}.jpg";

            try {
                $garage->addMediaFromUrl($photoUrl)
                    ->usingFileName($filename)
                    ->toMediaCollection($view['collection']);
                $downloaded++;
            } catch (\Exception $e) {
                Log::warning("Street View download failed for garage #{$garage->id}: " . $e->getMessage());
            }

            usleep(300000);
        }

        return $downloaded > 0;
    }

    // -------------------------------------------------------------------------
    // Strategy 3: Fallback PNG (already in public/images/)
    // -------------------------------------------------------------------------

    private function doFallback(Garage $garage, bool $dry): string
    {
        if ($dry) {
            return 'fallback';
        }

        $fallbacks = array_filter(array_map(function ($n) {
            return public_path("images/fallback-{$n}.png");
        }, range(1, 10)), 'file_exists');

        if (empty($fallbacks)) {
            return 'failed';
        }

        $fallbacks = array_values($fallbacks);
        $path      = $fallbacks[$garage->id % count($fallbacks)];

        try {
            $garage->addMedia($path)
                ->preservingOriginal()
                ->usingFileName("garage_{$garage->id}_fallback.png")
                ->toMediaCollection('logo');
            return 'fallback';
        } catch (\Exception $e) {
            Log::error("Fallback failed for garage #{$garage->id}: " . $e->getMessage());
            return 'failed';
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function hasValidImages(Garage $garage): bool
    {
        $media = $garage->getMedia('logo')->merge($garage->getMedia('storefront_image'));
        foreach ($media as $m) {
            if ($this->mediaFileExists($m)) {
                return true;
            }
        }
        return false;
    }

    private function cleanBrokenMedia(Garage $garage): void
    {
        foreach (['logo', 'storefront_image', 'gallery'] as $collection) {
            foreach ($garage->getMedia($collection) as $media) {
                if (!$this->mediaFileExists($media)) {
                    try {
                        $media->delete();
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }
        }
    }

    private function mediaFileExists(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): bool
    {
        try {
            return Storage::disk($media->disk ?? 'public')->exists($media->id . '/' . $media->file_name);
        } catch (\Exception $e) {
            return false;
        }
    }
}
