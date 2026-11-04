<x-layouts.app title="Audit log">
    <div class="p-6">
        <p class="mb-4 text-sm text-ink-2">{{ $logs->total() }} recorded changes — actor, action, entity, and the values moved</p>

        <div class="overflow-hidden rounded-xl border border-line bg-surface">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-xs text-ink-3">
                        <th class="px-4 py-3 font-medium">Time</th>
                        <th class="px-4 py-3 font-medium">Actor</th>
                        <th class="px-4 py-3 font-medium">Action</th>
                        <th class="px-4 py-3 font-medium">Entity</th>
                        <th class="px-4 py-3 font-medium">Changes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-b border-line last:border-0 align-top">
                            <td class="px-4 py-3 font-mono text-xs text-ink-3">{{ $log->created_at->format('M j · H:i') }}</td>
                            <td class="px-4 py-3 text-ink-2">{{ $log->actor?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-ink">{{ $log->action }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-ink-2">
                                {{ class_basename($log->entity_type) }}#{{ $log->entity_id }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($log->changes)
                                    <div class="space-y-0.5 font-mono text-[11px] text-ink-3">
                                        @foreach ($log->changes as $field => $change)
                                            <p>{{ $field }}: {{ \Illuminate\Support\Str::limit(json_encode($change['from'] ?? null), 24) }} → {{ \Illuminate\Support\Str::limit(json_encode($change['to'] ?? null), 24) }}</p>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-ink-3">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-3">No changes recorded yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <nav class="mt-4 flex items-center justify-between text-xs text-ink-3">
                @if ($logs->onFirstPage())
                    <span class="opacity-50">Prev</span>
                @else
                    <a href="{{ $logs->previousPageUrl() }}" class="hover:text-ink">Prev</a>
                @endif
                <span class="font-mono">{{ $logs->currentPage() }} / {{ $logs->lastPage() }}</span>
                @if ($logs->hasMorePages())
                    <a href="{{ $logs->nextPageUrl() }}" class="hover:text-ink">Next</a>
                @else
                    <span class="opacity-50">Next</span>
                @endif
            </nav>
        @endif
    </div>
</x-layouts.app>
