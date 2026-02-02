<?php

namespace App\Http\Controllers;

use App\Models\Garage;
use App\Services\MapGarageTitleMatchService;
use Illuminate\Http\Response;

class MapController extends Controller
{
    public function __construct(
        private MapGarageTitleMatchService $titleMatchService
    ) {}

    /**
     * Public map of Kuwait with garage pins (green = title match, red = no match).
     */
    public function index(): Response
    {
        $garages = Garage::query()
            ->whereNull('deleted_at')
            ->whereNotNull('maps_share_link')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('title')
            ->get();

        $markers = $garages->map(function (Garage $garage) {
            return [
                'id' => $garage->id,
                'lat' => (float) $garage->latitude,
                'lng' => (float) $garage->longitude,
                'title' => $garage->title ?? '',
                'title_ar' => $garage->title_ar ?? '',
                'maps_share_link' => $garage->maps_share_link,
                'title_matches' => $this->titleMatchService->titleMatches($garage),
            ];
        })->values()->all();

        $mapKey = env('GOOGLE_MAPS_API_KEY');

        // Use escaped Unicode (\uXXXX) so JSON is ASCII-only; avoids encoding issues with Arabic in HTML
        $markersJson = json_encode($markers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return response()
            ->view('maps.index', [
                'markers' => $markers,
                'markersJson' => $markersJson,
                'mapKey' => $mapKey,
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
