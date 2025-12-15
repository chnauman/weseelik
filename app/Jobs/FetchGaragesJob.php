<?php

namespace App\Jobs;

use App\Models\Garage;
use App\Services\GooglePlacesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FetchGaragesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $area;
    public float $lat;
    public float $lng;
    public string $jobId;

    public $timeout = 600; // 10 minutes timeout for the job

    /**
     * Create a new job instance.
     */
    public function __construct(string $area, float $lat, float $lng)
    {
        $this->area = $area;
        $this->lat = $lat;
        $this->lng = $lng;
        $this->jobId = uniqid('fetch_garages_', true);
    }

    /**
     * Execute the job.
     */
    public function handle(GooglePlacesService $googlePlacesService): void
    {
        $garages = [];
        $stats = [
            'processed' => 0,
            'new' => 0,
            'skipped' => 0,
            'failed' => 0,
            'status' => 'processing'
        ];

        // Store initial status
        Cache::put("fetch_garages_{$this->jobId}", $stats, 3600); // 1 hour cache

        try {
            $googlePlacesService->fetchAndStoreGarages(
                $this->area,
                $this->lat,
                $this->lng,
                function ($garage, $status) use (&$garages, &$stats) {
                    $stats['processed']++;
                    
                    if ($status === 'created' && $garage) {
                        $stats['new']++;
                        // Format garage for frontend
                        $garages[] = [
                            'id' => $garage->id,
                            'title' => $garage->title,
                            'title_ar' => $garage->title_ar,
                            'address' => $garage->address,
                            'contact_no' => $garage->contact_no,
                            'rating' => $garage->rating,
                            'service_description' => $garage->service_description,
                            'maps_share_link' => $garage->maps_share_link,
                        ];
                        
                        // Update cache immediately after each garage is created for real-time display
                        $stats['status'] = 'processing';
                        Cache::put("fetch_garages_{$this->jobId}", array_merge($stats, ['garages' => $garages]), 3600);
                    } elseif ($status === 'skipped') {
                        $stats['skipped']++;
                        // Update cache for skipped items too to show progress
                        $stats['status'] = 'processing';
                        Cache::put("fetch_garages_{$this->jobId}", array_merge($stats, ['garages' => $garages]), 3600);
                    } elseif ($status === 'failed') {
                        $stats['failed']++;
                        // Update cache for failed items too
                        $stats['status'] = 'processing';
                        Cache::put("fetch_garages_{$this->jobId}", array_merge($stats, ['garages' => $garages]), 3600);
                    }
                }
            );

            // Mark as completed
            $stats['status'] = 'completed';
            Cache::put("fetch_garages_{$this->jobId}", array_merge($stats, [
                'garages' => $garages,
                'message' => "Fetched {$stats['new']} new garages, skipped {$stats['skipped']} duplicates"
            ]), 3600);

        } catch (\Exception $e) {
            Log::error('Error in FetchGaragesJob: ' . $e->getMessage());
            $stats['status'] = 'failed';
            $stats['error'] = $e->getMessage();
            Cache::put("fetch_garages_{$this->jobId}", $stats, 3600);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $stats = [
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'processed' => 0,
            'new' => 0,
            'skipped' => 0,
            'failed' => 0
        ];
        Cache::put("fetch_garages_{$this->jobId}", $stats, 3600);
    }
}

