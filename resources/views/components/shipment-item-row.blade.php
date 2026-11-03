@props(['item' => []])

<div class="grid grid-cols-[1fr_5rem_6rem_6rem_2rem] items-start gap-2" data-item-row>
    <div>
        <label class="mb-1.5 block text-xs font-medium text-ink-2">Description</label>
        <x-input name="items[][description]" value="{{ $item['description'] ?? '' }}" placeholder="Pallet of consumer electronics"/>
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-medium text-ink-2">Qty</label>
        <x-input name="items[][quantity]" type="number" min="1" value="{{ $item['quantity'] ?? '' }}"/>
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-medium text-ink-2">Kg</label>
        <x-input name="items[][weight_kg]" type="number" step="0.01" min="0" value="{{ $item['weight_kg'] ?? '' }}"/>
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-medium text-ink-2">m³</label>
        <x-input name="items[][volume_m3]" type="number" step="0.01" min="0" value="{{ $item['volume_m3'] ?? '' }}"/>
    </div>
    <div class="pt-7">
        <button type="button" data-remove-row title="Remove item"
                class="flex size-9 items-center justify-center rounded-lg text-ink-3 transition-colors hover:bg-raised hover:text-danger">
            <x-lucide-trash-2 class="size-4" stroke-width="1.5"/>
        </button>
    </div>
</div>
