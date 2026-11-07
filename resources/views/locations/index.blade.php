@extends('layouts.app')

@section('title', 'Port Locations')
@section('header_title', 'Port locations')
@section('header_subtitle', $allLocations->count() . ' active ports & regional hubs (' . count($presets) . '+ searchable catalog presets)')

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
            <h2 class="text-base font-semibold">Port, Kabupaten/Kota &amp; Terminal Registry</h2>
            <p class="mt-0.5 text-xs font-medium text-[var(--ink-3)]">Search active locations or 38 Indonesian provinces (Aceh: Bireuen, Krueng Geukuh, Lhokseumawe, Sabang, Malahayati, etc.)</p>
        </div>
        <form method="GET" action="{{ route('locations.index') }}" class="flex w-full flex-wrap items-center gap-2.5 sm:w-auto">
            <div class="relative w-full sm:w-80">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-[var(--ink-3)]"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="q" value="{{ $q }}" placeholder="Search port, code, Bireuen, Krueng Geukuh..." aria-label="Search port locations" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] pl-8 pr-3 text-xs font-medium placeholder:text-[var(--ink-3)]" />
            </div>
            <select name="region" onchange="this.form.submit()" aria-label="Filter by region or Indonesian province" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs font-medium sm:w-auto">
                <option value="ALL" {{ $region === 'ALL' ? 'selected' : '' }}>All Countries &amp; Provinces ({{ $allLocations->count() }})</option>
                <option value="ID_ALL" {{ $region === 'ID_ALL' ? 'selected' : '' }}>Indonesia — All 38 Provinces</option>
                <option value="GLOBAL" {{ $region === 'GLOBAL' ? 'selected' : '' }}>International / Global Ports</option>
                <optgroup label="Filter by Provinsi Indonesia (38 Provinsi)">
                    @foreach ($provinces as $prov)
                        <option value="{{ $prov }}" {{ $region === $prov ? 'selected' : '' }}>Provinsi {{ $prov }}</option>
                    @endforeach
                </optgroup>
            </select>
            @if ($q !== '' || $region !== 'ALL')
                <a href="{{ route('locations.index') }}" class="inline-flex h-8 items-center rounded-md border border-[var(--line)] bg-[var(--bg-raised)] px-2.5 text-xs font-medium">Reset</a>
            @endif
            @if (auth()->user()->canOperateCorridor())
                <a href="{{ route('locations.create') }}" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C]">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    Add location
                </a>
            @endif
        </form>
    </div>

    @if ($quickAdd->isNotEmpty())
        <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4">
            <div class="mb-2.5 flex items-center justify-between gap-2">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[var(--ink-2)]"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/></svg>
                    @if ($q !== '')
                        Matching catalog hubs for "{{ $q }}" (1-click add to active registry)
                    @elseif ($region !== 'ALL' && $region !== 'ID_ALL' && $region !== 'GLOBAL')
                        Available catalog hubs in Provinsi {{ $region }} (1-click add)
                    @else
                        Quick-add Indonesian Regional &amp; Aceh Hubs (Bireuen, Krueng Geukuh, Lhokseumawe, Sabang)
                    @endif
                </span>
                <span class="font-mono text-[11px] font-medium text-[var(--ink-3)]">{{ count($presets) }} total presets in catalog</span>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($quickAdd as $preset)
                    <div class="flex items-center justify-between gap-2 rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 py-2 text-xs">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="rounded bg-[var(--tint-strong)] px-1.5 py-0.5 font-mono text-[10px] font-bold">{{ $preset['code'] }}</span>
                                <span class="truncate font-semibold">{{ $preset['name'] }}</span>
                            </div>
                            <p class="mt-0.5 truncate text-[11px] font-medium text-[var(--ink-3)]">{{ $preset['city'] }}{{ $preset['province'] ? ' · Prov. ' . $preset['province'] : ' · ' . $preset['country'] }}</p>
                        </div>
                        <form method="POST" action="{{ route('locations.quick-add') }}" class="shrink-0">
                            @csrf
                            <input type="hidden" name="code" value="{{ $preset['code'] }}" />
                            <button type="submit" class="rounded border border-[var(--line-strong)] bg-[var(--bg-surface)] px-2.5 py-1 text-[11px] font-semibold transition-colors hover:border-transparent hover:bg-[#C6F432] hover:text-[#080B0C]">+ Add</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)]">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--line)] bg-[var(--bg-base)] px-4 py-2.5 text-xs sm:px-5">
            <span class="font-semibold">Active Port Locations ({{ $locations->count() }} of {{ $allLocations->count() }})</span>
            @if ($q !== '' || $region !== 'ALL')
                <span class="font-mono text-[11px] text-[var(--ink-3)]">Filtered view · {{ $quickAdd->count() }} additional catalog matches above</span>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[var(--line)] bg-[var(--bg-base)] text-xs opacity-75">
                        <th class="px-5 py-3 font-semibold">Location name</th>
                        <th class="px-5 py-3 font-semibold">UN/LOCODE</th>
                        <th class="px-5 py-3 font-semibold">City / Kabupaten &amp; Provinsi</th>
                        <th class="px-5 py-3 font-semibold">Country</th>
                        <th class="px-5 py-3 font-semibold">WGS84 coordinates</th>
                        <th class="px-5 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @forelse ($locations as $loc)
                        <tr class="hover:bg-[var(--bg-raised)]">
                            <td class="px-5 py-3 text-xs">{{ $loc->name }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $loc->code }}</td>
                            <td class="px-5 py-3 text-xs font-medium">{{ $loc->city }}</td>
                            <td class="px-5 py-3 text-xs font-medium">{{ $loc->country }}</td>
                            <td class="px-5 py-3 font-mono text-xs font-medium tabular-nums">{{ number_format($loc->latitude, 4) }}, {{ number_format($loc->longitude, 4) }}</td>
                            <td class="px-5 py-3 text-right">
                                @if (auth()->user()->canOperateCorridor())
                                    <a href="{{ route('locations.edit', $loc) }}" class="text-xs">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-xs opacity-70">
                                No active registered locations match your filter.
                                @if ($quickAdd->isNotEmpty())
                                    Click "+ Add" on one of the matching catalog hubs above to activate it immediately.
                                @else
                                    Click "Add location" to register a custom port or terminal.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
