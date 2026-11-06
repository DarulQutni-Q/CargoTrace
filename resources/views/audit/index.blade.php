@extends('layouts.app')

@section('title', 'Audit Log')
@section('header_title', 'Audit log')
@section('header_subtitle', $logs->count() . ' recorded state transitions and configuration updates')

@section('content')
<div class="mx-auto max-w-[1440px] space-y-5 p-3 sm:p-6">
    <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] px-4 py-3.5 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold">Immutable Audit Trail</h2>
                <p class="mt-0.5 text-xs opacity-70">Filter state transitions by actor, action type, entity ID, or field delta</p>
            </div>
            <form method="GET" action="{{ route('audit.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ $q }}" placeholder="Search actor, entity, field delta..." class="h-9 w-72 rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
                <select name="action" onchange="this.form.submit()" class="h-9 rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                    <option value="">All actions ({{ $logs->count() }})</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}" {{ $action === $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex h-9 items-center rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C]">Filter</button>
                @if ($q !== '' || $action !== '')
                    <a href="{{ route('audit.index') }}" class="text-xs font-medium underline">Clear</a>
                @endif
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)]">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[var(--line)] bg-[var(--bg-base)] text-xs opacity-75">
                        <th class="px-5 py-3 font-medium">Timestamp</th>
                        <th class="px-5 py-3 font-medium">Actor</th>
                        <th class="px-5 py-3 font-medium">Action</th>
                        <th class="px-5 py-3 font-medium">Entity</th>
                        <th class="px-5 py-3 font-medium">State delta</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @forelse ($logs as $log)
                        <tr class="align-top hover:bg-[var(--bg-raised)]">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs tabular-nums text-[var(--ink-3)]">{{ $log->created_at?->format('M j, Y · H:i') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-xs font-medium">{{ $log->actor->name ?? 'System' }}</td>
                            <td class="px-5 py-3">@include('partials.badge', ['tone' => 'neutral', 'label' => $log->action])</td>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                            <td class="px-5 py-3 text-xs">
                                @if (empty($log->changes))
                                    <span class="text-[var(--ink-3)]">Initial creation</span>
                                @else
                                    <div class="space-y-1">
                                        @foreach ($log->changes as $field => $delta)
                                            <div><span class="font-medium">{{ $field }}</span>: <span class="font-mono text-[var(--ink-3)]">{{ is_array($delta) ? json_encode($delta['from'] ?? null) : json_encode($delta) }}</span> → <span class="font-mono">{{ is_array($delta) ? json_encode($delta['to'] ?? null) : '' }}</span></div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-xs opacity-70">No audit log entries match the active search or action filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
