@extends('layouts.app')

@section('title', 'Port Locations')
@section('header_title', 'Port locations')
@section('header_subtitle', $locations->count() . ' active ports & 38 Indonesian provinces catalog')

@section('content')
<div class="mx-auto max-w-[1440px] space-y-5 p-3 sm:p-6">
    @if (session('status'))
        <div class="rounded-md border border-[#C6F432] bg-[#C6F432]/20 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] px-4 py-4 sm:px-5">
        <div>
            <h2 class="text-base font-semibold">Port, Kabupaten/Kota &amp; Terminal Registry (38 Provinsi Indonesia)</h2>
            <p class="mt-0.5 text-xs opacity-75">Includes Aceh (Bireuen, Krueng Geukuh, Lhokseumawe, Sabang, Malahayati) and global hubs</p>
        </div>
    </div>

    @if (auth()->user()->canOperateCorridor())
        <form method="POST" action="{{ route('locations.store') }}" class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-5">
            @csrf
            <h2 class="text-sm font-semibold">Register a new location</h2>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="loc_name" class="text-xs font-semibold">Location name</label>
                    <input id="loc_name" name="name" type="text" required maxlength="180" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" placeholder="Tanjung Priok Port" />
                </div>
                <div>
                    <label for="loc_code" class="text-xs font-semibold">UN/LOCODE</label>
                    <input id="loc_code" name="code" type="text" required maxlength="16" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" placeholder="JKT" />
                </div>
                <div>
                    <label for="loc_city" class="text-xs font-semibold">City / Kabupaten</label>
                    <input id="loc_city" name="city" type="text" required maxlength="120" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" placeholder="Jakarta" />
                </div>
                <div>
                    <label for="loc_province" class="text-xs font-semibold">Province (optional)</label>
                    <input id="loc_province" name="province" type="text" maxlength="120" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" placeholder="DKI Jakarta" />
                </div>
                <div>
                    <label for="loc_country" class="text-xs font-semibold">Country</label>
                    <input id="loc_country" name="country" type="text" required maxlength="120" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" placeholder="Indonesia" />
                </div>
                <div>
                    <label for="loc_lat" class="text-xs font-semibold">Latitude</label>
                    <input id="loc_lat" name="latitude" type="number" step="0.000001" min="-90" max="90" required class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" placeholder="-6.105" />
                </div>
                <div>
                    <label for="loc_lng" class="text-xs font-semibold">Longitude</label>
                    <input id="loc_lng" name="longitude" type="number" step="0.000001" min="-180" max="180" required class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" placeholder="106.88" />
                </div>
                <div class="flex items-end">
                    <button type="submit" class="inline-flex h-9 items-center rounded-md bg-[#C6F432] px-4 text-xs font-semibold text-[#080B0C]">Register location</button>
                </div>
            </div>
        </form>
    @endif

    <div class="overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)]">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[var(--line)] bg-[var(--bg-base)] text-xs opacity-75">
                        <th class="px-5 py-3 font-semibold">Location name</th>
                        <th class="px-5 py-3 font-semibold">UN/LOCODE</th>
                        <th class="px-5 py-3 font-semibold">City / Kabupaten &amp; Provinsi</th>
                        <th class="px-5 py-3 font-semibold">Country</th>
                        <th class="px-5 py-3 font-semibold">WGS84 coordinates</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach ($locations as $loc)
                        <tr>
                            <td class="px-5 py-3 text-xs font-semibold">{{ $loc->name }}</td>
                            <td class="px-5 py-3 font-mono text-xs font-bold">{{ $loc->code }}</td>
                            <td class="px-5 py-3 text-xs">{{ $loc->city }}{{ $loc->province ? ' · Prov. ' . $loc->province : '' }}</td>
                            <td class="px-5 py-3 text-xs">{{ $loc->country }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ number_format($loc->latitude, 4) }}, {{ number_format($loc->longitude, 4) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
