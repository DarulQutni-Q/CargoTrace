{{-- 2D Map / 3D Globe projection switcher.
     Wired by CargoTraceGlobe.initProjection() in public/js/globe3d.js.
     Preference persists in sessionStorage under `cargotrace_map_projection_v1`.
     Expects $prefix for unique element ids, e.g. 'shipments' or 'detail'. --}}
<div class="absolute left-2.5 top-2.5 z-[450] inline-flex items-center rounded-md border border-[var(--line-strong)] bg-[var(--bg-overlay)] p-0.5 text-xs shadow-md sm:left-4 sm:top-4">
    <button type="button" id="{{ $prefix }}-proj-2d" class="inline-flex h-7 items-center gap-1.5 rounded px-2.5 text-[11px] font-semibold transition-colors cursor-pointer text-[var(--ink-2)] hover:text-[var(--ink-1)] sm:text-xs">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 20.5 4.61v12.783a1 1 0 0 1-.59.91l-3.659 1.83a2 2 0 0 1-1.788 0l-4.92-2.46a1 1 0 0 0-1.086 0l-4.92 2.46a2 2 0 0 1-1.788 0L1.09 18.31A1 1 0 0 1 .5 17.4V4.61a1 1 0 0 1 .59-.91l3.659-1.83a2 2 0 0 1 1.788 0l4.92 2.46a1 1 0 0 1 1.086 0z"/><path d="M15 5.764v15"/><path d="M9 3.236v15"/></svg>
        <span>2D Map</span>
    </button>
    <button type="button" id="{{ $prefix }}-proj-3d" class="inline-flex h-7 items-center gap-1.5 rounded px-2.5 text-[11px] font-semibold transition-colors cursor-pointer text-[var(--ink-2)] hover:text-[var(--ink-1)] sm:text-xs">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
        <span>3D Globe</span>
    </button>
</div>
