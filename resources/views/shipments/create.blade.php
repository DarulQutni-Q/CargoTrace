<x-layouts.app title="New shipment">
    <div class="mx-auto max-w-3xl space-y-6 p-6">
        <div>
            <h2 class="text-xl font-semibold">New shipment</h2>
            <p class="mt-1 text-sm text-ink-2">A tracking number is generated when the shipment is created.</p>
        </div>

        @include('shipments._form')
    </div>
</x-layouts.app>
