<div class="supplier-hub pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('suppliers.title') }}</h1>
            <p class="app-page-lead">{{ __('suppliers.subtitle') }}</p>
        </div>
        <x-app.add-button type="button" wire:click="startCreate" class="mb-0 w-full sm:ms-auto sm:w-auto">{{ __('suppliers.add') }}</x-app.add-button>
    </header>

    @unless($schemaReady)
        <p class="app-card p-4 text-sm text-[var(--app-muted)]">{{ __('purchases.schema_missing') }}</p>
    @else
        <section class="app-card grid gap-4 p-4 lg:grid-cols-5">
            <div class="space-y-3 lg:col-span-2">
                <x-field.search
                    :label="__('suppliers.search')"
                    name="search"
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('suppliers.search_placeholder')"
                    :clearable="trim($search) !== ''"
                    clear-action="$set('search', '')"
                />
                <ul class="space-y-2 lg:max-h-[32rem] lg:overflow-y-auto">
                    @forelse ($suppliers ?? [] as $row)
                        <li wire:key="supplier-{{ $row->id }}">
                            <button
                                type="button"
                                wire:click="selectSupplier({{ $row->id }})"
                                @class([
                                    'w-full rounded-xl border p-3 text-start transition',
                                    'border-[var(--plumber-safety)] bg-[var(--plumber-safety)]/10' => $selected?->id === $row->id,
                                    'border-[var(--app-border)] hover:border-[var(--plumber-safety)]/50' => $selected?->id !== $row->id,
                                    'opacity-60' => ! $row->is_active,
                                ])
                            >
                                <p class="font-medium">{{ $row->name }}</p>
                                @if($row->name_ar)
                                    <p class="text-xs text-[var(--app-muted)]" dir="rtl">{{ $row->name_ar }}</p>
                                @endif
                                <p class="mt-1 text-xs text-[var(--app-muted)]">
                                    {{ collect([$row->phone, $row->vat_number])->filter()->implode(' · ') }}
                                </p>
                                @unless($row->is_active)
                                    <span class="pos-status-pill mt-2">{{ __('suppliers.inactive_badge') }}</span>
                                @endunless
                            </button>
                        </li>
                    @empty
                        <li class="text-sm text-[var(--app-muted)]">{{ __('suppliers.empty_list') }}</li>
                    @endforelse
                </ul>
                @if($suppliers)
                    <div>{{ $suppliers->links() }}</div>
                @endif
            </div>

            <div class="lg:col-span-3">
                @if ($showCreateForm || $selected)
                    <x-field.section :title="$selected ? __('suppliers.edit_title') : __('suppliers.add')" icon="users">
                        <form class="admin-form-grid space-y-0" wire:submit="saveSupplier">
                            <div class="admin-form-grid admin-form-grid--2 gap-3">
                                <x-field.input :label="__('suppliers.name')" name="formName" wire:model.blur="formName" required />
                                <x-field.input :label="__('suppliers.name_ar')" name="formNameAr" wire:model.blur="formNameAr" dir="rtl" />
                            </div>
                            <div class="admin-form-grid admin-form-grid--2 gap-3">
                                <x-field.input :label="__('suppliers.contact_person')" name="formContactPerson" wire:model.blur="formContactPerson" />
                                <x-field.input :label="__('suppliers.phone')" name="formPhone" wire:model.blur="formPhone" inputmode="tel" icon="phone" />
                            </div>
                            <div class="admin-form-grid admin-form-grid--2 gap-3">
                                <x-field.input type="email" :label="__('suppliers.email')" name="formEmail" wire:model.blur="formEmail" icon="mail" />
                                <x-field.input :label="__('suppliers.vat')" name="formVat" wire:model.blur="formVat" inputmode="numeric" />
                            </div>
                            <div class="admin-form-grid admin-form-grid--2 gap-3">
                                <x-field.input :label="__('suppliers.cr_number')" name="formCr" wire:model.blur="formCr" />
                                <x-field.input :label="__('suppliers.city')" name="formCity" wire:model.blur="formCity" />
                            </div>
                            <x-field.textarea :label="__('suppliers.address')" name="formAddress" wire:model.blur="formAddress" rows="2" />
                            <x-field.textarea :label="__('suppliers.notes')" name="formNotes" wire:model.blur="formNotes" rows="2" />
                            <x-field.toggle :label="__('suppliers.active')" name="formActive" wire:model="formActive" />

                            @if($selected && $purchaseCount > 0)
                                <p class="text-xs text-[var(--app-muted)]">{{ __('suppliers.purchase_count_hint', ['count' => $purchaseCount]) }}</p>
                            @endif

                            <x-field.actions class="mt-2">
                                <x-ui.livewire-action type="submit" target="saveSupplier" loading="saveSupplier" class="tap-action-primary min-h-11 flex-1">
                                    {{ __('suppliers.save') }}
                                </x-ui.livewire-action>
                                @if($selected)
                                    <x-ui.livewire-action type="button" wire:click="deleteSupplier" target="deleteSupplier" loading="deleteSupplier" class="tap-action-muted min-h-11 flex-1">
                                        {{ __('suppliers.delete') }}
                                    </x-ui.livewire-action>
                                @else
                                    <button type="button" class="tap-action-muted min-h-11 flex-1" wire:click="cancelForm">{{ __('catalog.cancel') }}</button>
                                @endif
                            </x-field.actions>
                        </form>
                    </x-field.section>
                @else
                    <div class="app-card flex min-h-48 items-center justify-center p-6 text-center text-sm text-[var(--app-muted)]">
                        {{ __('suppliers.pick_one') }}
                    </div>
                @endif
            </div>
        </section>
    @endunless
</div>