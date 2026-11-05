@extends('layouts.app')

@section('title', 'New shipment')
@section('header_title', 'New shipment')
@section('header_subtitle', 'Register a shipment on a corridor')

@section('content')
<div class="mx-auto max-w-[720px] p-3 sm:p-6">
    <form method="POST" action="{{ route('shipments.store') }}" class="overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)]">
        @csrf
        <div class="border-b border-[var(--line)] bg-[var(--bg-base)] px-5 py-4">
            <h2 class="text-[15px] font-semibold tracking-tight text-[var(--ink-1)]">Shipment details</h2>
            <p class="mt-0.5 text-xs font-medium text-[var(--ink-3)]">The shipment starts as CREATED with an anchor event at the origin port.</p>
        </div>

        @if ($errors->any())
            <div class="border-b border-[var(--line)] bg-rose-50 px-5 py-3 text-xs font-medium text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2">
            @if ($isCustomer)
                <input type="hidden" name="customer_id" value="{{ auth()->id() }}" />
                <div>
                    <label class="mb-1 block text-xs text-[var(--ink-3)]">Customer</label>
                    <div class="flex h-9 items-center rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs font-medium text-[var(--ink-1)]">{{ auth()->user()->name }}</div>
                </div>
            @else
                <div>
                    <label for="customer_id" class="mb-1 block text-xs text-[var(--ink-3)]">Customer</label>
                    <select id="customer_id" name="customer_id" required class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs text-[var(--ink-1)]">
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (string) old('customer_id') === (string) $customer->id ? 'selected' : '' }}>{{ $customer->name }} ({{ $customer->email }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="carrier_id" class="mb-1 block text-xs text-[var(--ink-3)]">Carrier</label>
                <select id="carrier_id" name="carrier_id" required class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs text-[var(--ink-1)]">
                    @foreach ($carriers as $carrier)
                        <option value="{{ $carrier->id }}" {{ (string) old('carrier_id') === (string) $carrier->id ? 'selected' : '' }}>{{ $carrier->name }} ({{ $carrier->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="origin_location_id" class="mb-1 block text-xs text-[var(--ink-3)]">Origin port</label>
                <select id="origin_location_id" name="origin_location_id" required class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs text-[var(--ink-1)]">
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (string) old('origin_location_id') === (string) $loc->id ? 'selected' : '' }}>{{ $loc->code }} — {{ $loc->city }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="destination_location_id" class="mb-1 block text-xs text-[var(--ink-3)]">Destination port</label>
                <select id="destination_location_id" name="destination_location_id" required class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs text-[var(--ink-1)]">
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (string) old('destination_location_id', $locations->firstWhere('code', 'RTM')?->id ?? '') === (string) $loc->id ? 'selected' : '' }}>{{ $loc->code }} — {{ $loc->city }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="cargo_type" class="mb-1 block text-xs text-[var(--ink-3)]">Cargo type</label>
                <input id="cargo_type" name="cargo_type" type="text" required maxlength="120" value="{{ old('cargo_type') }}" placeholder="Electronics" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs text-[var(--ink-1)]" />
            </div>
            <div>
                <label for="weight_kg" class="mb-1 block text-xs text-[var(--ink-3)]">Total weight (kg)</label>
                <input id="weight_kg" name="weight_kg" type="number" step="0.01" min="0.01" required value="{{ old('weight_kg') }}" placeholder="800" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs text-[var(--ink-1)]" />
            </div>
            <div>
                <label for="volume_m3" class="mb-1 block text-xs text-[var(--ink-3)]">Total volume (m³)</label>
                <input id="volume_m3" name="volume_m3" type="number" step="0.01" min="0" required value="{{ old('volume_m3') }}" placeholder="12" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs text-[var(--ink-1)]" />
            </div>
            <div>
                <label for="expected_departure_at" class="mb-1 block text-xs text-[var(--ink-3)]">Expected departure</label>
                <input id="expected_departure_at" name="expected_departure_at" type="datetime-local" value="{{ old('expected_departure_at', now()->format('Y-m-d\TH:i')) }}" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs text-[var(--ink-1)]" />
            </div>
            <div>
                <label for="expected_arrival_at" class="mb-1 block text-xs text-[var(--ink-3)]">Expected arrival</label>
                <input id="expected_arrival_at" name="expected_arrival_at" type="datetime-local" required value="{{ old('expected_arrival_at', now()->addDays(7)->format('Y-m-d\TH:i')) }}" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs text-[var(--ink-1)]" />
            </div>
            <div class="sm:col-span-2">
                <label for="estimated_arrival_at" class="mb-1 block text-xs text-[var(--ink-3)]">Estimated arrival (optional, defaults to expected)</label>
                <input id="estimated_arrival_at" name="estimated_arrival_at" type="datetime-local" value="{{ old('estimated_arrival_at') }}" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs text-[var(--ink-1)]" />
            </div>
        </div>

        <div class="border-t border-[var(--line)] bg-[var(--bg-base)] px-5 py-4">
            <h3 class="text-xs font-semibold text-[var(--ink-1)]">Manifest item</h3>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="item_description" class="mb-1 block text-xs text-[var(--ink-3)]">Description</label>
                    <input id="item_description" name="items[0][description]" type="text" required maxlength="255" value="{{ old('items.0.description') }}" placeholder="Palletized Electronics ×24" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs text-[var(--ink-1)]" />
                </div>
                <div>
                    <label for="item_quantity" class="mb-1 block text-xs text-[var(--ink-3)]">Quantity</label>
                    <input id="item_quantity" name="items[0][quantity]" type="number" min="1" required value="{{ old('items.0.quantity', 1) }}" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs text-[var(--ink-1)]" />
                </div>
                <div>
                    <label for="item_weight" class="mb-1 block text-xs text-[var(--ink-3)]">Item weight (kg)</label>
                    <input id="item_weight" name="items[0][weight_kg]" type="number" step="0.01" min="0.01" required value="{{ old('items.0.weight_kg') }}" placeholder="800" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs text-[var(--ink-1)]" />
                </div>
                <div>
                    <label for="item_volume" class="mb-1 block text-xs text-[var(--ink-3)]">Item volume (m³)</label>
                    <input id="item_volume" name="items[0][volume_m3]" type="number" step="0.01" min="0" required value="{{ old('items.0.volume_m3') }}" placeholder="12" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs text-[var(--ink-1)]" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-[var(--line)] bg-[var(--bg-base)] px-5 py-4">
            <a href="{{ route('shipments.index') }}" class="text-xs font-semibold text-[var(--ink-2)] hover:underline">Cancel</a>
            <button type="submit" class="inline-flex h-9 cursor-pointer items-center rounded-md bg-[var(--brand)] px-4 text-xs font-semibold text-[var(--brand-ink)] transition-colors hover:bg-[var(--brand-hover)]">Create shipment</button>
        </div>
    </form>
</div>
@endsection
