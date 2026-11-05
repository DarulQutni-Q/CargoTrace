@extends('layouts.app')

@section('title', 'Port Locations')
@section('header_title', 'Port locations')
@section('header_subtitle', $locations->count() . ' active ports & 38 Indonesian provinces catalog')

@section('content')
<div class="mx-auto max-w-[1440px] space-y-5 p-3 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] px-4 py-4 sm:px-5">
        <div>
            <h2 class="text-base font-semibold">Port, Kabupaten/Kota &amp; Terminal Registry (38 Provinsi Indonesia)</h2>
            <p class="mt-0.5 text-xs opacity-75">Includes Aceh (Bireuen, Krueng Geukuh, Lhokseumawe, Sabang, Malahayati) and global hubs</p>
        </div>
    </div>

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
