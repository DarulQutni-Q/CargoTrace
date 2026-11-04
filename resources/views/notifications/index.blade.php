<x-layouts.app title="Notifications">
    <div class="mx-auto max-w-3xl space-y-4 p-6">
        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-2">
                {{ $notifications->total() }} notifications ·
                {{ $notifications->getCollection()->filter(fn ($n) => $n->isUnread())->count() }} unread on this page
            </p>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit"
                        class="inline-flex h-8 items-center rounded-lg border border-line bg-raised px-3 text-xs text-ink-2 transition-colors hover:border-line-strong hover:text-ink">
                    Mark all read
                </button>
            </form>
        </div>

        <div class="space-y-2">
            @forelse ($notifications as $item)
                <div class="rounded-xl border p-4 transition-colors {{ $item->isUnread() ? 'border-line-strong bg-raised/40' : 'border-line bg-surface' }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="flex min-w-0 items-start gap-2.5">
                            @if ($item->isUnread())
                                <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-accent"></span>
                            @else
                                <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-transparent"></span>
                            @endif
                            <div class="min-w-0">
                                <p class="text-sm {{ $item->isUnread() ? 'font-medium text-ink' : 'text-ink-2' }}">{{ $item->title }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-ink-3">{{ $item->body }}</p>
                                <p class="mt-1.5 font-mono text-[10px] text-ink-3">
                                    {{ $item->type }} · {{ $item->created_at->format('M j, Y · H:i') }}
                                    @if ($item->shipment)
                                        · <a href="{{ route('shipments.show', $item->shipment) }}" class="text-accent hover:underline">{{ $item->shipment->shipment_number }}</a>
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if ($item->isUnread())
                            <form method="POST" action="{{ route('notifications.read', $item) }}">
                                @csrf
                                <button type="submit" class="text-xs text-ink-3 transition-colors hover:text-ink">Mark read</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-line bg-surface px-4 py-12 text-center">
                    <p class="text-sm text-ink-2">No notifications yet</p>
                    <p class="mt-1 text-xs text-ink-3">Status changes and exceptions on your shipments will show up here</p>
                </div>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <nav class="flex items-center justify-between text-xs text-ink-3">
                @if ($notifications->onFirstPage())
                    <span class="opacity-50">Prev</span>
                @else
                    <a href="{{ $notifications->previousPageUrl() }}" class="hover:text-ink">Prev</a>
                @endif
                <span class="font-mono">{{ $notifications->currentPage() }} / {{ $notifications->lastPage() }}</span>
                @if ($notifications->hasMorePages())
                    <a href="{{ $notifications->nextPageUrl() }}" class="hover:text-ink">Next</a>
                @else
                    <span class="opacity-50">Next</span>
                @endif
            </nav>
        @endif
    </div>
</x-layouts.app>
