<x-layouts.app title="{{ isset($location->id) ? 'Edit location' : 'Add location' }}">
    <div class="mx-auto max-w-xl p-6">
        <h2 class="text-xl font-semibold">{{ isset($location->id) ? 'Edit location' : 'Add location' }}</h2>
        <p class="mt-1 text-sm text-ink-2">Coordinates are WGS84 decimal degrees — the map places markers with them.</p>

        <form method="POST" action="{{ isset($location->id) ? route('locations.update', $location) : route('locations.store') }}"
              class="mt-6 space-y-4 rounded-xl border border-line bg-surface p-6">
            @csrf
            @isset($location->id) @method('PUT') @endisset

            <div>
                <label for="name" class="mb-1.5 block text-xs font-medium text-ink-2">Name</label>
                <x-input id="name" name="name" value="{{ old('name', $location->name) }}" placeholder="Rotterdam Port"/>
                @error('name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="code" class="mb-1.5 block text-xs font-medium text-ink-2">Code</label>
                    <x-input id="code" name="code" value="{{ old('code', $location->code) }}" placeholder="RTM" class="font-mono uppercase"/>
                    @error('code') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="city" class="mb-1.5 block text-xs font-medium text-ink-2">City</label>
                    <x-input id="city" name="city" value="{{ old('city', $location->city) }}"/>
                    @error('city') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="country" class="mb-1.5 block text-xs font-medium text-ink-2">Country</label>
                <x-input id="country" name="country" value="{{ old('country', $location->country) }}"/>
                @error('country') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="latitude" class="mb-1.5 block text-xs font-medium text-ink-2">Latitude</label>
                    <x-input id="latitude" name="latitude" type="number" step="0.000001" value="{{ old('latitude', $location->latitude) }}"/>
                    @error('latitude') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="longitude" class="mb-1.5 block text-xs font-medium text-ink-2">Longitude</label>
                    <x-input id="longitude" name="longitude" type="number" step="0.000001" value="{{ old('longitude', $location->longitude) }}"/>
                    @error('longitude') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" data-geocode data-geocode-url="{{ route('locations.geocode') }}"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line bg-raised px-3 text-xs text-ink-2 transition-colors hover:border-line-strong hover:text-ink">
                    <x-lucide-search class="size-3.5" stroke-width="1.5"/>
                    Fill coordinates from the map provider
                </button>
                <p class="text-xs text-ink-3" data-geocode-status></p>
            </div>

            @vite('resources/js/location-form.js')

            <div class="flex items-center gap-3 pt-2">
                <x-button>Save location</x-button>
                <a href="{{ route('locations.index') }}" class="text-sm text-ink-2 transition-colors hover:text-ink">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.app>
