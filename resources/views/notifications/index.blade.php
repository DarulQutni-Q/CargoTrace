@extends('layouts.app')

@section('title', 'Notifications')
@section('header_title', 'Notifications')
@section('header_subtitle', $unreadCount . ' unread')

@section('content')
<div class="mx-auto max-w-[900px] space-y-5 p-3 sm:p-6">
    @if (session('status'))
        <div class="rounded-md border border-[#C6F432] bg-[#C6F432]/20 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    <div class="flex items-center justify-between rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] px-4 py-3.5 sm:px-5">
        <div>
            <h2 class="text-sm font-semibold">Event dispatch log</h2>
            <p class="mt-0.5 text-xs opacity-70">Alerts generated on status transitions, exception events, and delivery completion.</p>
        </div>
        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="inline-flex h-8 items-center rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium">Mark all read</button>
            </form>
        @endif
    </div>

    <div class="space-y-3">
        @forelse ($notifications as $n)
            <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] px-4 py-3.5 sm:px-5 {{ $n->read_at ? 'opacity-70' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded border border-[var(--line)] bg-[var(--bg-base)] px-2 py-0.5 font-mono font-semibold">{{ $n->type }}</span>
                            <time class="font-mono opacity-70">{{ $n->created_at->format('M j, Y · H:i') }}</time>
                            @if (! $n->read_at)
                                <span class="rounded bg-[#C6F432] px-2 py-0.5 font-mono text-[10px] font-bold text-[#080B0C]">UNREAD</span>
                            @endif
                        </div>
                        <h3 class="mt-2 text-sm font-semibold">{{ $n->title }}</h3>
                        <p class="mt-0.5 text-xs leading-relaxed opacity-80">{{ $n->body }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-2">
                        @if ($n->shipment_id)
                            <a href="{{ route('shipments.show', $n->shipment_id) }}" class="rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 py-1 font-mono text-[11px] font-semibold">View shipment →</a>
                        @endif
                        @if (! $n->read_at)
                            <form method="POST" action="{{ route('notifications.read', $n) }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium underline">Mark read</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] px-5 py-10 text-center text-sm opacity-70">
                No notifications yet. Status changes and exceptions on your shipments will appear here.
            </div>
        @endforelse
    </div>

    <div class="pb-4">
        {{ $notifications->links() }}
    </div>
</div>
@endsection
