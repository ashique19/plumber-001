<div>
    <header class="mb-4">
        <h1 class="app-page-title">{{ __('pos.select_branch') }}</h1>
        <p class="app-page-lead">{{ __('pos.title') }}</p>
    </header>
    <div class="grid gap-3 sm:grid-cols-2">
        @forelse($branches as $branch)
            <article class="app-card p-4 sm:p-5">
                <h2 class="text-lg font-semibold">{{ $branch->name }}</h2>
                <div class="mt-4 flex flex-col gap-2">
                    <a class="tap-action-primary w-full" href="{{ route('admin.pos.terminal', [request()->route('tenant'), $branch]) }}">
                        <x-app.icon name="cart" class="h-5 w-5" />
                        {{ __('pos.open_terminal') }}
                    </a>
                    <div class="grid grid-cols-3 gap-2">
                        <a class="tap-action-muted min-h-11 px-2 text-xs sm:text-sm" href="{{ route('admin.pos.history', [request()->route('tenant'), $branch]) }}">{{ __('pos.history') }}</a>
                        <a class="tap-action-muted min-h-11 px-2 text-xs sm:text-sm" href="{{ route('admin.pos.eod', [request()->route('tenant'), $branch]) }}">{{ __('pos.end_of_day') }}</a>
                        <a class="tap-action-muted min-h-11 px-2 text-xs sm:text-sm" href="{{ route('admin.pos.ops', [request()->route('tenant'), $branch]) }}">{{ __('pos.nav_ops') }}</a>
                    </div>
                </div>
            </article>
        @empty
            <x-app.empty-state class="col-span-full">
                <p class="text-sm text-[var(--app-muted)]">{{ __('pos.empty_branches') }}</p>
            </x-app.empty-state>
        @endforelse
    </div>
</div>
