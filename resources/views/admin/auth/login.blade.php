<x-auth.tenant-shell :tenant="$business" :title="__('pos.login_title')">
    <h1 class="text-xl font-bold text-cafe-espresso sm:text-2xl">{{ __('pos.login_title') }}</h1>
    <p class="mt-1 text-sm text-cafe-mocha">{{ data_get($business->settings, 'legal_entity.name_en', $business->name) }}</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
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
        <label @class(['auth-field', 'auth-field--invalid' => $errors->has('password')])>
            {{ __('pos.password') }}
            <input
                type="password"
                name="password"
                required
                autocomplete="current-password"
                @class(['auth-input', 'auth-input--invalid' => $errors->has('password')])
                @if($errors->has('password')) aria-invalid="true" @endif
            >
            @error('password')
                <p class="auth-field__error" role="alert">{{ $message }}</p>
            @enderror
        </label>
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <label class="flex min-h-11 items-center gap-2">
                <input type="checkbox" name="remember" class="auth-checkbox">
                {{ __('pos.remember') }}
            </label>
            <a href="{{ route('admin.password.request') }}" class="min-h-11 inline-flex items-center font-medium text-cafe-mocha underline-offset-4 hover:underline">
                {{ __('auth.forgot_password') }}
            </a>
        </div>
        <button type="submit" class="tap-action-primary w-full">{{ __('pos.sign_in') }}</button>
    </form>
</x-auth.tenant-shell>
