@if ($zheartTestMessage !== '')
    <div
        @class([
            'rounded-lg p-3 text-sm',
            'bg-emerald-50 text-emerald-950 ring-1 ring-emerald-200' => $zheartTestStatus === 'ok',
            'bg-amber-50 text-amber-950 ring-1 ring-amber-200' => $zheartTestStatus === 'warn',
            'bg-red-50 text-red-950 ring-1 ring-red-200' => $zheartTestStatus === 'error',
            'bg-[var(--app-surface-muted)] text-[var(--app-muted)]' => $zheartTestStatus === null,
        ])
        role="status"
        data-testid="business-zheart-test-result"
    >
        @if ($zheartTestStatus !== 'ok' && $zheartTestReason)
            <p class="font-medium">{{ $zheartTestMessage }}</p>
        @else
            <p class="font-medium">{{ __('business.zheart.test_connected_heading') }}</p>
        @endif

        @if ($zheartTestReason === 'not_ready')
            <div class="mt-2" data-testid="business-zheart-test-issues">
                <p class="text-xs">{{ __('business.zheart.test_not_ready_hint') }}</p>
                @if (! empty($zheartTestIssues))
                    <ul class="mt-2 list-disc space-y-1 ps-5">
                        @foreach ($zheartTestIssues as $issue)
                            <li>{{ $issue }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if ($zheartTestHttpStatus !== null)
            <p class="mt-1 text-xs opacity-80" data-testid="business-zheart-test-http-status">
                {{ __('business.zheart.test_http_status', ['status' => $zheartTestHttpStatus]) }}
            </p>
        @endif

        @if (filled($zheartTestRequestId ?? null))
            <p class="mt-1 text-xs opacity-80" data-testid="business-zheart-test-request-id">
                {!! str_replace('%ID%', '<bdi dir="ltr" class="break-all font-mono">'.e($zheartTestRequestId).'</bdi>', e(__('business.zheart.test_request_id', ['id' => '%ID%']))) !!}
            </p>
        @endif

        @if (is_array($zheartTestCard))
            <dl class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2" data-testid="business-zheart-test-card">
                <div class="min-w-0 sm:col-span-2">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_client_name') }}</dt>
                    <dd class="font-medium break-words">{{ $zheartTestCard['client_name'] ?? '—' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_vat') }}</dt>
                    <dd class="font-mono text-sm tabular-nums" dir="ltr">{{ $zheartTestCard['vat_number'] ?? '—' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_cr') }}</dt>
                    <dd class="font-mono text-sm tabular-nums" dir="ltr">{{ $zheartTestCard['cr_number'] ?? '—' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_environment') }}</dt>
                    <dd>
                        @if (! empty($zheartTestCard['environment_production']))
                            <span class="inline-flex rounded px-2 py-0.5 text-xs font-semibold uppercase tracking-wide bg-red-600 text-white">{{ $zheartTestCard['environment'] ?? 'PRODUCTION' }}</span>
                        @else
                            <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-[var(--app-surface-muted)] text-[var(--app-fg)]">{{ $zheartTestCard['environment'] ?? '—' }}</span>
                        @endif
                    </dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_active') }}</dt>
                    <dd>{{ $zheartTestCard['active'] ?? '—' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_onboarding') }}</dt>
                    <dd>{{ $zheartTestCard['onboarding'] ?? '—' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_csid_expires') }}</dt>
                    <dd @class(['font-mono text-sm tabular-nums', 'text-amber-800 font-medium' => ! empty($zheartTestCard['csid_expires_soon'])]) dir="ltr">
                        {{ $zheartTestCard['csid_expires'] ?? '—' }}
                    </dd>
                </div>
                <div class="min-w-0 sm:col-span-2">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_last_zatca') }}</dt>
                    <dd class="font-mono text-sm tabular-nums" dir="ltr">{{ $zheartTestCard['last_zatca_call'] ?? '—' }}</dd>
                </div>
                <div class="min-w-0 sm:col-span-2">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_api_key') }}</dt>
                    <dd class="break-words" dir="ltr">
                        @php
                            $keyLabel = $zheartTestCard['key_label'] ?? '—';
                            $keyLast4 = $zheartTestCard['key_last4'] ?? '—';
                        @endphp
                        @if ($keyLabel !== '—' || $keyLast4 !== '—')
                            {{ $keyLabel }} @if ($keyLast4 !== '—')<span class="font-mono tabular-nums">{{ $keyLast4 }}</span>@endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_key_expires') }}</dt>
                    <dd class="font-mono text-sm tabular-nums" dir="ltr">{{ $zheartTestCard['key_expires'] ?? '—' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('business.zheart.card_ip_allowlist') }}</dt>
                    <dd>{{ $zheartTestCard['ip_allowlist'] ?? '—' }}</dd>
                </div>
            </dl>
        @endif

        @if (! empty($zheartTestWarnings))
            <ul class="mt-3 list-none space-y-1 border-t border-current/10 pt-3 text-xs" data-testid="business-zheart-test-warnings">
                @foreach ($zheartTestWarnings as $warning)
                    <li class="font-medium text-amber-900">{{ $warning }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif