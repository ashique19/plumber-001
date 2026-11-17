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
    <details class="hub-doc-table__more relative">
        <summary
            class="hub-doc-table__action hub-doc-table__action--icon hub-doc-table__action--more"
            aria-label="{{ __('staff_users.action_more') }}"
            title="{{ __('staff_users.action_more') }}"
            data-testid="hub-row-action-more"
        >
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="19" cy="12" r="1.75"/></svg>
            <span class="sr-only">{{ __('staff_users.action_more') }}</span>
        </summary>
        <div class="hub-doc-table__more-panel" role="menu">
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
        </div>
    </details>
</div>