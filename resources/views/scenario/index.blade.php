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
        <h2 class="text-sm font-semibold">Group scenario &amp; deterministic delay rule</h2>
        <p class="mt-0.5 text-xs opacity-70">Select a group preset or configure custom delay thresholds. Changes immediately recalculate delay classifications across all shipments and write an audit log entry.</p>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($presets as $code => $preset)
                <form method="POST" action="{{ route('scenario.update') }}">
                    @csrf
                    <input type="hidden" name="group_code" value="{{ $preset['group_code'] }}" />
                    <input type="hidden" name="route_corridor" value="{{ $preset['route_corridor'] }}" />
                    <input type="hidden" name="low_threshold_hours" value="{{ $preset['low_threshold_hours'] }}" />
                    <input type="hidden" name="high_threshold_hours" value="{{ $preset['high_threshold_hours'] }}" />
                    <input type="hidden" name="primary_exception" value="{{ $preset['primary_exception'] }}" />
                    <input type="hidden" name="kpi_focus" value="{{ $preset['kpi_focus'] }}" />
                    <button type="submit" class="block w-full rounded-md border p-3 text-left text-xs {{ ($scenario->group_code ?? 'DEFAULT') === $code ? 'border-[#080B0C] bg-[#C6F432] text-[#080B0C]' : 'border-[var(--line)] bg-[var(--bg-base)] hover:bg-[var(--bg-raised)]' }}">
                        <span class="flex items-center justify-between font-mono font-bold">{{ $code }} @if (($scenario->group_code ?? 'DEFAULT') === $code) <span>● Active</span> @endif</span>
                        <span class="mt-1 block font-mono">Low ≤ {{ $preset['low_threshold_hours'] }}h · Med ≤ {{ $preset['high_threshold_hours'] }}h</span>
                        <span class="mt-0.5 block opacity-80">{{ $preset['route_corridor'] }}</span>
                        <span class="mt-0.5 block opacity-80">{{ $preset['primary_exception'] }} · {{ $preset['kpi_focus'] }}</span>
                    </button>
                </form>
            @endforeach
        </div>

        <form method="POST" action="{{ route('scenario.update') }}" class="mt-5 grid grid-cols-1 gap-4 border-t border-[var(--line)] pt-5 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <div>
                <label for="group_code" class="text-xs font-semibold">Group code</label>
                <select id="group_code" name="group_code" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs">
                    @foreach (['DEFAULT', 'GROUP_A', 'GROUP_B', 'GROUP_C', 'CUSTOM'] as $code)
                        <option value="{{ $code }}" {{ ($scenario->group_code ?? 'DEFAULT') === $code ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="low_threshold_hours" class="text-xs font-semibold">Low threshold (hours)</label>
                <input id="low_threshold_hours" name="low_threshold_hours" type="number" min="1" max="168" required value="{{ $scenario->low_threshold_hours ?? 6 }}" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" />
            </div>
            <div>
                <label for="high_threshold_hours" class="text-xs font-semibold">Medium / high threshold (hours)</label>
                <input id="high_threshold_hours" name="high_threshold_hours" type="number" min="2" max="720" required value="{{ $scenario->high_threshold_hours ?? 24 }}" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" />
            </div>
            <div>
                <label for="route_corridor" class="text-xs font-semibold">Assigned route corridor</label>
                <input id="route_corridor" name="route_corridor" type="text" required maxlength="180" value="{{ $scenario->route_corridor ?? 'Southeast Asia → Europe' }}" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            </div>
            <div>
                <label for="primary_exception" class="text-xs font-semibold">Primary exception type</label>
                <select id="primary_exception" name="primary_exception" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs">
                    @foreach (\App\Models\ShipmentException::TYPES as $type)
                        <option value="{{ $type }}" {{ ($scenario->primary_exception ?? 'CUSTOMS_HOLD') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="kpi_focus" class="text-xs font-semibold">KPI focus</label>
                <input id="kpi_focus" name="kpi_focus" type="text" required maxlength="180" value="{{ $scenario->kpi_focus ?? 'On-Time Delivery & Exception Rate' }}" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <button type="submit" class="inline-flex h-9 items-center rounded-md bg-[#C6F432] px-4 text-xs font-semibold text-[#080B0C]">Save scenario rule</button>
            </div>
        </form>
    </div>

    <!-- User accounts & role access -->
    <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-5">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold">User accounts &amp; role access</h2>
                <p class="mt-0.5 text-xs opacity-70">Administrator, logistics operator, and customer accounts</p>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[var(--line)] text-xs opacity-75">
                        <th class="py-2 pr-3 font-medium">Name</th>
                        <th class="py-2 pr-3 font-medium">Email</th>
                        <th class="py-2 pr-3 font-medium">Role</th>
                        <th class="py-2 font-medium">Change role</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach ($users as $u)
                        <tr>
                            <td class="py-2.5 pr-3 text-xs font-semibold">{{ $u->name }}</td>
                            <td class="py-2.5 pr-3 font-mono text-xs">{{ $u->email }}</td>
                            <td class="py-2.5 pr-3 font-mono text-xs font-semibold">{{ $u->role }}</td>
                            <td class="py-2.5">
                                <form method="POST" action="{{ route('users.role.update', $u) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" class="h-8 rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 font-mono text-xs">
                                        @foreach (['ADMIN', 'LOGISTICS_OPERATOR', 'CUSTOMER'] as $role)
                                            <option value="{{ $role }}" {{ $u->role === $role ? 'selected' : '' }}>{{ $role }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="text-xs font-medium underline">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('users.store') }}" class="mt-5 grid grid-cols-1 gap-4 border-t border-[var(--line)] pt-5 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div>
                <label for="user_name" class="text-xs font-semibold">Name</label>
                <input id="user_name" name="name" type="text" required maxlength="120" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" placeholder="Dewi Lestari" />
            </div>
            <div>
                <label for="user_email" class="text-xs font-semibold">Email</label>
                <input id="user_email" name="email" type="email" required maxlength="180" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" placeholder="dewi@cargotrace.io" />
            </div>
            <div>
                <label for="user_password" class="text-xs font-semibold">Password (min 8)</label>
                <input id="user_password" name="password" type="password" required minlength="8" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            </div>
            <div>
                <label for="user_role" class="text-xs font-semibold">Role</label>
                <select id="user_role" name="role" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs">
                    <option value="CUSTOMER">CUSTOMER</option>
                    <option value="LOGISTICS_OPERATOR">LOGISTICS_OPERATOR</option>
                    <option value="ADMIN">ADMIN</option>
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <button type="submit" class="inline-flex h-9 items-center rounded-md bg-[#C6F432] px-4 text-xs font-semibold text-[#080B0C]">Add user</button>
            </div>
        </form>
    </div>
</div>
@endsection
