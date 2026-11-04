<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Location master data over the API, mirroring carrier access rules.
 * Coordinates are WGS84 decimal degrees [S9].
 */
class LocationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isOperator(), 403);

        return response()->json([
            'data' => Location::query()->orderBy('city')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('locations', 'code')],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $location = Location::create($data);

        return response()->json(['data' => $location], 201);
    }
}
