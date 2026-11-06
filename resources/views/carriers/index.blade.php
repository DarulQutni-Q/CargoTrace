@extends('layouts.app')

@section('title', 'Carriers')
@section('header_title', 'Carriers')
@section('header_subtitle', 'Sea, air, and road carriers operating across international shipping corridors')

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

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-semibold">Carrier directory</h2>
        <form method="GET" action="{{ route('carriers.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search carrier, code, email..." class="h-8 w-64 rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            <select name="mode" onchange="this.form.submit()" class="h-8 rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                <option value="">All modes</option>
                <option value="SEA" {{ $mode === 'SEA' ? 'selected' : '' }}>SEA</option>
                <option value="AIR" {{ $mode === 'AIR' ? 'selected' : '' }}>AIR</option>
                <option value="ROAD" {{ $mode === 'ROAD' ? 'selected' : '' }}>ROAD</option>
            </select>
            @if (auth()->user()->canOperateCorridor())
                <a href="{{ route('carriers.create') }}" class="inline-flex h-8 items-center rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C]">+ Add carrier</a>
            @endif
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)]">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[var(--line)] bg-[var(--bg-base)] text-xs opacity-75">
                        <th class="px-5 py-3 font-medium">Carrier name</th>
                        <th class="px-5 py-3 font-medium">Code</th>
                        <th class="px-5 py-3 font-medium">Transport mode</th>
                        <th class="px-5 py-3 font-medium">Dispatch email</th>
                        <th class="px-5 py-3 font-medium">Active shipments</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @forelse ($carriers as $carrier)
                        <tr>
                            <td class="px-5 py-3 text-xs font-semibold">{{ $carrier->name }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $carrier->code }}</td>
                            <td class="px-5 py-3">@include('partials.badge', ['tone' => 'neutral', 'label' => $carrier->service_type ?: 'SEA'])</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $carrier->contact_email ?? '—' }}</td>
                            <td class="px-5 py-3 font-mono text-xs tabular-nums">{{ $carrier->shipments_count }}</td>
                            <td class="px-5 py-3">@include('partials.badge', ['tone' => $carrier->is_active ? 'green' : 'neutral', 'label' => $carrier->is_active ? 'Active' : 'Inactive'])</td>
                            <td class="px-5 py-3 text-right">
                                @if (auth()->user()->canOperateCorridor())
                                    <a href="{{ route('carriers.edit', $carrier) }}" class="text-xs font-semibold underline">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-xs opacity-70">No carriers match the active search or mode filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
