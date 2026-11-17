@props([
    'userId',
    'isActive' => true,
    'isSelf' => false,
])

<div {{ $attributes->class(['hub-doc-table__actions', 'hub-doc-table__actions--compact']) }} data-testid="hub-staff-user-row-actions">
    <x-hub.row-action-icon
        icon="edit"
        :label="__('staff_users.action_edit')"
        wire:click="startEdit({{ $userId }})"
        test-id="hub-row-action-edit"
    />
    <x-hub.more-menu
        :menu-id="'staff-user-'.$userId"
        :summary-label="__('staff_users.action_more')"
    >
        <button
            type="button"
            class="hub-doc-table__more-item"
            role="menuitem"
            wire:click="startEdit({{ $userId }})"
        >{{ __('staff_users.action_edit') }}</button>
        @if ($isActive && ! $isSelf)
            <button
                type="button"
                class="hub-doc-table__more-item text-red-700"
                role="menuitem"
                wire:click="deactivateUser({{ $userId }})"
                wire:confirm="{{ __('staff_users.confirm_deactivate') }}"
            >{{ __('staff_users.action_deactivate') }}</button>
        @endif
        @if (! $isActive)
            <button
                type="button"
                class="hub-doc-table__more-item hub-doc-table__more-item--accent"
                role="menuitem"
                wire:click="reactivateUser({{ $userId }})"
            >{{ __('staff_users.action_reactivate') }}</button>
        @endif
    </x-hub.more-menu>
</div>