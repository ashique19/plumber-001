<div class="supplier-hub admin-hub-page pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('suppliers.title') }}</h1>
            <p class="app-page-lead">{{ __('suppliers.subtitle') }}</p>
        </div>
        <x-app.add-button type="button" variant="primary" wire:click="startCreate" class="mb-0 hidden w-full sm:ms-auto sm:inline-flex sm:w-auto">
            {{ __('suppliers.add') }}
        </x-app.add-button>
    </header>

    @unless($schemaReady)
        <p class="app-card p-4 text-sm text-[var(--app-muted)]">{{ __('purchases.schema_missing') }}</p>
    @else
        @php
            $listEmpty = ($suppliers?->total() ?? 0) === 0 && trim($search) === '';
        @endphp

        <section class="app-card space-y-4 p-4">
            <x-field.search
                :label="__('suppliers.search')"
                name="search"
                wire:model.live.debounce.300ms="search"
                :placeholder="__('suppliers.search_placeholder')"
                :clearable="trim($search) !== ''"
                clear-action="$set('search', '')"
            />

        @if ($listEmpty)
            <p class="text-sm text-[var(--app-muted)]">{{ __('suppliers.empty_list') }}</p>
        @else
                <ul class="admin-hub-list" role="list">
                    @forelse ($suppliers ?? [] as $row)
                        <li wire:key="supplier-{{ $row->id }}">
                            <button
                                type="button"
                                wire:click="selectSupplier({{ $row->id }})"
                                @class([
                                    'h-full w-full rounded-xl border p-3 text-start transition',
                                    'border-[var(--plumber-safety)] bg-[var(--plumber-safety)]/10' => $selected?->id === $row->id,
                                    'border-[var(--app-border)] hover:border-[var(--plumber-safety)]/50' => $selected?->id !== $row->id,
                                    'opacity-60' => ! $row->is_active,
                                ])
                            >
                                <p class="font-medium">{{ $row->name }}</p>
                                @if ($row->name_ar)
                                    <p class="text-xs text-[var(--app-muted)]" dir="rtl">{{ $row->name_ar }}</p>
                                @endif
                                <p class="mt-1 text-xs text-[var(--app-muted)]">
                                    {{ collect([$row->phone, $row->vat_number])->filter()->implode(' · ') }}
                                </p>
                                @unless ($row->is_active)
                                    <span class="pos-status-pill mt-2">{{ __('suppliers.inactive_badge') }}</span>
                                @endunless
                            </button>
                        </li>
                    @empty
                        <li class="col-span-full text-sm text-[var(--app-muted)]">{{ __('suppliers.search_empty') }}</li>
                    @endforelse
                </ul>

                @if ($suppliers)
                    <x-admin.hub-pager :paginator="$suppliers" />
                @endif
        @endif
        </section>

        <x-admin.hub-fab wire:click="startCreate" :label="__('suppliers.add')" />

        <x-admin.hub-sheet
            :show="$showFormModal"
            :title="$selected ? __('suppliers.edit_title') : __('suppliers.add')"
            close-wire="closeFormModal"
            size="xl"
        >
            @include('livewire.purchases.partials.supplier-form', [
                'purchaseCount' => $purchaseCount,
                'editing' => $selected !== null,
            ])
        </x-admin.hub-sheet>
    @endunless
</div>