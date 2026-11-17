@props([
    'customerId',
])

<div {{ $attributes->class(['hub-doc-table__actions', 'hub-doc-table__actions--compact']) }} data-testid="hub-customer-row-actions">
    <x-hub.row-action-icon
        icon="view"
        :label="__('customers.action_view')"
        wire:click="selectCustomer({{ $customerId }})"
        test-id="hub-row-action-view"
    />
    <x-hub.row-action-icon
        icon="edit"
        :label="__('customers.action_edit')"
        wire:click="startEdit({{ $customerId }})"
        test-id="hub-row-action-edit"
        accent
    />
    <x-hub.more-menu
        :menu-id="'customer-'.$customerId"
        :summary-label="__('customers.action_more')"
    >
        <button
            type="button"
            class="hub-doc-table__more-item"
            role="menuitem"
            wire:click="startEdit({{ $customerId }})"
        >{{ __('customers.action_edit') }}</button>
        <button
            type="button"
            class="hub-doc-table__more-item text-red-700"
            role="menuitem"
            wire:click="deleteCustomer({{ $customerId }})"
            wire:confirm="{{ __('customers.confirm_delete') }}"
            data-testid="hub-customer-action-delete"
        >{{ __('customers.action_delete') }}</button>
    </x-hub.more-menu>
</div>