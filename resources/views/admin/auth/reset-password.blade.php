<x-auth.tenant-shell :tenant="$business" :title="__('auth.reset_title')">
    <h1 class="text-xl font-bold text-cafe-espresso sm:text-2xl">{{ __('auth.reset_title') }}</h1>
    <p class="mt-2 text-sm text-cafe-mocha">{{ __('auth.reset_lead') }}</p>

    <form method="POST" action="{{ route('admin.password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label @class(['auth-field', 'auth-field--invalid' => $errors->has('email')])>
            {{ __('pos.email') }}
            <input
                type="email"
                name="email"
                value="{{ old('email', $email) }}"
                required
                autocomplete="username"
                @class(['auth-input', 'auth-input--invalid' => $errors->has('email')])
                @if($errors->has('email')) aria-invalid="true" @endif
            >
            @error('email')
                <p class="auth-field__error" role="alert">{{ $message }}</p>
            @enderror
        </label>
        <label @class(['auth-field', 'auth-field--invalid' => $errors->has('password')])>
            {{ __('auth.new_password') }}
            <input
                type="password"
                name="password"
                required
                autocomplete="new-password"
                @class(['auth-input', 'auth-input--invalid' => $errors->has('password')])
                @if($errors->has('password')) aria-invalid="true" @endif
            >
        </label>
        <label class="auth-field">
            {{ __('auth.confirm_password') }}
            <input type="password" name="password_confirmation" required autocomplete="new-password" class="auth-input">
        </label>
        @error('password')
            <p class="auth-field__error" role="alert">{{ $message }}</p>
        @enderror
        <button type="submit" class="tap-action-primary w-full">{{ __('auth.reset_password') }}</button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="font-medium text-cafe-mocha underline-offset-4 hover:underline">{{ __('auth.back_to_login') }}</a>
    </p>
</x-auth.tenant-shell>
