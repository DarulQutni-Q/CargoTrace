@php
/** @var \App\Models\Shipment|null $shipment */
$shipment = $shipment ?? null;
$oldOr = fn ($field, $format = null) => old($field, $shipment?->{$field}?->format($format ?? 'Y-m-d\TH:i'));
@endphp

<form method="POST" action="{{ isset($shipment) ? route('shipments.update', $shipment) : route('shipments.store') }}"
      class="space-y-6">
    @csrf
    @isset($shipment) @method('PUT') @endisset

    <section class="rounded-xl border border-line bg-surface p-6">
        <h2 class="text-sm font-medium">Route</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="customer_id" class="mb-1.5 block text-xs font-medium text-ink-2">Customer</label>
                <select id="customer_id" name="customer_id"
                        class="w-full rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink focus:border-line-strong focus:outline-none focus:ring-2 focus:ring-accent/50">
                    <option value="">Select a customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id', $shipment?->customer_id) == $customer->id)>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_id') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="carrier_id" class="mb-1.5 block text-xs font-medium text-ink-2">Carrier</label>
                <select id="carrier_id" name="carrier_id"
                        class="w-full rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink focus:border-line-strong focus:outline-none focus:ring-2 focus:ring-accent/50">
                    <option value="">Select a carrier</option>
                    @foreach ($carriers as $carrier)
                        <option value="{{ $carrier->id }}" @selected(old('carrier_id', $shipment?->carrier_id) == $carrier->id)>
                            {{ $carrier->name }} ({{ $carrier->code }})
                        </option>
                    @endforeach
                </select>
                @error('carrier_id') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="origin_location_id" class="mb-1.5 block text-xs font-medium text-ink-2">Origin</label>
                <select id="origin_location_id" name="origin_location_id"
                        class="w-full rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink focus:border-line-strong focus:outline-none focus:ring-2 focus:ring-accent/50">
                    <option value="">Select origin</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('origin_location_id', $shipment?->origin_location_id) == $location->id)>
                            {{ $location->code }} — {{ $location->city }}, {{ $location->country }}
                        </option>
                    @endforeach
                </select>
                @error('origin_location_id') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="destination_location_id" class="mb-1.5 block text-xs font-medium text-ink-2">Destination</label>
                <select id="destination_location_id" name="destination_location_id"
                        class="w-full rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink focus:border-line-strong focus:outline-none focus:ring-2 focus:ring-accent/50">
                    <option value="">Select destination</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('destination_location_id', $shipment?->destination_location_id) == $location->id)>
                            {{ $location->code }} — {{ $location->city }}, {{ $location->country }}
                        </option>
                    @endforeach
                </select>
                @error('destination_location_id') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <h2 class="text-sm font-medium">Cargo &amp; schedule</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="cargo_type" class="mb-1.5 block text-xs font-medium text-ink-2">Cargo type</label>
                <x-input id="cargo_type" name="cargo_type" value="{{ old('cargo_type', $shipment?->cargo_type) }}" placeholder="Electronics"/>
                @error('cargo_type') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="weight_kg" class="mb-1.5 block text-xs font-medium text-ink-2">Weight (kg)</label>
                <x-input id="weight_kg" name="weight_kg" type="number" step="0.01" min="0" value="{{ old('weight_kg', $shipment?->weight_kg) }}"/>
                @error('weight_kg') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="volume_m3" class="mb-1.5 block text-xs font-medium text-ink-2">Volume (m³)</label>
                <x-input id="volume_m3" name="volume_m3" type="number" step="0.01" min="0" value="{{ old('volume_m3', $shipment?->volume_m3) }}"/>
                @error('volume_m3') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="expected_departure_at" class="mb-1.5 block text-xs font-medium text-ink-2">Expected departure</label>
                <x-input id="expected_departure_at" name="expected_departure_at" type="datetime-local" value="{{ $oldOr('expected_departure_at') }}"/>
                @error('expected_departure_at') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="expected_arrival_at" class="mb-1.5 block text-xs font-medium text-ink-2">Expected arrival</label>
                <x-input id="expected_arrival_at" name="expected_arrival_at" type="datetime-local" value="{{ $oldOr('expected_arrival_at') }}"/>
                @error('expected_arrival_at') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-medium">Items</h2>
            <button type="button" data-add-item
                    class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-raised px-3 py-1.5 text-xs text-ink-2 transition-colors hover:border-line-strong hover:text-ink">
                <x-lucide-plus class="size-3.5" stroke-width="1.75"/>
                Add item
            </button>
        </div>

        <div class="mt-4 space-y-3" data-item-rows>
            @php
                $rows = old('items', $shipment?->items?->map(fn ($item) => $item->only(['description', 'quantity', 'weight_kg', 'volume_m3']))->all() ?? []);
            @endphp
            @forelse ($rows as $row)
                <x-shipment-item-row :item="$row"/>
            @empty
                <x-shipment-item-row/>
            @endforelse
        </div>
        @error('items.*.description') <p class="mt-2 text-xs text-danger">Every item needs a description</p> @enderror

        <template data-item-template>
            <x-shipment-item-row/>
        </template>
    </section>

    <div class="flex items-center gap-3">
        <x-button>{{ isset($shipment) ? 'Save changes' : 'Create shipment' }}</x-button>
        <a href="{{ isset($shipment) ? route('shipments.show', $shipment) : route('shipments.index') }}"
           class="text-sm text-ink-2 transition-colors hover:text-ink">Cancel</a>
    </div>
</form>

@vite('resources/js/shipment-form.js')
