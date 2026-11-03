<x-layouts.app title="{{ isset($carrier->id) ? 'Edit carrier' : 'Add carrier' }}">
    <div class="mx-auto max-w-xl p-6">
        <h2 class="text-xl font-semibold">{{ isset($carrier->id) ? 'Edit carrier' : 'Add carrier' }}</h2>

        <form method="POST" action="{{ isset($carrier->id) ? route('carriers.update', $carrier) : route('carriers.store') }}"
              class="mt-6 space-y-4 rounded-xl border border-line bg-surface p-6">
            @csrf
            @isset($carrier->id) @method('PUT') @endisset

            <div>
                <label for="name" class="mb-1.5 block text-xs font-medium text-ink-2">Name</label>
                <x-input id="name" name="name" value="{{ old('name', $carrier->name) }}" placeholder="Meridian Air Cargo"/>
                @error('name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="code" class="mb-1.5 block text-xs font-medium text-ink-2">Code</label>
                <x-input id="code" name="code" value="{{ old('code', $carrier->code) }}" placeholder="MAC" class="font-mono uppercase"/>
                @error('code') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="service_type" class="mb-1.5 block text-xs font-medium text-ink-2">Service type</label>
                <select id="service_type" name="service_type"
                        class="w-full rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink focus:border-line-strong focus:outline-none focus:ring-2 focus:ring-accent/50">
                    <option value="">Not specified</option>
                    @foreach (['AIR', 'SEA', 'ROAD'] as $type)
                        <option value="{{ $type }}" @selected(old('service_type', $carrier->service_type) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="contact_email" class="mb-1.5 block text-xs font-medium text-ink-2">Contact email</label>
                <x-input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $carrier->contact_email) }}"/>
                @error('contact_email') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $carrier->is_active ?? true))
                       class="size-4 rounded border-line bg-raised accent-blue-600">
                Accepting new shipments
            </label>

            <div class="flex items-center gap-3 pt-2">
                <x-button>Save carrier</x-button>
                <a href="{{ route('carriers.index') }}" class="text-sm text-ink-2 transition-colors hover:text-ink">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.app>
