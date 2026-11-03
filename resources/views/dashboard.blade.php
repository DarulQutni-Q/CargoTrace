<x-layouts.app title="Dashboard">
    <div class="mx-auto max-w-3xl space-y-6 p-6">
        <section class="rounded-xl border border-line bg-surface p-6">
            <h2 class="text-xl font-semibold">Welcome back, {{ auth()->user()->name }}</h2>
            <p class="mt-1.5 text-sm leading-relaxed text-ink-2">
                You are signed in as {{ auth()->user()->role->label() }}.
                The operations room takes shape milestone by milestone — shipments, tracking,
                and the live map land next.
            </p>
        </section>

        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-line bg-surface p-5">
                <p class="text-xs font-medium text-ink-2">Next up</p>
                <p class="mt-2 text-sm leading-relaxed text-ink">Shipments, carriers, and locations with role-based access</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-5">
                <p class="text-xs font-medium text-ink-2">Then</p>
                <p class="mt-2 text-sm leading-relaxed text-ink">Tracking events, the timeline, and validated status transitions</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-5">
                <p class="text-xs font-medium text-ink-2">After that</p>
                <p class="mt-2 text-sm leading-relaxed text-ink">Delay rules, exceptions, the live map, and the dashboard metrics</p>
            </div>
        </section>
    </div>
</x-layouts.app>
