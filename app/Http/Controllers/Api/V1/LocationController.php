<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * GET /api/v1/locations
     */
    public function index(Request $request): JsonResponse
    {
        $locations = Location::query()
            ->when($request->query('q'), function ($query, $q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('country', 'like', "%{$q}%");
            })
            ->when($request->query('country'), fn ($query, $country) => $query->where('country', $country))
            ->when($request->query('province'), fn ($query, $province) => $query->where('province', $province))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json(['status' => 'ok', 'data' => $locations]);
    }

    /**
     * GET /api/v1/locations/{location}
     */
    public function show(Location $location): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $location]);
    }

    /**
     * POST /api/v1/locations
     * Admin and logistics operators only (role middleware).
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = Location::create($request->validated());

        return response()->json(['status' => 'ok', 'data' => $location], 201);
    }
}
