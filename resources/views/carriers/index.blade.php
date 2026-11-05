@extends('layouts.app')

@section('title', 'Carriers')
@section('header_title', 'Carriers')
@section('header_subtitle', $carriers->count() . ' registered logistics carriers')

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

    @if (auth()->user()->canOperateCorridor())
        <form method="POST" action="{{ route('carriers.store') }}" class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-5">
            @csrf
            <h2 class="text-sm font-semibold">Register a new carrier</h2>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="carrier_name" class="text-xs font-semibold">Carrier name</label>
                    <input id="carrier_name" name="name" type="text" required maxlength="120" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" placeholder="Meridian Air Cargo" />
                </div>
                <div>
                    <label for="carrier_code" class="text-xs font-semibold">Code</label>
                    <input id="carrier_code" name="code" type="text" required maxlength="16" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" placeholder="MAC" />
                </div>
                <div>
                    <label for="service_type" class="text-xs font-semibold">Transport mode</label>
                    <select id="service_type" name="service_type" required class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                        <option value="SEA">SEA</option>
                        <option value="AIR">AIR</option>
                        <option value="ROAD">ROAD</option>
                    </select>
                </div>
                <div>
                    <label for="contact_email" class="text-xs font-semibold">Dispatch email</label>
                    <input id="contact_email" name="contact_email" type="email" maxlength="180" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" placeholder="desk@example.com" />
                </div>
                <div class="flex items-end">
                    <button type="submit" class="inline-flex h-9 items-center rounded-md bg-[#C6F432] px-4 text-xs font-semibold text-[#080B0C]">Register carrier</button>
                </div>
            </div>
        </form>
    @endif

    <div class="overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)]">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[var(--line)] bg-[var(--bg-base)] text-xs opacity-75">
                        <th class="px-5 py-3 font-medium">Carrier name</th>
                        <th class="px-5 py-3 font-medium">Code</th>
                        <th class="px-5 py-3 font-medium">Transport mode</th>
                        <th class="px-5 py-3 font-medium">Dispatch email</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach ($carriers as $carrier)
                        <tr>
                            <td class="px-5 py-3 text-xs font-semibold">{{ $carrier->name }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $carrier->code }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $carrier->service_type }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $carrier->contact_email ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs">{{ $carrier->is_active ? 'Active' : 'Inactive' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
