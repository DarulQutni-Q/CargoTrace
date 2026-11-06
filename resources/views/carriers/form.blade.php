@extends('layouts.app')

@section('title', $carrier ? 'Edit carrier' : 'Add carrier')
@section('header_title', $carrier ? 'Edit carrier' : 'Add carrier')
@section('header_subtitle', $carrier ? $carrier->name . ' (' . $carrier->code . ')' : 'Register a carrier on the corridor')

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

    <form method="POST" action="{{ $carrier ? route('carriers.update', $carrier) : route('carriers.store') }}" class="space-y-5 rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-6">
        @csrf
        @if ($carrier)
            @method('PUT')
        @endif

        <div>
            <label for="carrier_name" class="text-xs font-semibold">Carrier name *</label>
            <input id="carrier_name" name="name" type="text" required maxlength="120" value="{{ old('name', $carrier->name ?? '') }}" placeholder="Meridian Air Cargo" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            @error('name')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="carrier_code" class="text-xs font-semibold">Carrier code *</label>
            <input id="carrier_code" name="code" type="text" required maxlength="16" value="{{ old('code', $carrier->code ?? '') }}" placeholder="MAC" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs uppercase" />
            @error('code')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="service_type" class="text-xs font-semibold">Transport mode</label>
            <select id="service_type" name="service_type" required class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                @foreach (['SEA', 'AIR', 'ROAD'] as $mode)
                    <option value="{{ $mode }}" {{ old('service_type', $carrier->service_type ?? 'SEA') === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                @endforeach
            </select>
            @error('service_type')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="contact_email" class="text-xs font-semibold">Dispatch email</label>
            <input id="contact_email" name="contact_email" type="email" maxlength="180" value="{{ old('contact_email', $carrier->contact_email ?? '') }}" placeholder="desk@example.com" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            @error('contact_email')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-center gap-2 text-xs font-medium">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $carrier->is_active ?? true) ? 'checked' : '' }} class="size-4 accent-[#080B0C]" />
            Active for new shipment bookings
        </label>

        <div class="flex items-center gap-2 pt-1">
            <button type="submit" class="inline-flex h-9 items-center rounded-md bg-[#C6F432] px-4 text-xs font-semibold text-[#080B0C]">Save carrier</button>
            <a href="{{ route('carriers.index') }}" class="inline-flex h-9 items-center rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-4 text-xs font-medium">Cancel</a>
        </div>
    </form>
</div>
@endsection
