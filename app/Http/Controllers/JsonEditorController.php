<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class JsonEditorController extends Controller
{
    public function index(Request $request)
    {
        $lang = $request->query('lang', 'en');
        $jsonPath = storage_path("app/{$lang}.json");
        $jsonData = file_exists($jsonPath) ? json_decode(file_get_contents($jsonPath), true) : null;
        // dd(file_get_contents($jsonPath) );
        return view('json-editor', compact('jsonData', 'lang'));
    }

    public function save(Request $request)
    {
        $lang = $request->input('lang', 'en');
        $json = $request->input('json');
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['json' => 'Invalid JSON format!']);
        }

        file_put_contents(storage_path("app/{$lang}.json"), json_encode($decoded, JSON_PRETTY_PRINT));
        return back()->with(['success' => 'JSON updated!', 'lang' => $lang]);
    }
}
