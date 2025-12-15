<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FetchGaragesJob;
use App\Models\Garage;
use App\Services\GooglePlacesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchGaragesController extends Controller
{
    protected $googlePlacesService;

    public function __construct(GooglePlacesService $googlePlacesService)
    {
        $this->googlePlacesService = $googlePlacesService;
    }

    /**
     * Display the fetch garages page
     */
    public function index()
    {
        $kuwaitAreas = $this->getKuwaitAreas();
        return view('admin.fetch-garages.index', compact('kuwaitAreas'));
    }

    /**
     * Fetch garages for selected area via AJAX (dispatches async job)
     */
    public function fetch(Request $request)
    {
        $request->validate([
            'area' => 'required|string',
        ]);

        $area = $request->input('area');
        $coordinates = $this->getAreaCoordinates($area);

        if (!$coordinates) {
            return response()->json([
                'success' => false,
                'message' => 'Coordinates not found for area: ' . $area
            ], 400);
        }

        try {
            // Dispatch job to queue
            $job = new FetchGaragesJob($area, $coordinates['lat'], $coordinates['lng']);
            dispatch($job);

            return response()->json([
                'success' => true,
                'job_id' => $job->jobId,
                'message' => 'Garage fetching started. Please wait while we process the request...'
            ]);

        } catch (\Exception $e) {
            Log::error('Error dispatching fetch garages job: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check job status
     */
    public function status(Request $request)
    {
        $request->validate([
            'job_id' => 'required|string',
        ]);

        $jobId = $request->input('job_id');
        $status = Cache::get("fetch_garages_{$jobId}");

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or expired'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $status['status'] ?? 'unknown',
            'stats' => [
                'processed' => $status['processed'] ?? 0,
                'new' => $status['new'] ?? 0,
                'skipped' => $status['skipped'] ?? 0,
                'failed' => $status['failed'] ?? 0,
            ],
            'garages' => $status['garages'] ?? [],
            'message' => $status['message'] ?? null,
            'error' => $status['error'] ?? null,
        ]);
    }


    /**
     * Get all Kuwait areas
     */
    private function getKuwaitAreas(): array
    {
        return [
            'Kuwait City' => ['lat' => 29.3759, 'lng' => 47.9774],
            'Salmiya' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Hawalli' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Jahra' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Ahmadi' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Farwaniya' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Mubarak Al-Kabeer' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Shuwaikh' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Shuwaikh Industrial' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Dasma' => ['lat' => 29.3333, 'lng' => 48.0333],
            'Salwa' => ['lat' => 29.2958, 'lng' => 48.0819],
            'Mahboula' => ['lat' => 29.1453, 'lng' => 48.1306],
            'Fahaheel' => ['lat' => 29.0831, 'lng' => 48.1306],
            'Mangaf' => ['lat' => 29.1000, 'lng' => 48.1167],
            'Abu Halifa' => ['lat' => 29.1167, 'lng' => 48.1167],
            'Sabah Al-Salem' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Rumaithiya' => ['lat' => 29.3333, 'lng' => 48.0833],
            'Surra' => ['lat' => 29.2667, 'lng' => 48.0333],
            'Yarmouk' => ['lat' => 29.3167, 'lng' => 48.0500],
            'Bayan' => ['lat' => 29.3167, 'lng' => 48.0333],
            'Jabriya' => ['lat' => 29.3333, 'lng' => 48.0500],
            'Salmiya Block 1' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 2' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 3' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 4' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 5' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 6' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 7' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 8' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 9' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 10' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 11' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Salmiya Block 12' => ['lat' => 29.3339, 'lng' => 48.0764],
            'Hawalli Block 1' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 2' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 3' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 4' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 5' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 6' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 7' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 8' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 9' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 10' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 11' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Hawalli Block 12' => ['lat' => 29.3328, 'lng' => 48.0286],
            'Jahra Block 1' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Jahra Block 2' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Jahra Block 3' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Jahra Block 4' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Jahra Block 5' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Jahra Block 6' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Jahra Block 7' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Jahra Block 8' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Jahra Block 9' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Jahra Block 10' => ['lat' => 29.3375, 'lng' => 47.6581],
            'Ahmadi Block 1' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Ahmadi Block 2' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Ahmadi Block 3' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Ahmadi Block 4' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Ahmadi Block 5' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Ahmadi Block 6' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Ahmadi Block 7' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Ahmadi Block 8' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Ahmadi Block 9' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Ahmadi Block 10' => ['lat' => 29.0769, 'lng' => 48.0839],
            'Farwaniya Block 1' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Farwaniya Block 2' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Farwaniya Block 3' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Farwaniya Block 4' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Farwaniya Block 5' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Farwaniya Block 6' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Farwaniya Block 7' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Farwaniya Block 8' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Farwaniya Block 9' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Farwaniya Block 10' => ['lat' => 29.2775, 'lng' => 47.9581],
            'Mubarak Al-Kabeer Block 1' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Mubarak Al-Kabeer Block 2' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Mubarak Al-Kabeer Block 3' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Mubarak Al-Kabeer Block 4' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Mubarak Al-Kabeer Block 5' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Mubarak Al-Kabeer Block 6' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Mubarak Al-Kabeer Block 7' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Mubarak Al-Kabeer Block 8' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Mubarak Al-Kabeer Block 9' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Mubarak Al-Kabeer Block 10' => ['lat' => 29.1453, 'lng' => 48.0947],
            'Shuwaikh Industrial Block 1' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Shuwaikh Industrial Block 2' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Shuwaikh Industrial Block 3' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Shuwaikh Industrial Block 4' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Shuwaikh Industrial Block 5' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Shuwaikh Industrial Block 6' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Shuwaikh Industrial Block 7' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Shuwaikh Industrial Block 8' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Shuwaikh Industrial Block 9' => ['lat' => 29.3458, 'lng' => 47.9208],
            'Shuwaikh Industrial Block 10' => ['lat' => 29.3458, 'lng' => 47.9208],
        ];
    }

    /**
     * Get coordinates for a specific area
     */
    private function getAreaCoordinates(string $area): ?array
    {
        $areas = $this->getKuwaitAreas();
        return $areas[$area] ?? null;
    }

}

