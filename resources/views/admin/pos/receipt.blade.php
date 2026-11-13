@php
    use App\Support\EnabledLocales;
    use App\Support\Invoicing\InvoiceLineGrouper;

    $legal = $tenant->settings['legal_entity'] ?? [];
    $groupedLines = InvoiceLineGrouper::sectionsForDisplay($order->line_items ?? [], $locale);
    $receiptLocales = EnabledLocales::codes();
    $showSuccess = request()->boolean('paid');
    $autoPrint = request()->boolean('autoprint');
    $receiptQuery = array_filter([
        'locale' => $locale,
        'paid' => request()->query('paid'),
        'autoprint' => request()->query('autoprint'),
    ], fn ($v) => $v !== null && $v !== '');
    $backUrl = $branch
        ? route('admin.pos.terminal', [ $branch])
        : route('admin.pos.history', [ $order->branch_id]);
    $newOrderUrl = route('admin.pos.terminal', [ $order->branch_id]);
    $historyUrl = route('admin.pos.history', [ $order->branch_id]);
    $t = $thermal;
    $invoiceTitleEn = $t['is_simplified']
        ? __('pos.receipt_title_simplified_en')
        : __('pos.receipt_title_standard_en');
    $invoiceTitleAr = $t['is_simplified']
        ? __('pos.receipt_title_simplified_ar')
        : __('pos.receipt_title_standard_ar');
@endphp

<x-layouts.pos :branch="$branch">
    @push('head')
        <title>{{ __('pos.receipt') }} {{ $order->order_number }}</title>
        @vite(['resources/css/receipt.css', 'resources/js/receipt.js'])
    @endpush

    <div
        class="receipt-screen mx-auto w-full max-w-md"
        data-receipt-root
        data-auto-print="{{ $autoPrint ? '1' : '0' }}"
        data-testid="receipt-page"
    >
        <header class="receipt-page-toolbar no-print mb-4 flex flex-col gap-3" aria-label="{{ __('pos.row_actions') }}">
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <a href="{{ $backUrl }}" class="tap-action-muted min-h-11 flex-1 sm:flex-initial" data-testid="receipt-back">
                    {{ $branch ? __('pos.back_to_terminal') : __('pos.back_to_orders') }}
                </a>
                <button type="button" class="tap-action-primary min-h-11 flex-1 sm:flex-initial" data-testid="receipt-print" onclick="window.print()">
                    {{ $showSuccess ? __('pos.print_receipt') : __('pos.print') }}
                </button>
                <a href="{{ $newOrderUrl }}" class="tap-action-primary min-h-11 flex-1 sm:flex-initial" data-testid="receipt-new-order">
                    {{ $showSuccess ? __('pos.new_order') : __('pos.new_order') }}
                </a>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-[var(--app-muted)]">{{ __('pos.receipt') }} · {{ $order->order_number }}</span>
                <span class="hidden h-4 w-px bg-[var(--app-border)] sm:inline" aria-hidden="true"></span>
                @php
                    $localeLink = fn (string $code) => route('admin.pos.receipt', array_merge(['order' => $order], $receiptQuery, ['locale' => $code]));
                @endphp
                @foreach ($receiptLocales as $code)
                    <a href="{{ $localeLink($code) }}" @class(['tap-action-muted min-h-10 px-3 text-xs', 'ring-2 ring-[var(--cafe-caramel)]' => $locale === $code])>{{ strtoupper($code) }}</a>
                @endforeach
                <a href="{{ $historyUrl }}" class="tap-action-muted min-h-10 px-3 text-xs">{{ __('pos.history') }}</a>
            </div>
        </header>

        @if ($showSuccess)
            <div class="no-print receipt-success-bar mb-4" role="status">
                <h2>{{ __('pos.receipt_payment_success') }}</h2>
                <p class="m-0 text-sm">{{ __('pos.receipt_payment_success_hint') }}</p>
            </div>
        @endif

        <div role="status" class="no-print receipt-badge receipt-badge--{{ $compliance['tone'] }}">
            <strong>{{ $compliance['title'] }}</strong><br>
            {{ $compliance['detail'] }}
        </div>

        <p class="no-print receipt-tags-todo">{{ __('pos.receipt_tags_todo') }}</p>

        <article class="receipt-sheet receipt-thermal" aria-label="{{ __('pos.receipt') }}">
            <header class="receipt-thermal__header">
                <div class="receipt-thermal__seller">
                    @if ($t['logo_url'])
                        <img src="{{ asset($t['logo_url']) }}" alt="" class="receipt-thermal__logo" width="72" height="40">
                    @endif
                    <p class="receipt-thermal__seller-names">
                        <span class="receipt-thermal__seller-en">{{ $t['seller_en'] }}</span>
                        <span class="receipt-thermal__seller-ar" dir="rtl">{{ $t['seller_ar'] }}</span>
                    </p>
                </div>
                @foreach ($t['branch_lines'] as $line)
                    <p class="receipt-thermal__center receipt-thermal__xs">{{ $line }}</p>
                @endforeach
                @if ($t['phone'] || $t['fax'])
                    <p class="receipt-thermal__center receipt-thermal__xs">
                        @if ($t['phone'])
                            {{ __('pos.receipt_phone_fax') }}: {{ $t['phone'] }}@if ($t['fax']) / {{ $t['fax'] }}@endif
                        @elseif ($t['fax'])
                            {{ __('pos.receipt_fax') }}: {{ $t['fax'] }}
                        @endif
                    </p>
                @endif
                @if ($t['email'])
                    <p class="receipt-thermal__center receipt-thermal__xs">{{ __('pos.receipt_email') }}: {{ $t['email'] }}</p>
                @endif
                @if ($t['cr_number'])
                    <p class="receipt-thermal__center receipt-thermal__xs">{{ __('pos.receipt_cr_no') }}: {{ $t['cr_number'] }}</p>
                @endif
                @if ($t['trn'])
                    <p class="receipt-thermal__center receipt-thermal__xs receipt-thermal__trn">{{ __('pos.receipt_trn') }}: {{ $t['trn'] }}</p>
                @endif
            </header>

            @if ($invoice->qr_tlv_base64)
                <div class="receipt-thermal__qr" aria-label="{{ __('pos.qr_tlv') }}">
                    <canvas id="receipt-qr" data-tlv="{{ $invoice->qr_tlv_base64 }}" width="240" height="240"></canvas>
                    <p class="no-print receipt-qr-note">{{ __('pos.scan_qr_note') }}</p>
                </div>
            @endif

            <p class="receipt-thermal__rule" aria-hidden="true">--------------------------------</p>
            <p class="receipt-thermal__center receipt-thermal__title">{{ $invoiceTitleEn }}</p>
            <p class="receipt-thermal__center receipt-thermal__title" dir="rtl">{{ $invoiceTitleAr }}</p>
            <p class="receipt-thermal__rule" aria-hidden="true">--------------------------------</p>

            @if ($t['customer'])
                <div class="receipt-thermal__customer receipt-thermal__xs">
                    @if (! empty($t['customer']['masked_phone']))
                        <p>{{ __('pos.receipt_customer') }}: {{ $t['customer']['masked_phone'] }}</p>
                    @endif
                    @if (! empty($t['customer']['name']))
                        <p>{{ __('pos.receipt_customer_name') }}: {{ $t['customer']['name'] }}</p>
                    @endif
                </div>
            @endif

            <section class="receipt-thermal__lines" aria-label="{{ __('pos.current_order') }}">
                @php($thermalByIndex = $t['lines'])
                @foreach ($groupedLines['sections'] as $section)
                    @if ($groupedLines['uses_groups'] && ($section['title'] ?? '') !== '')
                        <p class="receipt-thermal__center receipt-thermal__xs receipt-thermal__line-group-heading" dir="rtl">
                            {{ InvoiceLineGrouper::sectionHeading($section, $locale) }}
                        </p>
                    @endif
                    @foreach ($section['lines'] as $rawLine)
                        @php($line = $thermalByIndex[$rawLine['_index'] ?? -1] ?? null)
                        @if (! $line)
                            @continue
                        @endif
                        <div class="receipt-thermal__line">
                            <p class="receipt-thermal__line-meta receipt-thermal__xs">
                                <span>{{ $line['barcode'] }}</span>
                                <span class="receipt-thermal__line-meta-end">
                                    {{ number_format($line['quantity'], 0) }} x {{ number_format($line['unit_price_inc'], 2) }} {{ $line['tax_code'] }}
                                </span>
                            </p>
                            <p class="receipt-thermal__line-ar receipt-thermal__xs" dir="rtl">{{ $line['name_ar'] }}</p>
                            <p class="receipt-thermal__line-en receipt-thermal__xs">
                                <span>{{ $line['name_en'] }}</span>
                                <span class="receipt-thermal__amt">{{ number_format($line['line_total_inc'], 2) }}</span>
                            </p>
                        </div>
                    @endforeach
                @endforeach
            </section>

            <p class="receipt-thermal__rule" aria-hidden="true">--------------------------------</p>

            <p class="receipt-thermal__total-row receipt-thermal__xs">
                <span>{{ __('pos.receipt_total') }}</span>
                <span class="receipt-thermal__amt">{{ number_format($t['total_inc_vat'], 2) }}</span>
            </p>
            <p class="receipt-thermal__xs">{{ __('pos.receipt_items_count') }}: {{ $t['item_count'] }}</p>
            <p class="receipt-thermal__xs">
                {{ $t['payment_display'] }} {{ __('pos.receipt_sar') }} {{ number_format($t['total_inc_vat'], 2) }}
            </p>
            @if ($t['card'])
                @if (! empty($t['card']['eft_trans_no']))
                    <p class="receipt-thermal__xs">{{ __('pos.receipt_eft_trans') }} = {{ $t['card']['eft_trans_no'] }}</p>
                @endif
                @if (! empty($t['card']['card_no']))
                    <p class="receipt-thermal__xs">{{ __('pos.receipt_card_no') }} = {{ $t['card']['card_no'] }}</p>
                @endif
                @if (! empty($t['card']['auth']))
                    <p class="receipt-thermal__xs">{{ __('pos.receipt_auth') }} = {{ $t['card']['auth'] }}</p>
                @endif
            @endif

            <p class="receipt-thermal__center receipt-thermal__xs receipt-thermal__tax-note">
                {{ __('pos.receipt_tax_inclusive_en') }}
                <span dir="rtl">{{ __('pos.receipt_tax_inclusive_ar') }}</span>
            </p>

            <table class="receipt-thermal__vat-table receipt-thermal__xs">
                <thead>
                    <tr>
                        <th>{{ __('pos.receipt_vat_col_tax') }}</th>
                        <th>{{ __('pos.receipt_vat_col_pct') }}</th>
                        <th>{{ __('pos.receipt_vat_col_before') }}</th>
                        <th>{{ __('pos.receipt_vat_col_incl') }}</th>
                        <th>{{ __('pos.receipt_vat_col_vat') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($t['vat_rows'] as $row)
                        <tr>
                            <td>{{ $row['tax_code'] }}</td>
                            <td>{{ $row['vat_percent'] }}</td>
                            <td>{{ number_format($row['before_vat'], 2) }}</td>
                            <td>{{ number_format($row['incl_vat'], 2) }}</td>
                            <td>{{ number_format($row['vat'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($t['cashier_name'])
                <p class="receipt-thermal__xs receipt-thermal__served">{{ __('pos.receipt_served_by') }}: {{ $t['cashier_name'] }}</p>
            @endif

            <table class="receipt-thermal__meta-table receipt-thermal__xs">
                <thead>
                    <tr>
                        <th>{{ __('pos.receipt_meta_date') }}</th>
                        <th>{{ __('pos.receipt_meta_time') }}</th>
                        <th>{{ __('pos.receipt_meta_store') }}</th>
                        <th>{{ __('pos.receipt_meta_pos') }}</th>
                        <th>{{ __('pos.receipt_meta_receipt') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $t['meta_table']['date'] }}</td>
                        <td>{{ $t['meta_table']['time'] }}</td>
                        <td>{{ $t['meta_table']['store'] }}</td>
                        <td>{{ $t['meta_table']['pos'] }}</td>
                        <td>{{ $t['meta_table']['receipt'] }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="receipt-thermal__center receipt-thermal__xs receipt-thermal__thanks" dir="rtl">{{ __('pos.receipt_thanks_ar') }}</p>
            <p class="receipt-thermal__center receipt-thermal__xs">{{ __('pos.receipt_thanks_en') }}</p>
            @if ($t['storefront_url'])
                <p class="receipt-thermal__center receipt-thermal__xs">{{ __('pos.receipt_shop_online') }} {{ $t['storefront_url'] }}</p>
            @endif

            <div class="receipt-thermal__barcode-wrap">
                <svg id="receipt-barcode" data-value="{{ $t['barcode_value'] }}" class="receipt-thermal__barcode" role="img" aria-label="{{ $t['barcode_value'] }}"></svg>
            </div>
        </article>
    </div>
</x-layouts.pos>
