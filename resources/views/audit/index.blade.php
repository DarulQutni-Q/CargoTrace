@extends('layouts.app')

@section('title', 'Audit Log')
@section('header_title', 'Audit log')
@section('header_subtitle', $logs->count() . ' recorded state transitions')

@section('content')
<div class="mx-auto max-w-[1440px] space-y-5 p-3 sm:p-6">
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
                    @foreach ($logs as $log)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs">{{ $log->created_at?->format('M j, Y H:i') }}</td>
                            <td class="px-5 py-3 text-xs font-medium">{{ $log->actor->name ?? 'System' }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $log->action }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ json_encode($log->changes) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
