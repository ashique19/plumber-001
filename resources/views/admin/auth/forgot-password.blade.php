<x-auth.tenant-shell :tenant="$business" :title="__('auth.forgot_title')">
    <h1 class="text-xl font-bold text-cafe-espresso sm:text-2xl">{{ __('auth.forgot_title') }}</h1>
    <p class="mt-2 text-sm text-cafe-mocha">{{ __('auth.forgot_lead') }}</p>

    <form method="POST" action="{{ route('admin.password.email') }}" class="mt-6 space-y-4">
        @csrf
        <label @class(['auth-field', 'auth-field--invalid' => $errors->has('email')])>
            {{ __('pos.email') }}
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="username"
                @class(['auth-input', 'auth-input--invalid' => $errors->has('email')])
                @if($errors->has('email')) aria-invalid="true" @endif
            >
            @error('email')
                <p class="auth-field__error" role="alert">{{ $message }}</p>
            @enderror
        </label>
        <button type="submit" class="tap-action-primary w-full">{{ __('auth.send_reset_link') }}</button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="font-medium text-cafe-mocha underline-offset-4 hover:underline">{{ __('auth.back_to_login') }}</a>
    </p>
</x-auth.tenant-shell>
