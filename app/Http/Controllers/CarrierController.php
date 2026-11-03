<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Master data for the logistics operators, managed by administrators
 * only (route group is gated by the role middleware).
 */
class CarrierController extends Controller
{
    public function index(): View
    {
        return view('carriers.index', [
            'carriers' => Carrier::query()->withCount('shipments')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('carriers.form', ['carrier' => new Carrier]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Carrier::create($data);

        return redirect()->route('carriers.index');
    }

    public function edit(Carrier $carrier): View
    {
        return view('carriers.form', ['carrier' => $carrier]);
    }

    public function update(Request $request, Carrier $carrier): RedirectResponse
    {
        $carrier->update($this->validated($request, $carrier));

        return redirect()->route('carriers.index');
    }

    public function destroy(Carrier $carrier): RedirectResponse
    {
        $carrier->delete();

        return redirect()->route('carriers.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Carrier $carrier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('carriers', 'code')->ignore($carrier?->id)],
            'service_type' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
