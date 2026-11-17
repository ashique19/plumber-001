@props([
    'rows' => [],
])

@if (count($rows) > 0)
    <table class="invoice-print-meta-grid" role="presentation">
        <tbody>
            @foreach (array_chunk($rows, 2) as $pair)
                <tr>
                    @foreach ($pair as $row)
                        <td>
                            <x-invoices.print-labeled-row
                                :en="$row['en']"
                                :ar="$row['ar']"
                                :value="$row['value'] ?? null"
                                :nowrap="! empty($row['nowrap'])"
                            >
                                @if (! empty($row['html']))
                                    {!! $row['html'] !!}
                                @endif
                            </x-invoices.print-labeled-row>
                        </td>
                    @endforeach
                    @if (count($pair) === 1)
                        <td aria-hidden="true"></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif