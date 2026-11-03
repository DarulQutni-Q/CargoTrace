<x-layouts.app title="Carriers">
    <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-ink-2">{{ $carriers->total() }} carriers</p>
            <a href="{{ route('carriers.create') }}"
               class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-accent-strong px-3 text-sm font-medium text-white transition-colors hover:bg-accent">
                <x-lucide-plus class="size-4" stroke-width="1.75"/>
                Add carrier
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-line bg-surface">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-xs text-ink-3">
                        <th class="px-4 py-3 font-medium">Carrier</th>
                        <th class="px-4 py-3 font-medium">Code</th>
                        <th class="px-4 py-3 font-medium">Service</th>
                        <th class="px-4 py-3 font-medium">Contact</th>
                        <th class="px-4 py-3 font-medium">Shipments</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($carriers as $carrier)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink">{{ $carrier->name }}</td>
                            <td class="px-4 py-3 font-mono text-ink-2">{{ $carrier->code }}</td>
                            <td class="px-4 py-3 text-ink-2">{{ $carrier->service_type ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-3">{{ $carrier->contact_email ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-ink-2">{{ $carrier->shipments_count }}</td>
                            <td class="px-4 py-3">
                                <x-badge :tone="$carrier->is_active ? 'green' : 'neutral'">{{ $carrier->is_active ? 'Active' : 'Inactive' }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('carriers.edit', $carrier) }}" class="text-xs text-accent hover:underline">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-ink-3">No carriers yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
