@extends('layouts.app')

@section('title', 'Scenario & users')
@section('header_title', 'Scenario & users')
@section('header_subtitle', 'Live delay threshold tuning and role-based access control')

@section('content')
<div class="mx-auto max-w-[1100px] space-y-5 p-3 sm:p-6">
    @if (session('status'))
        <div class="rounded-md border border-[#C6F432] bg-[#C6F432]/20 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Group scenario & deterministic delay rule -->
    <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-semibold">Group scenario &amp; deterministic delay rule</h2>
                <p class="mt-0.5 max-w-3xl text-xs opacity-70">Select a group preset or configure custom delay thresholds. Changes immediately recalculate delay classifications across all shipments and write an audit log entry.</p>
            </div>
            <form method="POST" action="{{ route('scenario.reset') }}" onsubmit="return confirm('Reset the demo dataset to the seeded state? All changes since the last seed will be lost.');">
                @csrf
                <button type="submit" class="inline-flex h-8 shrink-0 items-center rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium">Reset seed dataset</button>
            </form>
        </div>

        <form method="POST" action="{{ route('scenario.update') }}" class="mt-4 grid grid-cols-1 divide-y divide-[var(--line)] overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-base)] sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-4 lg:divide-x">
            @csrf
            @foreach ($presets as $code => $preset)
                <button type="submit" name="preset" value="{{ $code }}" class="block w-full p-3 text-left text-xs {{ ($scenario->group_code ?? 'DEFAULT') === $code ? 'bg-[var(--bg-raised)]' : 'hover:bg-[var(--bg-raised)]' }}">
                    <span class="flex items-center justify-between font-mono font-bold">{{ $code }} @if (($scenario->group_code ?? 'DEFAULT') === $code) @include('partials.badge', ['tone' => 'green', 'label' => 'Active']) @endif</span>
                    <span class="mt-1 block font-mono">Low ≤ {{ $preset['low_threshold_hours'] }}h · Med ≤ {{ $preset['high_threshold_hours'] }}h</span>
                    <span class="mt-0.5 block truncate opacity-80">{{ $preset['route_corridor'] }}</span>
                    <span class="mt-0.5 block opacity-80">{{ \App\Models\ShipmentException::typeLabel($preset['primary_exception']) }}</span>
                </button>
            @endforeach
        </form>

        <form method="POST" action="{{ route('scenario.update') }}" class="mt-5 grid grid-cols-1 gap-4 border-t border-[var(--line)] pt-5 sm:grid-cols-3">
            @csrf
            <input type="hidden" name="group_code" id="scenario_group_code" value="{{ $scenario->group_code ?? 'DEFAULT' }}" />
            <input type="hidden" name="kpi_focus" value="{{ $scenario->kpi_focus ?? 'On-Time Delivery & Exception Rate' }}" />
            <div>
                <label for="low_threshold_hours" class="text-xs font-semibold">Low threshold (hours)</label>
                <input id="low_threshold_hours" name="low_threshold_hours" type="number" min="1" max="168" required value="{{ $scenario->low_threshold_hours ?? 6 }}" onchange="document.getElementById('scenario_group_code').value = 'CUSTOM'" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" />
            </div>
            <div>
                <label for="high_threshold_hours" class="text-xs font-semibold">Medium / high threshold (hours)</label>
                <input id="high_threshold_hours" name="high_threshold_hours" type="number" min="2" max="720" required value="{{ $scenario->high_threshold_hours ?? 24 }}" onchange="document.getElementById('scenario_group_code').value = 'CUSTOM'" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" />
            </div>
            <div>
                <label for="primary_exception" class="text-xs font-semibold">Primary exception type</label>
                <select id="primary_exception" name="primary_exception" onchange="document.getElementById('scenario_group_code').value = 'CUSTOM'" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs">
                    @foreach (\App\Models\ShipmentException::TYPES as $type)
                        <option value="{{ $type }}" {{ ($scenario->primary_exception ?? 'CUSTOMS_HOLD') === $type ? 'selected' : '' }}>{{ \App\Models\ShipmentException::typeLabel($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="route_corridor" class="text-xs font-semibold">Assigned route corridor</label>
                <input id="route_corridor" name="route_corridor" type="text" required maxlength="180" value="{{ $scenario->route_corridor ?? 'Southeast Asia → Europe' }}" onchange="document.getElementById('scenario_group_code').value = 'CUSTOM'" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            </div>
            <div class="flex items-end justify-end">
                <button type="submit" class="inline-flex h-9 items-center rounded-md bg-[#C6F432] px-4 text-xs font-semibold text-[#080B0C]">Save scenario rule</button>
            </div>
        </form>
    </div>

    <!-- User accounts & role access -->
    <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold">User accounts &amp; role access</h2>
                <p class="mt-0.5 text-xs opacity-70">Administrator, logistics operator, and customer accounts</p>
            </div>
            <button type="button" onclick="toggleUserForm(null)" class="inline-flex h-8 items-center rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C]">Add user</button>
        </div>

        <form id="user-form" method="POST" action="{{ route('users.store') }}" class="mt-4 hidden grid grid-cols-1 gap-4 border border-[var(--line)] bg-[var(--bg-base)] p-4 sm:grid-cols-4">
            @csrf
            <input type="hidden" name="_method" id="user-form-method" value="POST" />
            <div>
                <label for="user_name" class="text-xs font-semibold">Name *</label>
                <input id="user_name" name="name" type="text" required maxlength="120" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" placeholder="Dewi Lestari" />
            </div>
            <div>
                <label for="user_email" class="text-xs font-semibold">Email *</label>
                <input id="user_email" name="email" type="email" required maxlength="180" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" placeholder="dewi@cargotrace.io" />
            </div>
            <div>
                <label for="user_role" class="text-xs font-semibold">Role *</label>
                <select id="user_role" name="role" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs">
                    <option value="ADMIN">Administrator</option>
                    <option value="LOGISTICS_OPERATOR">Logistics Operator</option>
                    <option value="CUSTOMER" selected>Customer</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex h-9 items-center rounded-md bg-[#C6F432] px-4 text-xs font-semibold text-[#080B0C]">Save</button>
                <button type="button" onclick="toggleUserForm(null)" class="inline-flex h-9 items-center rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-4 text-xs font-medium">Cancel</button>
            </div>
        </form>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[var(--line)] text-xs opacity-75">
                        <th class="py-2 pr-3 font-medium">ID</th>
                        <th class="py-2 pr-3 font-medium">Name</th>
                        <th class="py-2 pr-3 font-medium">Email</th>
                        <th class="py-2 pr-3 font-medium">Role</th>
                        <th class="py-2 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach ($users as $u)
                        <tr>
                            <td class="py-2.5 pr-3 font-mono text-xs text-[var(--ink-3)]">#{{ $u->id }}</td>
                            <td class="py-2.5 pr-3 text-xs">{{ $u->name }}</td>
                            <td class="py-2.5 pr-3 font-mono text-xs">{{ $u->email }}</td>
                            <td class="py-2.5 pr-3">
                                @php
                                    $roleTone = $u->role === 'ADMIN' ? 'amber' : ($u->role === 'LOGISTICS_OPERATOR' ? 'blue' : 'green');
                                    $roleLabel = $u->role === 'ADMIN' ? 'Administrator' : ($u->role === 'LOGISTICS_OPERATOR' ? 'Logistics Operator' : 'Customer');
                                @endphp
                                @include('partials.badge', ['tone' => $roleTone, 'label' => $roleLabel])
                            </td>
                            <td class="py-2.5 text-right">
                                <button type="button" onclick="toggleUserForm({{ $u->id }}, '{{ addslashes($u->name) }}', '{{ $u->email }}', '{{ $u->role }}')" class="text-xs">Edit role</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function toggleUserForm(id, name, email, role) {
        const form = document.getElementById('user-form');
        const method = document.getElementById('user-form-method');
        if (!form) return;
        if (id === null) {
            form.action = '{{ route('users.store') }}';
            method.value = 'POST';
            document.getElementById('user_name').value = '';
            document.getElementById('user_email').value = '';
            document.getElementById('user_role').value = 'CUSTOMER';
            form.classList.toggle('hidden');
            return;
        }
        form.action = '/users/' + id;
        method.value = 'PATCH';
        document.getElementById('user_name').value = name || '';
        document.getElementById('user_email').value = email || '';
        document.getElementById('user_role').value = role || 'CUSTOMER';
        form.classList.remove('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
</script>
@endpush
@endsection
