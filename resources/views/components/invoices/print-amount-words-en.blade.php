@props(['text' => null, 'isPdf' => false])

@if (filled($text))
    @if ($isPdf)
        <p
            {{ $attributes->class(['invoice-a4__words invoice-print-words-block__en']) }}
            style="{{ \App\Support\Invoicing\DocumentPdfEnLineFit::wrappedBoxStyle((string) $text, 62, 7) }}"
        >{{ $text }}</p>
    @else
        @php($wrapped = \App\Support\Invoicing\InvoicePrintEnglishPrelines::amountWordsBlock((string) $text))
        <div
            {{ $attributes->class([
                'invoice-a4__words invoice-print-words-block__en invoice-print-en-block',
                'invoice-print-en-block--single' => $wrapped['single_line'],
            ]) }}
        >
            @foreach ($wrapped['lines'] as $line)
                <div class="invoice-print-en-preline">{{ $line }}</div>
            @endforeach
        </div>
    @endif
@endif