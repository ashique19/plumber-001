<div class="staff-profile pb-24 lg:pb-8">
    <header class="mb-4">
        <h1 class="app-page-title">{{ __('profile.title') }}</h1>
        <p class="app-page-lead">{{ __('profile.subtitle') }}</p>
    </header>

    <form class="app-card max-w-2xl space-y-4 p-4" wire:submit="save">
        <x-field.input :label="__('profile.name')" name="name" wire:model.blur="name" required />
        <x-field.input type="email" :label="__('profile.email')" name="email" wire:model.blur="email" required autocomplete="username" />
        <x-field.input type="password" :label="__('profile.password')" name="password" wire:model="password" autocomplete="new-password" :hint="__('profile.password_hint')" />
        <x-field.input type="password" :label="__('profile.password_confirm')" name="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" />
        <x-field.select :label="__('profile.language')" name="locale" wire:model="locale" required>
            @foreach ($localeOptions as $code => $label)
                <option value="{{ $code }}">{{ $label }}</option>
            @endforeach
        </x-field.select>
        <div class="flex justify-end pt-2">
            <button type="submit" class="tap-action-primary min-h-11 px-5 text-sm font-semibold" wire:loading.attr="disabled" wire:target="save">
                {{ __('business.save') }}
            </button>
        </div>
    </form>
</div>