<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Carrier master data over the API: readable by staff, writable by
 * administrators only [S5]. Customers see carrier details through the
 * shipment payloads instead.
 */
class CarrierController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isOperator(), 403);

        return response()->json([
            'data' => Carrier::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('carriers', 'code')],
            'service_type' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $carrier = Carrier::create($data);

        return response()->json(['data' => $carrier], 201);
    }
}
