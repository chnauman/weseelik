<?php

namespace App\Http\Controllers\Admin;
use App\Models\Garage;
use App\Models\User;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Http\Request;

class HomeController
{
    public function index()
    {
        // Total Users
        $totalUsers = User::count();
        $usersByType = User::select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->get();

        // Total Garages
        $totalGarages = Garage::count();
        
        // Verified Garages (status = 'approved' AND location_verified = 'verified')
        $verifiedGarages = Garage::where('status', 'approved')
            ->where('location_verified', 'verified')
            ->count();
        
        // Unverified Garages (everything else)
        $unverifiedGarages = $totalGarages - $verifiedGarages;

        // Garages by Status
        $garagesByStatus = Garage::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        // Garages by Location Verification
        $garagesByLocationVerified = Garage::select('location_verified', DB::raw('count(*) as total'))
            ->whereNotNull('location_verified')
            ->groupBy('location_verified')
            ->get();

        // Garages by Area with percentages
        $garagesByArea = Garage::select('area', DB::raw('count(*) as total'))
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();
        
        $totalGaragesWithArea = $garagesByArea->sum('total');
        $garagesByAreaWithPercent = $garagesByArea->map(function($item) use ($totalGaragesWithArea) {
            $item->percentage = $totalGaragesWithArea > 0 ? round(($item->total / $totalGaragesWithArea) * 100, 2) : 0;
            return $item;
        });

        // Paginate the area distribution (10 per page)
        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $perPage = 10;
        $currentItems = $garagesByAreaWithPercent->slice(($currentPage - 1) * $perPage, $perPage)->values();
        
        // Build path for pagination links
        $path = request()->url();
        $query = request()->query();
        unset($query['area_pagination']); // Remove area_pagination from query to avoid duplication
        
        $garagesByAreaPaginated = new LengthAwarePaginator(
            $currentItems,
            $garagesByAreaWithPercent->count(),
            $perPage,
            $currentPage,
            ['path' => $path, 'query' => $query]
        );

        // Garages by Tag
        $garagesByTag = DB::table('garage_tag')
            ->join('tags', 'garage_tag.tag_id', '=', 'tags.id')
            ->select('tags.id', 'tags.title', DB::raw('count(*) as total'))
            ->groupBy('tags.id', 'tags.title')
            ->orderByDesc('total')
            ->get();

        // Garages by Registration Type
        $garagesByRegisteredBy = Garage::select('registered_by', DB::raw('count(*) as total'))
            ->whereNotNull('registered_by')
            ->groupBy('registered_by')
            ->get();

        // Garages by Country
        $garagesByCountry = DB::table('garages')
            ->join('countries', 'garages.country_id', '=', 'countries.id')
            ->select('countries.name', DB::raw('count(*) as total'))
            ->groupBy('countries.name')
            ->orderByDesc('total')
            ->get();

        // Recent Garages (last 30 days)
        $recentGarages = Garage::where('created_at', '>=', now()->subDays(30))->count();

        // All areas for filters
        $areas = Garage::select('area')->distinct()->pluck('area')->filter()->values();
        
        // All tags for filters
        $allTags = Tag::pluck('title', 'id');

        // Check if this is an AJAX request for area pagination
        if (request()->ajax() || request()->has('area_pagination')) {
            return $this->getAreaPaginationData();
        }

        return view('home', compact(
            'totalUsers',
            'usersByType',
            'totalGarages',
            'verifiedGarages',
            'unverifiedGarages',
            'garagesByStatus',
            'garagesByLocationVerified',
            'garagesByAreaWithPercent',
            'garagesByAreaPaginated',
            'garagesByTag',
            'garagesByRegisteredBy',
            'garagesByCountry',
            'recentGarages',
            'areas',
            'allTags'
        ));
    }

    private function getAreaPaginationData()
    {
        // Garages by Area with percentages
        $garagesByArea = Garage::select('area', DB::raw('count(*) as total'))
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();
        
        $totalGaragesWithArea = $garagesByArea->sum('total');
        $garagesByAreaWithPercent = $garagesByArea->map(function($item) use ($totalGaragesWithArea) {
            $item->percentage = $totalGaragesWithArea > 0 ? round(($item->total / $totalGaragesWithArea) * 100, 2) : 0;
            return $item;
        });

        // Paginate the area distribution (10 per page)
        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $perPage = 10;
        $currentItems = $garagesByAreaWithPercent->slice(($currentPage - 1) * $perPage, $perPage)->values();
        
        // Build path for pagination links
        $path = route('admin.home');
        $query = request()->query();
        unset($query['area_pagination']); // Remove area_pagination from query to avoid duplication
        
        $garagesByAreaPaginated = new LengthAwarePaginator(
            $currentItems,
            $garagesByAreaWithPercent->count(),
            $perPage,
            $currentPage,
            ['path' => $path, 'query' => $query]
        );

        $html = view('partials.area-distribution-table', ['garagesByAreaPaginated' => $garagesByAreaPaginated])->render();
        
        return response()->json([
            'html' => $html,
            'current_page' => $garagesByAreaPaginated->currentPage(),
            'last_page' => $garagesByAreaPaginated->lastPage()
        ]);
    }
}
