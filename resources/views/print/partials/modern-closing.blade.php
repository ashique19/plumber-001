@php
    /** @var array<string, mixed> $t */
    $tot = $t['totals'];
    $showQr = ($t['show_qr'] ?? true) && filled($t['qr_tlv_base64'] ?? null);
    $isQuotation = ($t['document_kind'] ?? 'invoice') === 'quotation';
@endphp
<section @class([
    'print-tpl-modern__closing',
    'print-tpl-modern__closing--no-qr' => ! $showQr || $isQuotation,
])>
    <table class="print-tpl-modern__closing-grid" role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            @if ($showQr && ! $isQuotation)
                <td class="print-tpl-modern__closing-qr" style="width:48mm" width="34%" valign="top">
                    <div class="print-tpl-modern__qr-wrap">
                        <x-invoices.print-qr
                            :tlv="$t['qr_tlv_base64']"
                            :png="$t['qr_png'] ?? null"
                            :fallback="(bool) ($t['qr_phase1_fallback'] ?? false)"
                            :split-captions="true"
                            :is-pdf="$isPdf ?? false"
                        />
                    </div>
                </td>
            @endif
            <td class="print-tpl-modern__closing-main" valign="top">
                @include('print.partials.modern-totals-table', ['tot' => $tot, 't' => $t])
                <div class="invoice-print-words-block print-tpl-modern__words">
                    <x-invoices.print-amount-words-en
                        :text="$t['amount_words_en'] ?? null"
                        :is-pdf="$isPdf ?? false"
                        class="invoice-print-words-block__en"
                    />
                    <x-invoices.print-amount-words-ar
                        :text="$t['amount_words_ar'] ?? null"
                        :is-pdf="$isPdf ?? false"
                        class="invoice-print-words-block__ar"
                    />
                </div>
            </td>
        </tr>
    </table>
</section>