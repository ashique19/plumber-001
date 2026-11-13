<x-auth.staff-shell :business="$business" :title="__('pos.login_title')">
    <h1 class="text-center text-xl font-bold text-plumber-navy sm:text-2xl">{{ __('pos.login_title') }}</h1>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4" novalidate>
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
            <label class="flex min-h-11 items-center gap-2 text-plumber-navy">
                <input type="checkbox" name="remember" class="auth-checkbox" value="1">
                <span>{{ __('pos.remember') }}</span>
            </label>
            <a href="{{ route('password.request') }}" class="min-h-11 inline-flex items-center font-medium text-plumber-steel underline-offset-4 hover:text-plumber-navy hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-plumber-safety">
                {{ __('auth.forgot_password') }}
            </a>
        </div>
        <button type="submit" class="tap-action-primary w-full">{{ __('pos.sign_in') }}</button>
    </form>
</x-auth.staff-shell>
