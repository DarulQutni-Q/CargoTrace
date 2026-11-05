@extends('layouts.app')

@section('title', 'Bill of Lading · ' . $shipment->shipment_number)
@section('header_title', 'Electronic Bill of Lading (e-B/L)')

@section('content')
<div class="mx-auto max-w-3xl p-4 sm:p-6">
    <div class="rounded-lg border-2 border-[#080B0C] bg-white p-6 text-[#080B0C] shadow-xl">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b-2 border-[#080B0C] pb-4">
            <div>
                <div class="inline-block rounded bg-[#C6F432] px-2 py-0.5 font-mono text-[10px] font-bold uppercase text-[#080B0C]">Official Electronic Bill of Lading (e-B/L)</div>
                <h2 class="mt-1.5 font-mono text-xl font-bold">WAYBILL #{{ $shipment->shipment_number }}</h2>
            </div>
            <button type="button" onclick="window.print()" class="rounded-md border border-[#080B0C] bg-[#C6F432] px-3 py-1.5 text-xs font-bold text-[#080B0C] print:hidden">Print / Save PDF</button>
        </div>

        <div class="mt-4 grid gap-4 border-b border-slate-300 pb-4 text-xs sm:grid-cols-3">
            <div>
                <div class="font-mono text-[10px] font-bold uppercase text-slate-600">1. Origin Port</div>
                <div class="mt-1 font-bold">{{ $shipment->origin->code }} — {{ $shipment->origin->name }}</div>
                <div>{{ $shipment->origin->city }}, {{ $shipment->origin->country }}</div>
            </div>
            <div>
                <div class="font-mono text-[10px] font-bold uppercase text-slate-600">2. Transit Stops ({{ count($corridor['intermediate_stops']) }})</div>
                @forelse ($corridor['intermediate_stops'] as $stop)
                    <div class="mt-1 font-semibold">● {{ $stop['location_name'] }}</div>
                @empty
                    <div class="mt-1 text-slate-600">Direct corridor routing</div>
                @endforelse
            </div>
            <div>
                <div class="font-mono text-[10px] font-bold uppercase text-slate-600">3. Destination Port</div>
                <div class="mt-1 font-bold">{{ $shipment->destination->code }} — {{ $shipment->destination->name }}</div>
                <div>{{ $shipment->destination->city }}, {{ $shipment->destination->country }}</div>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full border-collapse border border-slate-300 text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-300 bg-slate-100 font-mono">
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Description of Goods</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Gross Weight (kg)</th>
                        <th class="px-3 py-2 text-right">Volume (m³)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-300">
                    @foreach ($shipment->items as $idx => $item)
                        <tr>
                            <td class="px-3 py-2 font-mono">{{ $idx + 1 }}</td>
                            <td class="px-3 py-2 font-semibold">{{ $item->description }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ $item->quantity }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($item->weight_kg, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ $item->volume_m3 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
