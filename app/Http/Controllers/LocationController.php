<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Origin/destination master data: ports, airports, and inland hubs with
 * WGS84 coordinates the map relies on.
 */
class LocationController extends Controller
{
    public function index(): View
    {
        return view('locations.index', [
            'locations' => Location::query()->orderBy('city')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('locations.form', ['location' => new Location]);
    }

    public function store(Request $request): RedirectResponse
    {
        Location::create($this->validated($request));

        return redirect()->route('locations.index');
    }

    public function edit(Location $location): View
    {
        return view('locations.form', ['location' => $location]);
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($this->validated($request, $location));

        return redirect()->route('locations.index');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        return redirect()->route('locations.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Location $location = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('locations', 'code')->ignore($location?->id)],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
    }
}
