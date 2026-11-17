@push('head')
    @vite(['resources/css/hub-document-table.css', 'resources/css/admin-hub-pagination.css'])
@endpush

@php
    $pageRtl = config('locales.supported.'.app()->getLocale().'.dir', 'ltr') === 'rtl';
@endphp

<div class="staff-users-hub admin-hub-page pb-24 lg:pb-8" data-testid="staff-users-hub" @if ($pageRtl) dir="rtl" @endif>
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('staff_users.title') }}</h1>
            <p class="app-page-lead">{{ __('staff_users.subtitle') }}</p>
        </div>
        <x-app.add-button type="button" variant="primary" wire:click="startCreate" class="mb-0 hidden w-full sm:ms-auto sm:inline-flex sm:w-auto">
            {{ __('staff_users.add') }}
        </x-app.add-button>
    </header>

    @php
        $listEmpty = $users->total() === 0 && trim($search) === '';
    @endphp

    @if ($listEmpty)
        <x-admin.hub-empty icon="users" :title="__('staff_users.empty')" :action-label="__('staff_users.add')" action-wire="startCreate" />
    @else
        <section class="app-card space-y-4 p-4">
            <x-field.search
                :label="__('staff_users.search')"
                name="search"
                wire:model.live.debounce.300ms="search"
                :placeholder="__('staff_users.search_placeholder')"
                :clearable="trim($search) !== ''"
                clear-action="$set('search', '')"
            />

            <ul class="mb-4 space-y-3 sm:hidden" data-testid="staff-user-cards">
                @foreach ($users as $row)
                    @php
                        $uiRole = \App\Support\Staff\StaffAdminRoleCatalog::primaryUiRole($row->getRoleNames());
                        $isSelf = $actor && $actor->id === $row->id;
                    @endphp
                    <li wire:key="staff-card-{{ $row->id }}" class="hub-doc-mobile-card">
                        <div class="hub-doc-mobile-card__head">
                            <div class="min-w-0">
                                <p class="font-semibold truncate">{{ $row->name }}</p>
                                <p class="text-xs text-[var(--app-muted)] truncate" dir="ltr">{{ $row->email }}</p>
                            </div>
                            <span @class([
                                'pos-status-pill',
                                'pos-status-pill--paid' => $row->is_active,
                                'pos-status-pill--cancelled' => ! $row->is_active,
                            ])>{{ $row->is_active ? __('staff_users.active') : __('staff_users.inactive') }}</span>
                        </div>
                        <dl class="hub-doc-mobile-card__meta">
                            <div class="hub-doc-mobile-card__meta-row">
                                <dt class="text-[var(--app-muted)]">{{ __('staff_users.col_role') }}</dt>
                                <dd>{{ __('staff_users.roles.'.$uiRole) }}</dd>
                            </div>
                            <div class="hub-doc-mobile-card__meta-row">
                                <dt class="text-[var(--app-muted)]">{{ __('staff_users.col_language') }}</dt>
                                <dd @class(['text-end' => ($localeOptions[$row->locale ?? 'bn'] ?? '') && config('locales.supported.'.($row->locale ?? 'bn').'.dir') === 'rtl'])>
                                    {{ $localeOptions[$row->locale ?? 'bn'] ?? strtoupper($row->locale ?? 'bn') }}
                                </dd>
                            </div>
                        </dl>
                        <div class="hub-doc-mobile-card__actions">
                            <x-hub.staff-user-row-actions
                                :user-id="$row->id"
                                :is-active="(bool) $row->is_active"
                                :is-self="$isSelf"
                            />
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="admin-data-table-scroll -mx-4 hidden px-4 sm:block">
                <table class="admin-data-table hub-doc-table text-start text-sm" data-testid="staff-users-table">
                    <thead>
                        <tr class="border-b border-[var(--app-border)] bg-[var(--app-surface-muted)]/60 text-xs uppercase text-[var(--app-muted)]">
                            <th class="admin-data-table__sticky px-3 py-3 font-medium" scope="col">{{ __('staff_users.col_name') }}</th>
                            <th class="px-3 py-3 font-medium" scope="col">{{ __('staff_users.col_email') }}</th>
                            <th class="px-3 py-3 font-medium" scope="col">{{ __('staff_users.col_role') }}</th>
                            <th class="px-3 py-3 font-medium" scope="col">{{ __('staff_users.col_language') }}</th>
                            <th class="px-3 py-3 font-medium" scope="col">{{ __('staff_users.col_status') }}</th>
                            <th class="px-3 py-3 text-end font-medium" scope="col">{{ __('staff_users.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $row)
                            @php
                                $uiRole = \App\Support\Staff\StaffAdminRoleCatalog::primaryUiRole($row->getRoleNames());
                                $isSelf = $actor && $actor->id === $row->id;
                                $rowLocale = $row->locale ?? 'bn';
                                $localeRtl = config('locales.supported.'.$rowLocale.'.dir') === 'rtl';
                            @endphp
                            <tr class="border-b border-[var(--app-border)] last:border-0 hover:bg-[var(--app-surface-muted)]/40" wire:key="staff-row-{{ $row->id }}">
                                <td class="admin-data-table__sticky max-w-[11rem] px-3 py-2.5 font-medium">
                                    <span class="line-clamp-2" dir="auto">{{ $row->name }}</span>
                                </td>
                                <td class="max-w-[14rem] px-3 py-2.5 truncate" dir="ltr">{{ $row->email }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">{{ __('staff_users.roles.'.$uiRole) }}</td>
                                <td @class(['px-3 py-2.5 whitespace-nowrap', 'text-end' => $localeRtl]) @if ($localeRtl) dir="rtl" @endif>
                                    {{ $localeOptions[$rowLocale] ?? strtoupper($rowLocale) }}
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <span @class([
                                        'pos-status-pill text-xs',
                                        'pos-status-pill--paid' => $row->is_active,
                                        'pos-status-pill--cancelled' => ! $row->is_active,
                                    ])>{{ $row->is_active ? __('staff_users.active') : __('staff_users.inactive') }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-end">
                                    <x-hub.staff-user-row-actions
                                        :user-id="$row->id"
                                        :is-active="(bool) $row->is_active"
                                        :is-self="$isSelf"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-admin.hub-pager :paginator="$users" />
        </section>
    @endif

    <x-admin.hub-fab wire:click="startCreate" :label="__('staff_users.add')" />

    <x-admin.hub-sheet
        :show="$showForm"
        :title="$editingId ? __('staff_users.edit_title') : __('staff_users.add')"
        close-wire="closeForm"
        size="xl"
    >
        <form class="x-modal-form" wire:submit="save" novalidate data-testid="staff-user-form">
            <div class="x-modal-form__fields x-modal-form__fields--2 app-admin-form app-admin-form--staff-user">
                <x-field.input :label="__('staff_users.name')" name="name" wire:model="name" required />
                <x-field.input type="email" :label="__('staff_users.email')" name="email" wire:model="email" required />
                <x-field.select :label="__('staff_users.role')" name="role" wire:model="role" required :liveValidate="false" wrapper-class="staff-user-hub-field">
                    @foreach ($roleOptions as $roleKey)
                        <option value="{{ $roleKey }}">{{ __('staff_users.roles.'.$roleKey) }}</option>
                    @endforeach
                </x-field.select>
                <x-field.select :label="__('staff_users.language')" name="locale" wire:model="locale" required :liveValidate="false" wrapper-class="staff-user-hub-field">
                    @foreach ($localeOptions as $code => $label)
                        <option value="{{ $code }}" @selected($locale === $code)>{{ $label }}</option>
                    @endforeach
                </x-field.select>
                <x-admin.staff-user-password-fields
                    :creating="$editingId === null"
                    :generated-password-plain="$generatedPasswordPlain"
                />
            </div>
            <div class="x-modal__footer x-modal__footer--in-form">
                <x-modal.footer
                    cancel-wire="closeForm"
                    submit-target="save"
                    :submit-label="__('staff_users.save')"
                />
            </div>
        </form>
    </x-admin.hub-sheet>
</div>