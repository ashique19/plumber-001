<div class="team-hub pb-24 lg:pb-8">
    <header class="mb-4">
        <h1 class="app-page-title">{{ __('operations.title') }}</h1>
        <p class="app-page-lead">{{ __('operations.subtitle') }}</p>
    </header>

    <div class="flex flex-wrap gap-2">
        <div class="app-section-tabs">
        @foreach (['staff' => __('operations.tab_staff'), 'branches' => __('operations.tab_branches'), 'shifts' => __('operations.tab_shifts')] as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')" @class([
                'app-segment-tab',
                'app-segment-tab--active' => $tab === $key,
            ])>{{ $label }}</button>
        @endforeach
        </div>
    </div>

    @if ($tab === 'staff')
        <section class="mt-4">
            <x-app.add-button type="button" wire:click="startStaff()">{{ __('operations.add_staff') }}</x-app.add-button>
            <ul class="space-y-2">
                @foreach ($staff as $member)
                    <li class="app-card flex flex-wrap items-center justify-between gap-2 p-3">
                        <div>
                            <p class="font-medium">{{ $member->name }}</p>
                            <p class="text-xs text-[var(--app-muted)]">{{ $member->email }} · {{ $member->branch?->name ?? '—' }}</p>
                            <p class="mt-1 flex flex-wrap gap-1">
                                @foreach ($member->getRoleNames() as $role)
                                    <span class="pos-status-pill pos-status-pill--paid">{{ $role }}</span>
                                @endforeach
                                @unless ($member->is_active)
                                    <span class="pos-status-pill pos-status-pill--cancelled">{{ __('operations.inactive') }}</span>
                                @endunless
                            </p>
                        </div>
                        <button type="button" class="tap-action-muted text-xs" wire:click="startStaff({{ $member->id }})">{{ __('operations.edit') }}</button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($tab === 'branches')
        <section class="mt-4">
            <x-app.add-button type="button" wire:click="startBranch()">{{ __('operations.add_branch') }}</x-app.add-button>
            <ul class="space-y-2">
                @foreach ($branches as $branch)
                    @php($contact = $branch->contact())
                    <li class="app-card flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $branch->name }}</p>
                            @if ($branch->name_ar)
                                <p class="text-sm text-[var(--app-muted)]" dir="rtl">{{ $branch->name_ar }}</p>
                            @endif
                            <p class="mt-1 text-xs text-[var(--app-muted)]">
                                {{ $branch->code }}
                                @if ($branch->address)
                                    · {{ $branch->address }}
                                @endif
                                @if ($contact['city'])
                                    · {{ $contact['city'] }}
                                @endif
                            </p>
                            @if ($contact['phone'] || $contact['email'])
                                <p class="mt-1 text-xs text-[var(--app-muted)]">
                                    @if ($contact['phone'])
                                        <span dir="ltr">{{ $contact['phone'] }}</span>
                                    @endif
                                    @if ($contact['phone'] && $contact['email'])
                                        ·
                                    @endif
                                    @if ($contact['email'])
                                        {{ $contact['email'] }}
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class(['pos-status-pill', 'pos-status-pill--paid' => $branch->is_active, 'pos-status-pill--cancelled' => ! $branch->is_active])>
                                {{ $branch->is_active ? __('operations.active') : __('operations.inactive') }}
                            </span>
                            <button type="button" class="tap-action-muted text-xs" wire:click="startBranch({{ $branch->id }})">{{ __('operations.edit') }}</button>
                            @if ($branch->is_active)
                                <button type="button" class="tap-action-muted text-xs" wire:click="deactivateBranch({{ $branch->id }})" wire:confirm="{{ __('operations.confirm_deactivate_branch') }}">{{ __('operations.deactivate_branch') }}</button>
                            @else
                                <button type="button" class="tap-action-primary text-xs" wire:click="reactivateBranch({{ $branch->id }})">{{ __('operations.reactivate_branch') }}</button>
                            @endif
                            @if ($branchDeletable[$branch->id] ?? false)
                                <button type="button" class="tap-action-muted text-xs text-red-700" wire:click="deleteBranch({{ $branch->id }})" wire:confirm="{{ __('operations.confirm_delete_branch') }}">{{ __('operations.delete_branch') }}</button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($tab === 'shifts')
        <section class="mt-4 space-y-4">
            <label class="block text-xs font-medium text-[var(--app-muted)]">
                {{ __('operations.shift_branch') }}
                <select wire:model.live="shiftBranchId" class="app-toolbar-select mt-1 w-full">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>

            @if ($openShift)
                <div class="app-card border border-emerald-500/30 p-4">
                    <p class="text-sm font-semibold text-emerald-700">{{ __('operations.shift_open') }}</p>
                    <p class="mt-1 text-xs text-[var(--app-muted)]">
                        {{ __('operations.opened_by') }} {{ $openShift->cashier?->name }}
                        · {{ $openShift->started_at?->format('Y-m-d H:i') }}
                    </p>
                    <p class="mt-2 text-lg font-bold tabular-nums">{{ number_format((float) $openShift->opening_cash_float, 2) }} SAR</p>
                    <p class="text-xs text-[var(--app-muted)]">{{ __('operations.opening_float') }}</p>
                </div>
            @else
                <p class="text-sm text-[var(--app-muted)]">{{ __('operations.shift_closed_hint') }}</p>
            @endif

            <div class="app-card grid gap-3 p-4 sm:grid-cols-2">
                <form wire:submit="openShift" class="space-y-2">
                    <h2 class="text-sm font-semibold">{{ __('operations.open_shift') }}</h2>
                    <x-form.input type="number" :label="__('operations.opening_float')" name="openingFloat" wire:model="openingFloat" placeholder="0.00" @disabled($openShift) />
                    <x-ui.livewire-action type="submit" target="openShift" loading="saving" class="w-full" variant="primary" :disabled="$openShift">{{ __('operations.open_shift') }}</x-ui.livewire-action>
                </form>
                <form wire:submit="closeShift" class="space-y-2">
                    <h2 class="text-sm font-semibold">{{ __('operations.close_shift') }}</h2>
                    <x-form.input type="number" :label="__('pos.cash_drawer_counted')" name="closingCounted" wire:model="closingCounted" placeholder="0.00" @disabled(! $openShift) />
                    <x-form.input :label="__('operations.close_notes')" name="closeNotes" wire:model="closeNotes" @disabled(! $openShift) />
                    <x-ui.livewire-action type="submit" target="closeShift" loading="saving" class="w-full" variant="primary" :disabled="! $openShift">{{ __('operations.close_shift') }}</x-ui.livewire-action>
                </form>
            </div>

            <section class="app-card p-4">
                <h2 class="text-sm font-semibold">{{ __('operations.recent_shifts') }}</h2>
                <ul class="app-mini-table mt-2">
                    @forelse ($recentShifts as $shift)
                        <li>
                            <span>{{ $shift->started_at?->format('M j, H:i') }} — {{ $shift->cashier?->name }}</span>
                            <span class="tabular-nums">{{ number_format((float) $shift->opening_cash_float, 2) }} → {{ $shift->ended_at ? number_format((float) $shift->closing_cash_counted, 2) : '…' }}</span>
                        </li>
                    @empty
                        <li class="text-[var(--app-muted)]">{{ __('operations.no_shifts') }}</li>
                    @endforelse
                </ul>
            </section>
        </section>
    @endif

    @if ($showStaffForm)
        <div class="app-sheet-backdrop fixed inset-0 z-40 bg-black/40" wire:click="$set('showStaffForm', false)"></div>
        <div class="app-sheet fixed inset-x-0 bottom-0 z-50 max-h-[90vh] overflow-y-auto p-4 sm:inset-auto sm:start-1/2 sm:top-1/2 sm:w-full sm:max-w-lg sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
            <h2 class="text-lg font-semibold">{{ $editingStaffId ? __('operations.edit_staff') : __('operations.add_staff') }}</h2>
            <form class="mt-4 space-y-3" wire:submit="saveStaff">
                <x-form.input :label="__('operations.name')" name="staffName" wire:model="staffName" required />
                <x-form.input type="email" :label="__('operations.email')" name="staffEmail" wire:model="staffEmail" required />
                <x-form.input type="password" :label="__('operations.password')" name="staffPassword" wire:model="staffPassword" @if(!$editingStaffId) required @endif />
                <x-form.select :label="__('operations.branch_assign')" name="staffBranchId" wire:model="staffBranchId">
                    <option value="">{{ __('operations.no_branch') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </x-form.select>
                <fieldset>
                    <legend class="text-xs font-medium">{{ __('operations.roles') }}</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($roleOptions as $role)
                            <label class="flex items-center gap-1 text-xs">
                                <input type="checkbox" value="{{ $role }}" wire:model="staffRoles"> {{ $role }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-form.toggle wire:model="staffActive" name="staffActive" :label="__('operations.active')" />
                <x-ui.livewire-action type="submit" target="saveStaff" loading="saving" class="w-full" variant="primary">{{ __('operations.save') }}</x-ui.livewire-action>
            </form>
        </div>
    @endif

    @if ($showBranchForm)
        <div class="app-sheet-backdrop fixed inset-0 z-40 bg-black/40" wire:click="$set('showBranchForm', false)"></div>
        <div class="app-sheet fixed inset-x-0 bottom-0 z-50 max-h-[85vh] overflow-y-auto p-4 sm:max-w-md sm:mx-auto sm:rounded-2xl">
            <h2 class="text-lg font-semibold">{{ $editingBranchId ? __('operations.edit_branch') : __('operations.add_branch') }}</h2>
            <form class="mt-4 space-y-3" wire:submit="saveBranch">
                <x-form.input :label="__('operations.branch_code')" name="branchCode" wire:model="branchCode" required />
                <x-form.input :label="__('operations.branch_name')" name="branchName" wire:model="branchName" required />
                <x-form.input :label="__('operations.branch_name_ar')" name="branchNameAr" wire:model="branchNameAr" dir="rtl" />
                <x-form.input :label="__('operations.address')" name="branchAddress" wire:model="branchAddress" />
                <x-form.input :label="__('operations.branch_city')" name="branchCity" wire:model="branchCity" />
                <x-form.input type="tel" :label="__('operations.branch_phone')" name="branchPhone" wire:model="branchPhone" />
                <x-form.input type="email" :label="__('operations.branch_contact_email')" name="branchEmail" wire:model="branchEmail" />
                <x-form.location-picker
                    lat-wire="branchLat"
                    lng-wire="branchLng"
                    maps-url-wire="branchMapsUrl"
                    :default-lat="(float) (is_numeric($branchLat) ? $branchLat : 24.7136)"
                    :default-lng="(float) (is_numeric($branchLng) ? $branchLng : 46.6753)"
                >
                    <x-slot name="advanced">
                        <div class="mt-2">
                            <button type="button" class="text-xs font-semibold text-[var(--cafe-caramel)] underline" wire:click="$toggle('branchLocationAdvanced')">
                                {{ __('business.map.advanced') }}
                            </button>
                        </div>
                        @if ($branchLocationAdvanced)
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <x-form.input :label="__('business.contact.lat')" name="branchLat" wire:model.blur="branchLat" :liveValidate="false" />
                                <x-form.input :label="__('business.contact.lng')" name="branchLng" wire:model.blur="branchLng" :liveValidate="false" />
                                <x-form.input :label="__('business.contact.maps_url')" name="branchMapsUrl" wire:model.blur="branchMapsUrl" :liveValidate="false" wrapperClass="sm:col-span-2" />
                            </div>
                        @endif
                    </x-slot>
                </x-form.location-picker>
                <x-form.toggle wire:model="branchActive" name="branchActive" :label="__('operations.active')" />
                <x-ui.livewire-action type="submit" target="saveBranch" loading="saving" class="w-full" variant="primary">{{ __('operations.save') }}</x-ui.livewire-action>
            </form>
        </div>
    @endif
</div>
