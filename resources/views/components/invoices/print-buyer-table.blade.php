@props([
    'rows' => [],
    'splitLeft' => [],
    'splitRight' => [],
])

@php
    $hasSplit = collect($splitLeft)->contains(fn (array $row): bool => filled($row['value'] ?? null))
        || collect($splitRight)->contains(fn (array $row): bool => filled($row['value'] ?? null));
    $showTable = count($rows) > 0 || $hasSplit;
@endphp

@if ($showTable)
    <table class="invoice-a4__buyer-table" role="presentation">
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="invoice-a4__buyer-table-label">{{ $row['en'] }}</td>
                    <td @class(['invoice-a4__buyer-table-value', 'invoice-a4__buyer-table-value--nowrap' => ! empty($row['nowrap'])]) @if (! empty($row['html'])) dir="auto" @endif>
                        @if (! empty($row['html']))
                            {!! $row['html'] !!}
                        @else
                            {{ $row['value'] ?? '' }}
                        @endif
                    </td>
                    <td class="invoice-a4__buyer-table-label ar invoice-print-ar" dir="rtl">{{ $row['ar'] }}</td>
                </tr>
            @endforeach

            @if ($hasSplit)
                <tr>
                    <td colspan="3" class="invoice-a4__buyer-table-split-cell">
                        <div class="invoice-a4__buyer-table-split">
                            <div class="invoice-a4__buyer-table-split-col">
                                @foreach ($splitLeft as $row)
                                    @if (filled($row['value'] ?? null))
                                        <div class="invoice-a4__buyer-table-split-row">
                                            <span class="invoice-a4__buyer-table-label">{{ $row['en'] }}</span>
                                            <span @class(['invoice-a4__buyer-table-value', 'invoice-a4__buyer-table-value--nowrap' => ! empty($row['nowrap'])])>{{ $row['value'] }}</span>
                                            <span class="invoice-a4__buyer-table-label ar invoice-print-ar" dir="rtl">{{ $row['ar'] }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <div class="invoice-a4__buyer-table-split-col">
                                @foreach ($splitRight as $row)
                                    @if (filled($row['value'] ?? null))
                                        <div class="invoice-a4__buyer-table-split-row">
                                            <span class="invoice-a4__buyer-table-label">{{ $row['en'] }}</span>
                                            <span @class(['invoice-a4__buyer-table-value', 'invoice-a4__buyer-table-value--nowrap' => ! empty($row['nowrap'])])>{{ $row['value'] }}</span>
                                            <span class="invoice-a4__buyer-table-label ar invoice-print-ar" dir="rtl">{{ $row['ar'] }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
@endif