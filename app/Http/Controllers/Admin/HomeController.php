<?php

namespace App\Http\Controllers\Admin;
use App\Models\Garage;
use Illuminate\Support\Facades\DB;

class HomeController
{
    public function index()
    {
        $garagesByArea = Garage::select('area', DB::raw('count(*) as total'))
        ->groupBy('area')
        ->orderByDesc('total')
        ->get();

        return view('home', compact('garagesByArea'));
    }
}
