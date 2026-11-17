@props([
    'en',
    'ar',
    'strong' => false,
    'rowClass' => '',
])

<tr @class([$rowClass, 'invoice-print-totals__row--strong' => $strong])>
    <td class="invoice-print-totals__en">
        @if ($strong)
            <strong>{{ $en }}</strong>
        @else
            {{ $en }}
        @endif
    </td>
    <td class="invoice-print-totals__ar invoice-print-ar" dir="rtl">
        @if ($strong)
            <strong>{{ $ar }}</strong>
        @else
            {{ $ar }}
        @endif
    </td>
    <td class="invoice-print-totals__value tabular-nums" dir="ltr">
        @if ($strong)
            <strong><span class="invoice-print-money">{{ $slot }}</span></strong>
        @else
            <span class="invoice-print-money">{{ $slot }}</span>
        @endif
    </td>
</tr>