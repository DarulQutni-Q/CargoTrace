<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCarrierRequest;
use App\Models\Carrier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarrierController extends Controller
{
    /**
     * GET /api/v1/carriers
     */
    public function index(Request $request): JsonResponse
    {
        $carriers = Carrier::withCount('shipments')
            ->when($request->query('q'), function ($query, $q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            })
            ->when($request->query('service_type'), fn ($query, $type) => $query->where('service_type', strtoupper($type)))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json(['status' => 'ok', 'data' => $carriers]);
    }

    /**
     * GET /api/v1/carriers/{carrier}
     */
    public function show(Carrier $carrier): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => $carrier->loadCount('shipments'),
        ]);
    }

    /**
     * POST /api/v1/carriers
     * Admin and logistics operators only (role middleware).
     */
    public function store(StoreCarrierRequest $request): JsonResponse
    {
        $carrier = Carrier::create($request->validated());

        return response()->json(['status' => 'ok', 'data' => $carrier], 201);
    }
}
