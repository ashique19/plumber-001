@props(['text' => null, 'isPdf' => false])

@if (filled($text))
    @php(
        $wrapped = $isPdf
            ? \App\Support\Invoicing\InvoicePrintArabicPrelines::amountWordsBlockForPdf((string) $text)
            : \App\Support\Invoicing\InvoicePrintArabicPrelines::amountWordsBlock((string) $text)
    )
    <div
        {{ $attributes->merge([
            'class' => trim(
                'invoice-a4__words invoice-pdf-ar-block'
                .($wrapped['compact'] ? ' invoice-pdf-ar-lines--compact' : '')
            ),
            'dir' => 'rtl',
            'lang' => 'ar',
        ]) }}
    >
        @foreach ($wrapped['lines'] as $line)
            <div class="invoice-pdf-ar-preline" dir="rtl" lang="ar">{{ $line }}</div>
        @endforeach
    </div>
@endif