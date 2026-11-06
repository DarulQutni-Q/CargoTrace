@extends('layouts.app')

@php
    $isEdit = $shipment !== null;
    $formTitle = $isEdit ? 'Edit ' . $shipment->shipment_number : ($isCustomer ? 'Request shipment booking' : 'New shipment');
    $formSubtitle = $isEdit ? null : ($isCustomer ? 'Submit a new cargo order under your Customer account (initial status: CREATED)' : null);
    $firstCustomer = $customers->first();
    $firstCarrier = $carriers->first();
    $firstLocation = $locations->first();
    $rtmLocation = $locations->firstWhere('code', 'RTM');
    $selCustomer = old('customer_id', $isEdit ? $shipment->customer_id : ($isCustomer ? auth()->id() : ($firstCustomer?->id ?? '')));
    $selCarrier = old('carrier_id', $isEdit ? $shipment->carrier_id : ($firstCarrier?->id ?? ''));
    $selOrigin = old('origin_location_id', $isEdit ? $shipment->origin_location_id : ($firstLocation?->id ?? ''));
    $selDest = old('destination_location_id', $isEdit ? $shipment->destination_location_id : ($rtmLocation?->id ?? ''));
    $originLoc = $locations->firstWhere('id', (int) $selOrigin);
    $destLoc = $locations->firstWhere('id', (int) $selDest);
    $portDisplay = fn ($loc) => $loc ? $loc->code . ' — ' . $loc->city . ', ' . $loc->country : '';
    $fmtDt = fn ($d) => $d ? $d->format('Y-m-d\TH:i') : '';
    $items = old('items', $isEdit ? $shipment->items->map(fn ($i) => ['description' => $i->description, 'quantity' => $i->quantity, 'weight_kg' => $i->weight_kg, 'volume_m3' => $i->volume_m3])->all() : [['description' => 'Pallet of automation modules', 'quantity' => 10, 'weight_kg' => 500, 'volume_m3' => 8]]);
    $transitName = old('transit_stop.location_name', $existingCheckpoint->location_name ?? '');
    $transitLat = old('transit_stop.latitude', $existingCheckpoint->latitude ?? '');
    $transitLng = old('transit_stop.longitude', $existingCheckpoint->longitude ?? '');
@endphp

@section('title', $formTitle)
@section('header_title', $formTitle)
@if ($formSubtitle)
@section('header_subtitle', $formSubtitle)
@endif

@section('content')
<div class="mx-auto max-w-3xl p-3 sm:p-6">
    @if ($errors->any())
        <div class="mb-5 rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="shipment-form" method="POST" action="{{ $isEdit ? route('shipments.update', $shipment) : route('shipments.store') }}" class="space-y-5 sm:space-y-6">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <section class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-6">
            <h2 class="text-sm font-semibold">1. Corridor route &amp; parties</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-[var(--ink-2)]">Customer / consignee</label>
                    @if ($isCustomer && auth()->user())
                        <input type="hidden" name="customer_id" value="{{ auth()->id() }}" />
                        <div class="flex h-9 w-full items-center justify-between rounded-md border border-[var(--line)] bg-[var(--bg-raised)] px-3 text-xs font-semibold">
                            <span class="truncate">{{ auth()->user()->name }} ({{ auth()->user()->email }})</span>
                            <span class="ml-2 shrink-0 rounded bg-[#C6F432] px-1.5 py-0.5 font-mono text-[10px] font-bold text-[#080B0C]">YOUR ACCOUNT</span>
                        </div>
                    @else
                        <select name="customer_id" required class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium">
                            <option value="">Select customer</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" {{ (string) $selCustomer === (string) $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->email }})</option>
                            @endforeach
                        </select>
                        @error('customer_id')<p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    @endif
                </div>
                <div>
                    <label for="carrier_id" class="mb-1.5 block text-xs font-medium text-[var(--ink-2)]">Carrier</label>
                    <select id="carrier_id" name="carrier_id" required class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium">
                        <option value="">Select carrier</option>
                        @foreach ($carriers as $c)
                            <option value="{{ $c->id }}" data-mode="{{ $c->service_type }}" {{ (string) $selCarrier === (string) $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->code }} · {{ $c->service_type }})</option>
                        @endforeach
                    </select>
                    @error('carrier_id')<p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="relative">
                    <label for="origin_display" class="mb-1.5 block text-xs font-medium text-[var(--ink-2)]">Origin port / Kabupaten / Hub *</label>
                    <input id="origin_display" type="text" value="{{ $portDisplay($originLoc) }}" placeholder="Search Bireuen, Krueng Geukuh, Lhokseumawe, Jakarta..." list="origin-options" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] pl-3 pr-8 text-xs font-medium focus:border-[var(--line-strong)] focus:outline-none" />
                    <input type="hidden" id="origin_location_id" name="origin_location_id" value="{{ $selOrigin }}" />
                    <datalist id="origin-options">
                        @foreach ($portOptions as $opt)
                            <option value="{{ $opt['code'] }} — {{ $opt['city'] }}, {{ $opt['country'] }}" data-id="{{ $opt['id'] ?? $opt['code'] }}">{{ $opt['name'] }}{{ $opt['province'] ? ' · Prov. ' . $opt['province'] : '' }}</option>
                        @endforeach
                    </datalist>
                    @error('origin_location_id')<p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="relative">
                    <label for="dest_display" class="mb-1.5 block text-xs font-medium text-[var(--ink-2)]">Destination port / Kabupaten / Hub *</label>
                    <input id="dest_display" type="text" value="{{ $portDisplay($destLoc) }}" placeholder="Search Surabaya, Rotterdam, Bireuen, Lhokseumawe..." list="dest-options" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] pl-3 pr-8 text-xs font-medium focus:border-[var(--line-strong)] focus:outline-none" />
                    <input type="hidden" id="destination_location_id" name="destination_location_id" value="{{ $selDest }}" />
                    <datalist id="dest-options">
                        @foreach ($portOptions as $opt)
                            <option value="{{ $opt['code'] }} — {{ $opt['city'] }}, {{ $opt['country'] }}" data-id="{{ $opt['id'] ?? $opt['code'] }}">{{ $opt['name'] }}{{ $opt['province'] ? ' · Prov. ' . $opt['province'] : '' }}</option>
                        @endforeach
                    </datalist>
                    @error('destination_location_id')<p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="relative sm:col-span-2">
                    <div class="mb-1.5 flex items-center justify-between">
                        <label for="transit_display" class="block text-xs font-medium text-[var(--ink-2)]">Intermediate transit stop / Via hub (optional — e.g. Port of Singapore, Sabang, Tanjung Priok)</label>
                        <button type="button" id="clear-transit" onclick="clearTransitStop()" class="{{ $transitName ? '' : 'hidden' }} text-[11px] font-semibold text-rose-600 dark:text-rose-400 hover:underline">× Clear transit stop</button>
                    </div>
                    <input id="transit_display" type="text" value="{{ $transitName }}" placeholder="Optional mid-route checkpoint (search Singapore, Sabang, Belawan, Batam, Colombo...)" list="transit-options" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] pl-3 pr-8 text-xs font-medium focus:border-[var(--line-strong)] focus:outline-none" />
                    <input type="hidden" id="transit_name" name="transit_stop[location_name]" value="{{ $transitName }}" />
                    <input type="hidden" id="transit_lat" name="transit_stop[latitude]" value="{{ $transitLat }}" />
                    <input type="hidden" id="transit_lng" name="transit_stop[longitude]" value="{{ $transitLng }}" />
                    <datalist id="transit-options">
                        @foreach ($portOptions as $opt)
                            <option value="{{ $opt['name'] }} ({{ $opt['code'] }})" data-lat="{{ $opt['latitude'] }}" data-lng="{{ $opt['longitude'] }}">{{ $opt['city'] }}, {{ $opt['country'] }}</option>
                        @endforeach
                    </datalist>
                </div>
            </div>

            <div id="route-preview" class="mt-4 hidden space-y-2 rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3.5 py-2.5 text-xs">
                <div class="flex flex-wrap items-center gap-1.5 font-mono text-[11px]">
                    <span id="preview-origin" class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-1.5 py-0.5 font-bold"></span>
                    <span id="preview-via-arrow" class="hidden font-bold text-[var(--ink-2)]">━━▶</span>
                    <span id="preview-via" class="hidden rounded border border-[#080B0C] bg-[#C6F432] px-1.5 py-0.5 font-bold text-[#080B0C]"></span>
                    <span id="preview-via-arrow2" class="hidden font-bold text-[var(--ink-3)]">╌╌▶</span>
                    <span id="preview-dest" class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-1.5 py-0.5 font-bold"></span>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[var(--line)] pt-2">
                    <div class="flex items-center gap-2 text-[var(--ink-2)]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-[var(--ink-3)]"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                        <span>Corridor distance: <span id="preview-km" class="font-mono font-bold text-[var(--ink-1)]"></span> (<span id="preview-nm"></span> NM) · Est. <span id="preview-mode"></span> transit: <span id="preview-hours" class="font-mono font-semibold text-[var(--ink-1)]"></span></span>
                    </div>
                    <button type="button" id="apply-arrival" class="font-mono text-[11px] font-semibold underline hover:opacity-80">Apply ~<span id="apply-hours"></span>h to expected arrival</button>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">2. Cargo load &amp; schedule</h2>
                <button type="button" id="sync-totals-top" onclick="syncTotalsFromItems()" class="hidden h-7 items-center gap-1.5 rounded-md border border-[#080B0C] bg-[#C6F432] px-2.5 text-[11px] font-semibold text-[#080B0C] hover:bg-[#b5e422]">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                    Sync with manifest (<span id="sync-totals-label"></span>)
                </button>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="cargo_type" class="mb-1.5 block text-xs font-medium text-[var(--ink-2)]">Cargo type</label>
                    <input id="cargo_type" name="cargo_type" type="text" required maxlength="120" value="{{ old('cargo_type', $isEdit ? $shipment->cargo_type : '') }}" placeholder="Industrial Electronics" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium" />
                    @error('cargo_type')<p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="weight_kg" class="mb-1.5 block text-xs font-medium text-[var(--ink-2)]">Total weight (kg)</label>
                    <input id="weight_kg" name="weight_kg" type="number" step="any" min="0.01" required value="{{ old('weight_kg', $isEdit ? $shipment->weight_kg : 500) }}" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 font-mono text-xs font-medium" />
                    @error('weight_kg')<p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="volume_m3" class="mb-1.5 block text-xs font-medium text-[var(--ink-2)]">Total volume (m³)</label>
                    <input id="volume_m3" name="volume_m3" type="number" step="any" min="0" required value="{{ old('volume_m3', $isEdit ? $shipment->volume_m3 : 8) }}" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 font-mono text-xs font-medium" />
                    @error('volume_m3')<p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="expected_departure_at" class="mb-1.5 block text-xs font-medium text-[var(--ink-2)]">Expected departure</label>
                    <input id="expected_departure_at" name="expected_departure_at" type="datetime-local" value="{{ old('expected_departure_at', $isEdit ? $fmtDt($shipment->expected_departure_at) : '2026-11-06T09:00') }}" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium" />
                </div>
                <div>
                    <label for="expected_arrival_at" class="mb-1.5 block text-xs font-medium text-[var(--ink-2)]">Expected arrival (baseline)</label>
                    <input id="expected_arrival_at" name="expected_arrival_at" type="datetime-local" required value="{{ old('expected_arrival_at', $isEdit ? $fmtDt($shipment->expected_arrival_at) : '2026-11-22T14:00') }}" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium" />
                </div>
                <div>
                    <label for="estimated_arrival_at" class="mb-1.5 block text-xs font-medium text-[var(--ink-2)]">Estimated arrival (live ETA)</label>
                    <input id="estimated_arrival_at" name="estimated_arrival_at" type="datetime-local" value="{{ old('estimated_arrival_at', $isEdit ? $fmtDt($shipment->estimated_arrival_at) : '') }}" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium" />
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold">3. Cargo manifest items</h2>
                    <p class="mt-0.5 font-mono text-[11px] font-medium tabular-nums text-[var(--ink-3)]">Manifest sum: <span id="manifest-sum">0 kg · 0 m³</span></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="syncTotalsFromItems()" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs font-medium text-[var(--ink-2)] hover:border-[var(--line-strong)] hover:text-[var(--ink-1)]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                        Sync totals from items
                    </button>
                    <button type="button" onclick="addItemRow()" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-raised)] px-3 text-xs font-medium hover:border-[var(--line-strong)]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        Add item
                    </button>
                </div>
            </div>
            <div id="items-rows" class="mt-4 space-y-3">
                @foreach ($items as $idx => $item)
                    <div class="item-row grid grid-cols-[1fr_1fr_1fr_2.25rem] items-end gap-2 sm:grid-cols-[1fr_5rem_6rem_6rem_2.25rem]" data-idx="{{ $idx }}">
                        <div class="col-span-4 sm:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-[var(--ink-2)]">Description</label>
                            <input type="text" name="items[{{ $idx }}][description]" required maxlength="255" value="{{ $item['description'] }}" placeholder="Pallet of automation modules" class="item-desc h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[var(--ink-2)]">Qty</label>
                            <input type="number" name="items[{{ $idx }}][quantity]" min="1" required value="{{ $item['quantity'] }}" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs font-medium" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[var(--ink-2)]">Kg</label>
                            <input type="number" name="items[{{ $idx }}][weight_kg]" step="any" min="0.01" required value="{{ $item['weight_kg'] }}" class="item-kg h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs font-medium" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[var(--ink-2)]">m³</label>
                            <input type="number" name="items[{{ $idx }}][volume_m3]" step="any" min="0" required value="{{ $item['volume_m3'] }}" class="item-m3 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs font-medium" />
                        </div>
                        <button type="button" onclick="removeItemRow(this)" class="flex size-9 items-center justify-center rounded-md border border-[var(--line)] bg-[var(--bg-base)] text-[var(--ink-3)] hover:text-rose-500 disabled:opacity-30" {{ count($items) <= 1 ? 'disabled' : '' }}>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>
            @error('items')<p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="h-9 rounded-md bg-[#C6F432] px-4 text-xs font-semibold text-[#080B0C] hover:bg-[#b5e422]">{{ $isEdit ? 'Save changes' : 'Create shipment' }}</button>
            <a href="{{ $isEdit ? route('shipments.show', $shipment) : route('shipments.index') }}" class="text-xs font-medium text-[var(--ink-2)] hover:text-[var(--ink-1)]">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    const ctPorts = @json($portOptions);
    const ctPortByDisplay = {};
    ctPorts.forEach((p) => {
        const display = p.code + ' — ' + p.city + ', ' + p.country;
        ctPortByDisplay[display] = p;
    });
    const ctTransitByDisplay = {};
    ctPorts.forEach((p) => {
        ctTransitByDisplay[p.name + ' (' + p.code + ')'] = p;
    });

    function resolvePortInput(inputId, hiddenId) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const val = input.value.trim();
        const opt = ctPortByDisplay[val];
        if (opt) {
            hidden.value = opt.id !== null ? opt.id : opt.code;
            return opt;
        }
        const codeMatch = val.match(/^([A-Za-z]{2,5})\b/);
        if (codeMatch) {
            const byCode = ctPorts.find((p) => p.code.toUpperCase() === codeMatch[1].toUpperCase());
            if (byCode) {
                input.value = byCode.code + ' — ' + byCode.city + ', ' + byCode.country;
                hidden.value = byCode.id !== null ? byCode.id : byCode.code;
                return byCode;
            }
        }
        return null;
    }

    function haversineKm(a, b) {
        const R = 6371;
        const dLat = (b[0] - a[0]) * Math.PI / 180;
        const dLng = (b[1] - a[1]) * Math.PI / 180;
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(a[0] * Math.PI / 180) * Math.cos(b[0] * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
    }

    let ctTransitHours = 0;
    function updateRoutePreview() {
        const origin = resolvePortInput('origin_display', 'origin_location_id');
        const dest = resolvePortInput('dest_display', 'destination_location_id');
        const preview = document.getElementById('route-preview');
        if (!origin || !dest) {
            preview.classList.add('hidden');
            return;
        }
        const carrierSel = document.getElementById('carrier_id');
        const mode = carrierSel.options[carrierSel.selectedIndex]?.dataset?.mode || 'SEA';
        const transitName = document.getElementById('transit_name').value;
        const transitLat = parseFloat(document.getElementById('transit_lat').value);
        const transitLng = parseFloat(document.getElementById('transit_lng').value);
        const hasTransit = transitName && !isNaN(transitLat) && !isNaN(transitLng);
        let km = 0;
        if (hasTransit) {
            km = haversineKm([origin.latitude, origin.longitude], [transitLat, transitLng])
                + haversineKm([transitLat, transitLng], [dest.latitude, dest.longitude]);
        } else {
            km = haversineKm([origin.latitude, origin.longitude], [dest.latitude, dest.longitude]);
        }
        const nm = Math.round(km * 0.539957);
        const stopBuffer = hasTransit ? 12 : 0;
        const transitHours = (mode === 'AIR'
            ? Math.max(6, Math.round(km / 650 + 10))
            : mode === 'ROAD'
            ? Math.max(8, Math.round(km / 52 + 8))
            : Math.max(24, Math.round((km * 1.25) / 32 + 24))) + stopBuffer;
        ctTransitHours = transitHours;
        document.getElementById('preview-origin').textContent = origin.code + ' · ' + origin.city;
        document.getElementById('preview-dest').textContent = dest.code + ' · ' + dest.city;
        document.getElementById('preview-via').textContent = '● ' + transitName;
        document.getElementById('preview-via').classList.toggle('hidden', !hasTransit);
        document.getElementById('preview-via-arrow').classList.toggle('hidden', !hasTransit);
        document.getElementById('preview-via-arrow2').classList.toggle('hidden', !hasTransit);
        document.getElementById('preview-km').textContent = Math.round(km).toLocaleString() + ' km';
        document.getElementById('preview-nm').textContent = nm.toLocaleString();
        document.getElementById('preview-mode').textContent = mode;
        document.getElementById('preview-hours').textContent = '~' + transitHours + 'h';
        document.getElementById('apply-hours').textContent = transitHours;
        preview.classList.remove('hidden');
    }

    document.getElementById('apply-arrival').addEventListener('click', function () {
        const dep = document.getElementById('expected_departure_at').value;
        if (!dep || !ctTransitHours) return;
        const depMs = new Date(dep + ':00').getTime();
        if (isNaN(depMs)) return;
        const arr = new Date(depMs + ctTransitHours * 3600000);
        const pad = (n) => String(n).padStart(2, '0');
        document.getElementById('expected_arrival_at').value = arr.getFullYear() + '-' + pad(arr.getMonth() + 1) + '-' + pad(arr.getDate()) + 'T' + pad(arr.getHours()) + ':' + pad(arr.getMinutes());
    });

    function setTransit(name, lat, lng) {
        document.getElementById('transit_display').value = name;
        document.getElementById('transit_name').value = name;
        document.getElementById('transit_lat').value = lat;
        document.getElementById('transit_lng').value = lng;
        document.getElementById('clear-transit').classList.toggle('hidden', !name);
        updateRoutePreview();
    }
    function clearTransitStop() {
        setTransit('', '', '');
    }
    document.getElementById('transit_display').addEventListener('input', function () {
        const opt = ctTransitByDisplay[this.value.trim()];
        if (opt) setTransit(opt.name + ' (' + opt.code + ')', opt.latitude.toFixed(4), opt.longitude.toFixed(4));
        else if (!this.value.trim()) setTransit('', '', '');
        else updateRoutePreview();
    });
    document.getElementById('transit_display').addEventListener('change', function () {
        const opt = ctTransitByDisplay[this.value.trim()];
        if (opt) setTransit(opt.name + ' (' + opt.code + ')', opt.latitude.toFixed(4), opt.longitude.toFixed(4));
        else if (!this.value.trim()) setTransit('', '', '');
        else updateRoutePreview();
    });

    ['origin_display', 'dest_display'].forEach((id) => {
        document.getElementById(id).addEventListener('input', updateRoutePreview);
        document.getElementById(id).addEventListener('change', updateRoutePreview);
    });
    document.getElementById('carrier_id').addEventListener('change', updateRoutePreview);

    function manifestTotals() {
        let w = 0, v = 0;
        document.querySelectorAll('#items-rows .item-row').forEach((row) => {
            w += Number(row.querySelector('.item-kg').value) || 0;
            v += Number(row.querySelector('.item-m3').value) || 0;
        });
        return { weight: Math.round(w * 100) / 100, volume: Math.round(v * 100) / 100 };
    }
    function refreshManifestUI() {
        const t = manifestTotals();
        document.getElementById('manifest-sum').textContent = t.weight.toLocaleString() + ' kg · ' + t.volume + ' m³';
        const w = Number(document.getElementById('weight_kg').value) || 0;
        const v = Number(document.getElementById('volume_m3').value) || 0;
        const top = document.getElementById('sync-totals-top');
        const differs = w !== t.weight || v !== t.volume;
        top.classList.toggle('hidden', !differs);
        top.classList.toggle('inline-flex', differs);
        document.getElementById('sync-totals-label').textContent = t.weight.toLocaleString() + ' kg · ' + t.volume + ' m³';
        document.querySelectorAll('#items-rows .item-row button').forEach((b) => {
            b.disabled = document.querySelectorAll('#items-rows .item-row').length <= 1;
        });
    }
    function syncTotalsFromItems() {
        const t = manifestTotals();
        if (t.weight > 0) document.getElementById('weight_kg').value = t.weight;
        if (t.volume > 0) document.getElementById('volume_m3').value = t.volume;
        refreshManifestUI();
    }
    function reindexItems() {
        document.querySelectorAll('#items-rows .item-row').forEach((row, idx) => {
            row.querySelectorAll('input').forEach((input) => {
                const m = input.name.match(/^items\[\d+\]\[(.+)\]$/);
                if (m) input.name = 'items[' + idx + '][' + m[1] + ']';
            });
        });
    }
    function addItemRow() {
        const rows = document.getElementById('items-rows');
        const idx = rows.querySelectorAll('.item-row').length;
        const div = document.createElement('div');
        div.className = 'item-row grid grid-cols-[1fr_1fr_1fr_2.25rem] items-end gap-2 sm:grid-cols-[1fr_5rem_6rem_6rem_2.25rem]';
        div.innerHTML = '<div class="col-span-4 sm:col-span-1"><label class="mb-1 block text-xs font-medium text-[var(--ink-2)]">Description</label><input type="text" name="items[' + idx + '][description]" required maxlength="255" placeholder="Pallet of automation modules" class="item-desc h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium" /></div>'
            + '<div><label class="mb-1 block text-xs font-medium text-[var(--ink-2)]">Qty</label><input type="number" name="items[' + idx + '][quantity]" min="1" required value="1" class="h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs font-medium" /></div>'
            + '<div><label class="mb-1 block text-xs font-medium text-[var(--ink-2)]">Kg</label><input type="number" name="items[' + idx + '][weight_kg]" step="any" min="0.01" required value="100" class="item-kg h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs font-medium" /></div>'
            + '<div><label class="mb-1 block text-xs font-medium text-[var(--ink-2)]">m³</label><input type="number" name="items[' + idx + '][volume_m3]" step="any" min="0" required value="2" class="item-m3 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs font-medium" /></div>'
            + '<button type="button" onclick="removeItemRow(this)" class="flex size-9 items-center justify-center rounded-md border border-[var(--line)] bg-[var(--bg-base)] text-[var(--ink-3)] hover:text-rose-500"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>';
        rows.appendChild(div);
        syncTotalsFromItems();
        refreshManifestUI();
    }
    function removeItemRow(btn) {
        const rows = document.querySelectorAll('#items-rows .item-row');
        if (rows.length <= 1) return;
        btn.closest('.item-row').remove();
        reindexItems();
        syncTotalsFromItems();
        refreshManifestUI();
    }
    document.getElementById('items-rows').addEventListener('input', function (e) {
        if (e.target.classList.contains('item-kg') || e.target.classList.contains('item-m3')) {
            syncTotalsFromItems();
        }
        refreshManifestUI();
    });
    document.getElementById('weight_kg').addEventListener('input', refreshManifestUI);
    document.getElementById('volume_m3').addEventListener('input', refreshManifestUI);

    document.getElementById('shipment-form').addEventListener('submit', function () {
        resolvePortInput('origin_display', 'origin_location_id');
        resolvePortInput('dest_display', 'destination_location_id');
    });

    updateRoutePreview();
    refreshManifestUI();
</script>
@endpush
@endsection
