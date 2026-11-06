@extends('layouts.app')

@section('title', 'Notifications')
@section('header_title', 'Notifications')
@section('header_subtitle', $notifications->count() . ' notifications · ' . $unreadCount . ' unread')

@section('content')
<div class="mx-auto max-w-4xl space-y-5 p-3 sm:p-6">
    @if (session('status'))
        <div class="rounded-md border border-[#C6F432] bg-[#C6F432]/20 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    <div class="flex items-center justify-between rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] px-4 py-3.5 sm:px-5">
        <div>
            <h2 class="text-base font-semibold">Event dispatch log</h2>
            <p class="mt-0.5 text-xs opacity-70">Alerts generated on status transitions, exception events, and delivery completion</p>
        </div>
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="inline-flex h-8 shrink-0 items-center rounded-md border border-[var(--line)] bg-[var(--bg-raised)] px-3 text-xs font-medium">Mark all read</button>
        </form>
    </div>

    <div class="divide-y divide-[var(--line)] overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)]">
        @forelse ($notifications as $n)
            <div class="flex items-start justify-between gap-3 p-5 {{ $n->read_at ? '' : 'bg-[var(--bg-raised)]/60' }}">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        @php
                            $notifTone = str_contains($n->type, 'EXCEPTION') ? 'red' : (str_contains($n->type, 'RESOLVED') ? 'green' : 'neutral');
                        @endphp
                        @include('partials.badge', ['tone' => $notifTone, 'label' => $n->type])
                        <time class="font-mono text-xs text-[var(--ink-3)]">{{ $n->created_at->format('M j, Y · H:i') }}</time>
                    </div>
                    <h3 class="mt-2 text-sm font-medium">{{ $n->title }}</h3>
                    <p class="mt-0.5 text-xs leading-relaxed opacity-80">{{ $n->body }}</p>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-2">
                    @if ($n->shipment)
                        <a href="{{ route('shipments.show', $n->shipment) }}" class="rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 py-1 font-mono text-[11px] font-semibold">{{ $n->shipment->shipment_number }} →</a>
                    @endif
                    @if (! $n->read_at)
                        <form method="POST" action="{{ route('notifications.read', $n) }}">
                            @csrf
                            <button type="submit" class="text-xs font-medium">Mark read</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm opacity-70">
                No notifications yet. Status changes and exceptions on your shipments will appear here.
            </div>
        @endforelse
    </div>
</div>
@endsection
