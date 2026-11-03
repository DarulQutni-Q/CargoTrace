<x-layouts.app title="Edit {{ $shipment->shipment_number }}">
    <div class="mx-auto max-w-3xl space-y-6 p-6">
        <div>
            <h2 class="text-xl font-semibold">Edit <span class="font-mono">{{ $shipment->shipment_number }}</span></h2>
            <p class="mt-1 text-sm text-ink-2">Status changes happen through tracking events, not this form.</p>
        </div>

        @include('shipments._form')
    </div>
</x-layouts.app>
