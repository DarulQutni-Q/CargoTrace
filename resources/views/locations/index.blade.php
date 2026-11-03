<x-layouts.app title="Locations">
    <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-ink-2">{{ $locations->total() }} locations</p>
            <a href="{{ route('locations.create') }}"
               class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-accent-strong px-3 text-sm font-medium text-white transition-colors hover:bg-accent">
                <x-lucide-plus class="size-4" stroke-width="1.75"/>
                Add location
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-line bg-surface">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-xs text-ink-3">
                        <th class="px-4 py-3 font-medium">Location</th>
                        <th class="px-4 py-3 font-medium">Code</th>
                        <th class="px-4 py-3 font-medium">City</th>
                        <th class="px-4 py-3 font-medium">Country</th>
                        <th class="px-4 py-3 font-medium">Coordinates</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink">{{ $location->name }}</td>
                            <td class="px-4 py-3 font-mono text-ink-2">{{ $location->code }}</td>
                            <td class="px-4 py-3 text-ink-2">{{ $location->city }}</td>
                            <td class="px-4 py-3 text-ink-2">{{ $location->country }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-ink-3">{{ $location->latitude }}, {{ $location->longitude }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('locations.edit', $location) }}" class="text-xs text-accent hover:underline">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-ink-3">No locations yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
