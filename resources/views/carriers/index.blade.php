@extends('layouts.app')

@section('title', 'Carriers')
@section('header_title', 'Carriers')
@section('header_subtitle', $carriers->count() . ' registered logistics carriers')

@section('content')
<div class="mx-auto max-w-[1440px] space-y-5 p-3 sm:p-6">
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
