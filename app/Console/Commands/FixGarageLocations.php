<?php

namespace App\Console\Commands;

use App\Models\Garage;
use App\Services\MapGarageTitleMatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FixGarageLocations extends Command
{
    protected $signature = 'garage:fix-locations
                            {--dry-run : Show what would be updated without saving}
                            {--limit= : Limit number of garages to process}';

    protected $description = 'Update red-pin garages only: search by title in Kuwait, take first result, update maps_share_link and coordinates. Green-pin garages are left unchanged.';

    private const KUWAIT_CENTER_LAT = 29.3759;
    private const KUWAIT_CENTER_LNG = 47.9774;
    private const DELAY_SECONDS = 2;

    public function __construct(
        private MapGarageTitleMatchService $titleMatchService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $placesKey = env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY'));

        if (empty($placesKey)) {
            $this->error('Set GOOGLE_PLACES_API_KEY or GOOGLE_MAPS_API_KEY in .env');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN: No changes will be saved.');
        }

        $query = Garage::whereNull('deleted_at')->orderBy('id');
        if ($limit) {
            $query->limit($limit);
        }
        $garages = $query->get();
        $total = $garages->count();

        if ($total === 0) {
            $this->info('No garages to process.');
            return Command::SUCCESS;
        }

        $this->info("Processing {$total} garage(s). Green-pin garages are skipped; only red-pin garages are updated.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $skippedGreen = 0;
        $skippedNoResult = 0;
        $failed = 0;

        foreach ($garages as $garage) {
            try {
                // Leave green-pin garages unchanged
                if ($this->titleMatchService->titleMatches($garage)) {
                    $skippedGreen++;
                    $bar->advance();
                    if ($total > 1) {
                        sleep(self::DELAY_SECONDS);
                    }
                    continue;
                }

                $result = $this->resolveLocationForRedGarage($garage, $placesKey);
                if ($result === null) {
                    $skippedNoResult++;
                    $bar->advance();
                    if ($total > 1) {
                        sleep(self::DELAY_SECONDS);
                    }
                    continue;
                }
                if ($result === false) {
                    $failed++;
                    $bar->advance();
                    if ($total > 1) {
                        sleep(self::DELAY_SECONDS);
                    }
                    continue;
                }

                if (!$dryRun) {
                    $garage->update($result);
                    Cache::forget('map_title_match_' . $garage->id);
                }
                $updated++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('FixGarageLocations garage ' . $garage->id . ': ' . $e->getMessage());
            }

            $bar->advance();
            if ($total > 1) {
                sleep(self::DELAY_SECONDS);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated (red pins)', $updated],
                ['Skipped (green pin – left unchanged)', $skippedGreen],
                ['Skipped (no result for title search)', $skippedNoResult],
                ['Failed', $failed],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Resolve location for a red-pin garage: search by title in Kuwait, take first result.
     * Returns update array, null to skip (no title / no result), false on failure.
     */
    private function resolveLocationForRedGarage(Garage $garage, string $placesKey): array|null|false
    {
        $queryString = $this->buildTitleSearchQuery($garage);
        if ($queryString === '') {
            return null;
        }

        $place = $this->placesTextSearch($queryString, $placesKey);
        if ($place === null) {
            return null;
        }

        $location = $place['geometry']['location'] ?? null;
        $name = $place['name'] ?? null;
        $placeId = $place['place_id'] ?? null;
        $formattedAddress = $place['formatted_address'] ?? $place['vicinity'] ?? null;

        if (!$location || !isset($location['lat'], $location['lng'])) {
            return false;
        }

        $confidence = $this->nameSimilarity($garage->title ?? '', $name ?? '');
        $verified = $this->verifiedFromConfidence($confidence);

        $mapsLink = $this->buildMapsShareLink(
            $name,
            $formattedAddress,
            $placeId,
            (float) $location['lat'],
            (float) $location['lng']
        );

        return [
            'latitude' => (string) $location['lat'],
            'longitude' => (string) $location['lng'],
            'google_place_id' => $placeId,
            'is_google_maps' => true,
            'maps_share_link' => $mapsLink,
            'confidence' => $confidence,
            'location_verified' => $verified,
        ];
    }

    /** Search query for red garages: "{title}, Kuwait" only. */
    private function buildTitleSearchQuery(Garage $garage): string
    {
        $title = trim((string) ($garage->title ?? ''));
        if ($title === '') {
            return '';
        }
        return $title . ', Kuwait';
    }

    private function placesTextSearch(string $query, string $apiKey): ?array
    {
        $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json';
        $params = [
            'query' => $query,
            'location' => self::KUWAIT_CENTER_LAT . ',' . self::KUWAIT_CENTER_LNG,
            'key' => $apiKey,
        ];

        try {
            $response = Http::timeout(15)->get($url, $params);
            if (!$response->successful()) {
                return null;
            }
            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) {
                return null;
            }
            return $data['results'][0];
        } catch (\Throwable $e) {
            Log::warning('Places Text Search error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Name similarity 0–100 using normalized strings and similar_text.
     */
    private function nameSimilarity(string $name1, string $name2): int
    {
        $a = $this->normalizeNameForMatch($name1);
        $b = $this->normalizeNameForMatch($name2);
        if ($a === '' || $b === '') {
            return 0;
        }
        similar_text($a, $b, $percent);
        $forward = (float) $percent;
        similar_text($b, $a, $percent);
        $reverse = (float) $percent;
        return (int) round(max($forward, $reverse));
    }

    private function normalizeNameForMatch(string $name): string
    {
        $name = trim($name);
        $name = mb_strtolower($name);
        $name = preg_replace('/\s+/', ' ', $name);
        $remove = ['garage', 'workshop', 'car repair', 'the', 'ـ', '،', ','];
        foreach ($remove as $word) {
            $name = preg_replace('/\b' . preg_quote($word, '/') . '\b/u', ' ', $name);
        }
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }

    private function verifiedFromConfidence(int $confidence): string
    {
        if ($confidence >= 80) {
            return 'verified';
        }
        if ($confidence >= 50) {
            return 'human_verification_required';
        }
        return 'unverified';
    }

    private function buildMapsShareLink(
        ?string $name,
        ?string $address,
        ?string $placeId,
        float $lat,
        float $lng
    ): ?string {
        if ($name && $address) {
            return 'https://www.google.com/maps/search/?api=1&query='
                . urlencode($name) . ',' . urlencode($address);
        }
        if ($placeId && $name) {
            return 'https://www.google.com/maps/search/?api=1&query='
                . urlencode($name) . '&query_place_id=' . urlencode($placeId);
        }
        if ($placeId) {
            return 'https://www.google.com/maps/place/?q=place_id:' . urlencode($placeId);
        }
        return 'https://www.google.com/maps/search/?api=1&query=' . $lat . ',' . $lng;
    }
}
