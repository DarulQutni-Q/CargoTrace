<x-layouts.app title="{{ $shipment->shipment_number }}">
    <div class="mx-auto max-w-4xl space-y-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="font-mono text-xl font-semibold">{{ $shipment->shipment_number }}</h2>
                <x-badge :tone="$shipment->current_status->tone()">{{ $shipment->current_status->label() }}</x-badge>
            </div>
            <div class="flex items-center gap-2">
                @can('update', $shipment)
                    <a href="{{ route('shipments.edit', $shipment) }}"
                       class="inline-flex h-8 items-center rounded-lg border border-line bg-raised px-3 text-sm text-ink transition-colors hover:border-line-strong">
                        Edit
                    </a>
                @endcan
                @can('delete', $shipment)
                    <form method="POST" action="{{ route('shipments.destroy', $shipment) }}"
                          onsubmit="return confirm('Archive this shipment? Tracking history is preserved.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex h-8 items-center rounded-lg border border-line bg-raised px-3 text-sm text-ink-2 transition-colors hover:border-danger/40 hover:text-danger">
                            Archive
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        <section class="rounded-xl border border-line bg-surface p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-ink-2">Route</h3>
                <span class="font-mono text-xs text-ink-3">{{ $shipment->plannedDurationHours() }}H planned</span>
            </div>

            <div class="mt-4 grid items-center gap-4 sm:grid-cols-[1fr_auto_1fr]">
                <div>
                    <p class="font-mono text-sm text-ink">{{ $shipment->origin->code }}</p>
                    <p class="mt-1 text-sm text-ink-2">{{ $shipment->origin->city }}, {{ $shipment->origin->country }}</p>
                    <p class="mt-2 text-xs text-ink-3">Depart {{ $shipment->expected_departure_at->format('M j, Y · H:i') }}</p>
                </div>
                <div class="flex items-center gap-2 text-ink-3">
                    <span class="h-px w-10 border-t border-dashed border-line-strong"></span>
                    <x-lucide-plane class="size-4" stroke-width="1.5"/>
                    <span class="h-px w-10 border-t border-dashed border-line-strong"></span>
                </div>
                <div class="sm:text-right">
                    <p class="font-mono text-sm text-ink">{{ $shipment->destination->code }}</p>
                    <p class="mt-1 text-sm text-ink-2">{{ $shipment->destination->city }}, {{ $shipment->destination->country }}</p>
                    <p class="mt-2 text-xs text-ink-3">Arrive {{ $shipment->expected_arrival_at->format('M j, Y · H:i') }}</p>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-line bg-surface p-6">
                <h3 class="text-sm font-medium text-ink-2">Cargo &amp; parties</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-3">Cargo type</dt>
                        <dd class="text-ink">{{ $shipment->cargo_type }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-3">Weight / volume</dt>
                        <dd class="font-mono text-ink">{{ number_format((float) $shipment->weight_kg, 2) }} kg · {{ number_format((float) $shipment->volume_m3, 2) }} m³</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-3">Carrier</dt>
                        <dd class="text-ink">{{ $shipment->carrier->name }} <span class="font-mono text-ink-3">{{ $shipment->carrier->code }}</span></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-3">Customer</dt>
                        <dd class="text-ink">{{ $shipment->customer->name }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-line bg-surface p-6">
                <h3 class="text-sm font-medium text-ink-2">Items ({{ $shipment->items->count() }})</h3>
                <div class="mt-4 space-y-2">
                    @forelse ($shipment->items as $item)
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-ink">{{ $item->description }} <span class="font-mono text-ink-3">×{{ $item->quantity }}</span></span>
                            <span class="shrink-0 font-mono text-xs text-ink-3">{{ number_format((float) $item->weight_kg, 2) }} kg</span>
                        </div>
                    @empty
                        <p class="text-sm text-ink-3">No item lines recorded</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="rounded-xl border border-line bg-surface p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-medium text-ink-2">Schedule</h3>
                @php $assessment = $shipment->delayAssessment(); @endphp
                @if ($assessment['state'] === 'pending')
                    <x-badge tone="neutral">No estimate yet</x-badge>
                @elseif ($assessment['state'] === 'on_time')
                    <x-badge tone="outline-green">On time</x-badge>
                @else
                    <x-badge :tone="$shipment->delayTone()">Delay {{ $assessment['state'] }}</x-badge>
                @endif
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-3 text-sm">
                <div>
                    <p class="text-xs text-ink-3">Expected arrival</p>
                    <p class="mt-1 font-mono text-ink">{{ $shipment->expected_arrival_at->format('M j, Y · H:i') }}</p>
                </div>
                <div>
                    <p class="text-xs text-ink-3">Estimated arrival</p>
                    <p class="mt-1 font-mono text-ink">{{ $shipment->estimated_arrival_at?->format('M j, Y · H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-ink-3">Assessment</p>
                    <p class="mt-1 text-ink-2">
                        @if ($assessment['state'] === 'pending')
                            Waiting for a carrier estimate
                        @elseif ($assessment['state'] === 'on_time')
                            Estimated ahead of or on the expected arrival
                        @else
                            {{ number_format($assessment['delay_hours'], 1) }}H behind expected —
                            LOW ≤ {{ $assessment['threshold_low'] }}H, MEDIUM ≤ {{ $assessment['threshold_high'] }}H, HIGH above
                        @endif
                    </p>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-line bg-surface p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-ink-2">Timeline</h3>
                @if ($lastEvent)
                    <span class="font-mono text-xs text-ink-3">Current position: {{ $lastEvent->location_name }}</span>
                @endif
            </div>

            @if ($lastEvent)
                <ol class="relative mt-5 space-y-5 border-l border-line pl-5">
                    @foreach ($shipment->trackingEvents as $event)
                        <li class="relative">
                            <span class="absolute -left-[25.5px] top-1.5 size-2.5 rounded-full {{ $event->isExceptionEvent() ? 'bg-danger' : ($loop->last ? 'bg-live ring-4 ring-live/20' : 'border border-line-strong bg-raised') }}"></span>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="font-mono text-xs text-ink-3">{{ $event->occurred_at->format('M j, Y · H:i') }}</span>
                                <span class="font-mono text-xs {{ $event->isExceptionEvent() ? 'text-danger' : 'text-ink-2' }}">{{ $event->event_type }}</span>
                            </div>
                            <p class="mt-1 text-sm text-ink">{{ $event->location_name }}</p>
                            @if ($event->description)
                                <p class="mt-0.5 text-xs leading-relaxed text-ink-2">{{ $event->description }}</p>
                            @endif
                            <p class="mt-1 text-[11px] text-ink-3">Recorded by {{ $event->creator->name }}</p>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="mt-4 text-sm text-ink-3">No tracking events recorded yet</p>
            @endif

            @can('addTracking', $shipment)
                <form method="POST" action="{{ route('shipments.tracking.store', $shipment) }}"
                      class="mt-6 grid gap-3 border-t border-line pt-5 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label for="event_type" class="mb-1.5 block text-xs font-medium text-ink-2">Status / event</label>
                        <select id="event_type" name="event_type"
                                class="w-full rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink focus:border-line-strong focus:outline-none focus:ring-2 focus:ring-accent/50">
                            @foreach (\App\Enums\ShipmentStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('event_type', $shipment->current_status->value) === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('event_type') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="occurred_at" class="mb-1.5 block text-xs font-medium text-ink-2">Occurred at</label>
                        <x-input id="occurred_at" name="occurred_at" type="datetime-local" value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}"/>
                        @error('occurred_at') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="location_name" class="mb-1.5 block text-xs font-medium text-ink-2">Location</label>
                        <x-input id="location_name" name="location_name" value="{{ old('location_name', $lastEvent?->location_name) }}" placeholder="Transit hub, Singapore"/>
                        @error('location_name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="latitude" class="mb-1.5 block text-xs font-medium text-ink-2">Latitude</label>
                        <x-input id="latitude" name="latitude" type="number" step="0.000001" value="{{ old('latitude') }}" placeholder="optional"/>
                        @error('latitude') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="longitude" class="mb-1.5 block text-xs font-medium text-ink-2">Longitude</label>
                        <x-input id="longitude" name="longitude" type="number" step="0.000001" value="{{ old('longitude') }}" placeholder="optional"/>
                        @error('longitude') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="description" class="mb-1.5 block text-xs font-medium text-ink-2">Description</label>
                        <x-input id="description" name="description" value="{{ old('description') }}" placeholder="What happened at this point"/>
                        @error('description') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <x-button>Record event</x-button>
                    </div>
                </form>
            @endcan
        </section>

        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="text-sm font-medium text-ink-2">Exceptions ({{ $shipment->exceptions->count() }})</h3>

            <div class="mt-4 space-y-3">
                @forelse ($shipment->exceptions as $exception)
                    <div class="rounded-lg border border-line bg-raised/40 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-ink">{{ $exception->type->label() }}</span>
                                <x-badge :tone="$exception->severity->tone()">{{ $exception->severity->value }}</x-badge>
                                <x-badge :tone="$exception->status->tone()">{{ $exception->status->value }}</x-badge>
                            </div>
                            <span class="font-mono text-[11px] text-ink-3">
                                Detected {{ $exception->detected_at->format('M j · H:i') }}
                                @if ($exception->resolved_at)
                                    · Resolved {{ $exception->resolved_at->format('M j · H:i') }}
                                @endif
                            </span>
                        </div>
                        @if ($exception->description)
                            <p class="mt-1.5 text-xs leading-relaxed text-ink-2">{{ $exception->description }}</p>
                        @endif
                        @if ($exception->resolver)
                            <p class="mt-1 text-[11px] text-ink-3">Resolved by {{ $exception->resolver->name }}</p>
                        @endif

                        @can('manageExceptions', $shipment)
                            @if ($exception->isOpen())
                                <div class="mt-3 flex gap-2">
                                    @if ($exception->status->value === 'OPEN')
                                        <form method="POST" action="{{ route('exceptions.update', $exception) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="INVESTIGATING">
                                            <button type="submit" class="inline-flex h-7 items-center rounded-lg border border-line bg-raised px-2.5 text-xs text-ink-2 transition-colors hover:border-line-strong hover:text-ink">
                                                Start investigating
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('exceptions.update', $exception) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="RESOLVED">
                                        <button type="submit" class="inline-flex h-7 items-center rounded-lg border border-ok/30 bg-raised px-2.5 text-xs text-ok transition-colors hover:bg-ok/10">
                                            Resolve
                                        </button>
                                    </form>
                                </div>
                            @endif
                        @endcan
                    </div>
                @empty
                    <p class="text-sm text-ink-3">No exceptions on this shipment</p>
                @endforelse
            </div>

            @can('manageExceptions', $shipment)
                <form method="POST" action="{{ route('shipments.exceptions.store', $shipment) }}"
                      class="mt-6 grid gap-3 border-t border-line pt-5 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label for="exception_type" class="mb-1.5 block text-xs font-medium text-ink-2">Type</label>
                        <select id="exception_type" name="type"
                                class="w-full rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink focus:border-line-strong focus:outline-none focus:ring-2 focus:ring-accent/50">
                            @foreach (\App\Enums\ExceptionType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="exception_severity" class="mb-1.5 block text-xs font-medium text-ink-2">Severity</label>
                        <select id="exception_severity" name="severity"
                                class="w-full rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink focus:border-line-strong focus:outline-none focus:ring-2 focus:ring-accent/50">
                            @foreach (\App\Enums\ExceptionSeverity::cases() as $severity)
                                <option value="{{ $severity->value }}">{{ $severity->value }}</option>
                            @endforeach
                        </select>
                        @error('severity') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="detected_at" class="mb-1.5 block text-xs font-medium text-ink-2">Detected at</label>
                        <x-input id="detected_at" name="detected_at" type="datetime-local" value="{{ old('detected_at', now()->format('Y-m-d\TH:i')) }}"/>
                        @error('detected_at') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="exception_location" class="mb-1.5 block text-xs font-medium text-ink-2">Location (optional)</label>
                        <x-input id="exception_location" name="location_name" value="{{ old('location_name') }}" placeholder="Defaults to the last known position"/>
                        @error('location_name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="exception_description" class="mb-1.5 block text-xs font-medium text-ink-2">Description</label>
                        <x-input id="exception_description" name="description" value="{{ old('description') }}" placeholder="What went wrong"/>
                        @error('description') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <x-button>Record exception</x-button>
                    </div>
                </form>
            @endcan
        </section>
    </div>
</x-layouts.app>
