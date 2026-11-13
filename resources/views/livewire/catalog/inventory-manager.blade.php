<div class="catalog-admin pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('inventory.title') }}</h1>
            <p class="app-page-lead">{{ __('inventory.subtitle') }}</p>
        </div>
        @if($canManage)
            <x-app.add-button type="button" wire:click="startCreate">{{ __('inventory.add') }}</x-app.add-button>
        @endif
    </header>

    @if(count($lowStock) > 0)
        <section class="app-card mb-4 border-amber-200 bg-amber-50/80 p-3">
            <h2 class="text-sm font-semibold text-amber-900">{{ __('inventory.low_stock_heading') }}</h2>
            <ul class="mt-2 space-y-1 text-sm text-amber-950">
                @foreach($lowStock as $ing)
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <span>{{ $ing->displayName() }}</span>
                        <span class="tabular-nums font-medium">{{ number_format((float) $ing->quantity_on_hand, 2) }} {{ $ing->unit }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <ul class="space-y-3">
        @forelse($ingredients as $ing)
            <li class="app-card flex flex-wrap items-center justify-between gap-3 p-3">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $ing->displayName() }}</p>
                    <p class="text-xs text-[var(--app-muted)]">{{ $ing->unit }}</p>
                    <p class="mt-1 text-sm tabular-nums">
                        {{ number_format((float) $ing->quantity_on_hand, 2) }} {{ $ing->unit }}
                    </p>
                    <p class="text-xs tabular-nums text-[var(--app-muted)]">
                        {{ __('inventory.low_stock_threshold') }}:
                        {{ number_format((float) ($ing->low_stock_threshold ?? $ing->reorder_level ?? 0), 2) }}
                        {{ $ing->unit }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($ing->isLowStock())
                        <span class="pos-status-pill pos-status-pill--cancelled">{{ __('inventory.low_stock_badge') }}</span>
                    @endif
                    @unless($ing->is_active)
                        <span class="pos-status-pill">{{ __('inventory.inactive') }}</span>
                    @endunless
                    @if($canManage)
                        <button type="button" class="tap-action-muted text-xs" wire:click="startAdjust({{ $ing->id }})">{{ __('inventory.adjust') }}</button>
                        <button type="button" class="tap-action-muted text-xs" wire:click="startEdit({{ $ing->id }})">{{ __('catalog.edit') }}</button>
                        <x-ui.livewire-action wire:click="delete({{ $ing->id }})" :target="'delete('.$ing->id.')'" loading="saving" variant="muted" class="tap-action-muted text-xs text-rose-600">{{ __('catalog.delete') }}</x-ui.livewire-action>
                    @endif
                </div>
            </li>
        @empty
            <li class="app-empty"><p class="text-sm text-[var(--app-muted)]">{{ __('inventory.empty') }}</p></li>
        @endforelse
    </ul>

    @if($movements->isNotEmpty())
        <section class="app-card mt-6 p-3">
            <h2 class="text-sm font-semibold">{{ __('inventory.recent_movements') }}</h2>
            <ul class="mt-2 space-y-2 text-xs text-[var(--app-muted)]">
                @foreach($movements as $move)
                    <li class="flex justify-between gap-2 tabular-nums">
                        <span>#{{ $move->ingredient_id }} · {{ $move->reason ?? $move->type }}</span>
                        <span>{{ number_format((float) $move->quantity_delta, 2) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($showForm && $canManage)
        <div class="app-sheet-backdrop fixed inset-0 z-40 bg-black/40" wire:click="$set('showForm', false)"></div>
        <div class="app-sheet fixed inset-x-0 bottom-0 z-50 max-h-[90vh] overflow-y-auto p-4 sm:inset-auto sm:start-1/2 sm:top-1/2 sm:w-full sm:max-w-md sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
            <h2 class="text-lg font-semibold">{{ $editingId ? __('inventory.edit') : __('inventory.add') }}</h2>
            <form class="mt-4 space-y-3" wire:submit="save">
                <x-form.input :label="__('catalog.name_en')" name="nameEn" wire:model="nameEn" required />
                <x-form.input :label="__('catalog.name_ar')" name="nameAr" wire:model="nameAr" dir="rtl" />
                <x-form.input :label="__('catalog.name_bn')" name="nameBn" wire:model="nameBn" />
                <x-form.input :label="__('inventory.unit')" name="unit" wire:model="unit" required />
                <x-form.input type="number" :label="__('inventory.quantity_on_hand')" name="quantityOnHand" wire:model="quantityOnHand" required />
                <x-form.input type="number" :label="__('inventory.low_stock_threshold')" name="lowStockThreshold" wire:model="lowStockThreshold" required />
                <x-form.toggle wire:model="isActive" name="isActive" :label="__('catalog.active')" />
                <x-ui.livewire-action type="submit" target="save" loading="saving" class="tap-action-primary w-full" variant="primary">{{ __('catalog.save') }}</x-ui.livewire-action>
            </form>
        </div>
    @endif

    @if($showAdjust && $canManage)
        <div class="app-sheet-backdrop fixed inset-0 z-40 bg-black/40" wire:click="$set('showAdjust', false)"></div>
        <div class="app-sheet fixed inset-x-0 bottom-0 z-50 max-h-[70vh] overflow-y-auto p-4 sm:inset-auto sm:start-1/2 sm:top-1/2 sm:w-full sm:max-w-sm sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
            <h2 class="text-lg font-semibold">{{ __('inventory.adjust_title') }}</h2>
            <form class="mt-4 space-y-3" wire:submit="saveAdjust">
                <x-form.select :label="__('inventory.adjust_mode')" name="adjustMode" wire:model="adjustMode" :liveValidate="true">
                    <option value="add">{{ __('inventory.adjust_add') }}</option>
                    <option value="remove">{{ __('inventory.adjust_remove') }}</option>
                    <option value="set">{{ __('inventory.adjust_set') }}</option>
                </x-form.select>
                <x-form.input type="number" :label="__('inventory.adjust_amount')" name="adjustAmount" wire:model="adjustAmount" required />
                <x-ui.livewire-action type="submit" target="saveAdjust" loading="saving" class="tap-action-primary w-full" variant="primary">{{ __('inventory.adjust_submit') }}</x-ui.livewire-action>
            </form>
        </div>
    @endif
</div>
