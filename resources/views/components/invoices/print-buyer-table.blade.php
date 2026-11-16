@props(['rows' => [], 'documentMeta' => []])

@if (count($rows) > 0)
    <table class="invoice-a4__buyer-table" role="presentation">
        <thead>
            <tr>
                <th scope="col" class="invoice-a4__buyer-table-label">Buyer</th>
                <th scope="col" class="invoice-a4__buyer-table-value"></th>
                <th scope="col" class="invoice-a4__buyer-table-label ar" dir="rtl">العميل</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="invoice-a4__buyer-table-label">{{ $row['en'] }}</td>
                    <td class="invoice-a4__buyer-table-value" @if (! empty($row['html'])) dir="auto" @endif>
                        @if (! empty($row['html']))
                            {!! $row['html'] !!}
                        @else
                            {{ $row['value'] ?? '' }}
                        @endif
                    </td>
                    <td class="invoice-a4__buyer-table-label ar" dir="rtl">{{ $row['ar'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (count($documentMeta) > 0)
    <div class="invoice-a4__customer-meta">
        @foreach ($documentMeta as $row)
            @if (filled($row['value'] ?? null))
                <div>
                    <span class="invoice-a4__customer-label">
                        {{ $row['en'] }}
                        <span class="ar">{{ $row['ar'] }}</span>
                    </span>
                    <div>{{ $row['value'] }}</div>
                </div>
            @endif
        @endforeach
    </div>
@endif