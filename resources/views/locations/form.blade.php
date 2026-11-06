@extends('layouts.app')

@section('title', $location ? 'Edit location' : 'Add location')
@section('header_title', $location ? 'Edit location' : 'Add location')
@section('header_subtitle', $location ? $location->name . ' (' . $location->code . ')' : 'Register a port, terminal, or regional hub')

@section('content')
<div class="mx-auto max-w-xl space-y-5 p-3 sm:p-6">
    @if ($errors->any())
        <div class="rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $location ? route('locations.update', $location) : route('locations.store') }}" class="space-y-5 rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-6">
        @csrf
        @if ($location)
            @method('PUT')
        @endif

        <div>
            <label for="loc_name" class="text-xs font-semibold">Location name *</label>
            <input id="loc_name" name="name" type="text" required maxlength="180" value="{{ old('name', $location->name ?? '') }}" placeholder="Tanjung Priok Port" list="preset-names" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            <datalist id="preset-names">
                @foreach ($presets as $p)
                    <option value="{{ $p['name'] }}">{{ $p['code'] }} — {{ $p['city'] }}, {{ $p['country'] }}</option>
                @endforeach
            </datalist>
            <p class="mt-1 text-[11px] text-[var(--ink-3)]">Pick a catalog preset to autofill code, city, country, and coordinates.</p>
            @error('name')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="loc_code" class="text-xs font-semibold">UN/LOCODE *</label>
                <input id="loc_code" name="code" type="text" required maxlength="16" value="{{ old('code', $location->code ?? '') }}" placeholder="JKT" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs uppercase" />
                @error('code')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="loc_city" class="text-xs font-semibold">City / Kabupaten *</label>
                <input id="loc_city" name="city" type="text" required maxlength="120" value="{{ old('city', $location->city ?? '') }}" placeholder="Jakarta" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
                @error('city')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="loc_province" class="text-xs font-semibold">Province (optional)</label>
                <select id="loc_province" name="province" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                    <option value="">—</option>
                    @foreach ($provinces as $prov)
                        <option value="{{ $prov }}" {{ old('province', $location->province ?? '') === $prov ? 'selected' : '' }}>{{ $prov }}</option>
                    @endforeach
                </select>
                @error('province')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="loc_country" class="text-xs font-semibold">Country *</label>
                <input id="loc_country" name="country" type="text" required maxlength="120" value="{{ old('country', $location->country ?? 'Indonesia') }}" list="country-names" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
                <datalist id="country-names">
                    @foreach ($countries as $c)
                        <option value="{{ $c['name'] }}">{{ $c['iso'] }} · {{ $c['region'] }}</option>
                    @endforeach
                </datalist>
                @error('country')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="loc_lat" class="text-xs font-semibold">Latitude *</label>
                <input id="loc_lat" name="latitude" type="number" step="0.000001" min="-90" max="90" required value="{{ old('latitude', $location->latitude ?? '') }}" placeholder="-6.105" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" />
                @error('latitude')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="loc_lng" class="text-xs font-semibold">Longitude *</label>
                <input id="loc_lng" name="longitude" type="number" step="0.000001" min="-180" max="180" required value="{{ old('longitude', $location->longitude ?? '') }}" placeholder="106.88" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" />
                @error('longitude')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex items-center gap-2 pt-1">
            <button type="submit" class="inline-flex h-9 items-center rounded-md bg-[#C6F432] px-4 text-xs font-semibold text-[#080B0C]">Save location</button>
            <a href="{{ route('locations.index') }}" class="inline-flex h-9 items-center rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-4 text-xs font-medium">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    const ctPresets = @json(collect($presets)->keyBy('name'));
    document.getElementById('loc_name').addEventListener('change', function () {
        const preset = ctPresets[this.value];
        if (!preset) return;
        document.getElementById('loc_code').value = preset.code;
        document.getElementById('loc_city').value = preset.city;
        document.getElementById('loc_country').value = preset.country;
        document.getElementById('loc_lat').value = preset.latitude;
        document.getElementById('loc_lng').value = preset.longitude;
        const prov = document.getElementById('loc_province');
        if (preset.province) prov.value = preset.province;
    });
</script>
@endpush
@endsection
