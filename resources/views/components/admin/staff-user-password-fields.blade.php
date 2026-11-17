@props([
    'creating' => true,
    'generatedPasswordPlain' => null,
])

<div
    @class([
        'staff-user-password-fields',
        'col-span-full' => ! $creating,
    ])
    x-data="{
        showPassword: false,
        showConfirm: false,
    }"
>
    @unless ($creating)
        <p class="staff-user-password-fields__legend type-label">{{ __('staff_users.reset_password_section') }}</p>
        <p class="staff-user-password-fields__hint type-small text-[var(--app-muted)]">{{ __('staff_users.reset_password_hint') }}</p>
    @endunless

    <div class="staff-user-password-fields__toolbar mb-3">
        <button type="button" class="tap-action-muted min-h-10 px-3 text-sm font-semibold" wire:click="generatePassword" data-testid="staff-user-generate-password">
            {{ __('staff_users.generate_password') }}
        </button>
    </div>

    @if (filled($generatedPasswordPlain))
        <div
            class="staff-user-password-fields__reveal mb-3 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-muted)]/60 p-3 text-sm"
            role="status"
            data-testid="staff-user-generated-password-banner"
        >
            <p class="font-semibold">{{ __('staff_users.generated_password_once') }}</p>
            <p class="mt-1 font-mono text-base break-all select-all" dir="ltr">{{ $generatedPasswordPlain }}</p>
            <p class="mt-1 type-small text-[var(--app-muted)]">{{ __('staff_users.generated_password_copy_hint') }}</p>
            <button type="button" class="tap-action-muted mt-2 min-h-9 px-2 text-xs font-semibold" wire:click="dismissGeneratedPassword">
                {{ __('staff_users.dismiss_generated_password') }}
            </button>
        </div>
    @endif

    <div class="staff-user-password-fields__grid">
        <label class="form-field">
            <span class="form-field__label type-label">
                {{ $creating ? __('staff_users.password') : __('staff_users.new_password') }}
                @if ($creating)<span class="text-rose-600" aria-hidden="true">*</span>@endif
            </span>
            <div class="admin-field-wrap admin-field-wrap--with-suffix">
                <input
                    :type="showPassword ? 'text' : 'password'"
                    name="password"
                    id="staff-user-password"
                    autocomplete="new-password"
                    wire:model="password"
                    wire:input="touchLiveField('password')"
                    class="form-field__control"
                    data-testid="staff-user-password"
                />
                <button
                    type="button"
                    class="admin-field-clear admin-field-toggle-password"
                    @click="showPassword = !showPassword"
                    :aria-label="showPassword ? @js(__('staff_users.hide_password')) : @js(__('staff_users.show_password'))"
                    :title="showPassword ? @js(__('staff_users.hide_password')) : @js(__('staff_users.show_password'))"
                    data-testid="staff-user-toggle-password"
                >
                    <x-app.icon name="eye" class="h-5 w-5" />
                </button>
            </div>
            @error('password')
                <span class="form-field__error type-small">{{ $message }}</span>
            @enderror
        </label>

        <label class="form-field">
            <span class="form-field__label type-label">
                {{ __('staff_users.password_confirm') }}
                @if ($creating)<span class="text-rose-600" aria-hidden="true">*</span>@endif
            </span>
            <div class="admin-field-wrap admin-field-wrap--with-suffix">
                <input
                    :type="showConfirm ? 'text' : 'password'"
                    name="password_confirmation"
                    id="staff-user-password-confirmation"
                    autocomplete="new-password"
                    wire:model="password_confirmation"
                    class="form-field__control"
                    data-testid="staff-user-password-confirmation"
                />
                <button
                    type="button"
                    class="admin-field-clear admin-field-toggle-password"
                    @click="showConfirm = !showConfirm"
                    :aria-label="showConfirm ? @js(__('staff_users.hide_password')) : @js(__('staff_users.show_password'))"
                    data-testid="staff-user-toggle-password-confirm"
                >
                    <x-app.icon name="eye" class="h-5 w-5" />
                </button>
            </div>
            @error('password_confirmation')
                <span class="form-field__error type-small">{{ $message }}</span>
            @enderror
        </label>
    </div>
</div>