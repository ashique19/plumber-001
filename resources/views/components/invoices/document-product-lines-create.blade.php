@props([
    'lines' => [],
    'stockHint' => null,
    'vatOptions' => ['0' => '0%', '5' => '5%', '15' => '15%'],
    'discountOptions' => ['0' => '0%', '5' => '5%', '10' => '10%', '15' => '15%'],
])

@php
    use App\Support\Invoicing\DocumentCreateUiSupport;
    $filled = collect($lines)->filter(fn ($row) => filled($row['product_id'] ?? null));
@endphp

<div class="document-product-line-cards">
    @foreach ($lines as $index => $line)
        @continue(! filled($line['product_id'] ?? null))
        @php
            $amounts = DocumentCreateUiSupport::lineAmounts($line);
            $thumb = filled($line['product_image_url'] ?? null) ? asset($line['product_image_url']) : null;
        @endphp
        <article class="document-product-line-card invoice-document-lines__row" wire:key="doc-line-card-{{ $index }}-{{ $line['product_id'] }}">
            <div class="mb-2 flex items-start justify-between gap-2">
                <div class="flex min-w-0 items-start gap-2">
                    @if ($thumb)
                        <img src="{{ $thumb }}" alt="" class="document-product-thumb" loading="lazy" />
                    @else
                        <div class="document-product-thumb grid place-items-center text-[var(--app-muted)]"><x-app.icon name="photo" class="h-4 w-4" /></div>
                    @endif
                    <div class="min-w-0">
                        <p class="m-0 text-sm font-semibold">{{ $line['name'] }}</p>
                        @if (filled($line['name_ar'] ?? null) || filled($line['name_bn'] ?? null))
                            <p class="m-0 text-xs text-[var(--app-muted)]">
                                @if (filled($line['name_ar'] ?? null))<span dir="rtl">{{ $line['name_ar'] }}</span>@endif
                                @if (filled($line['name_ar'] ?? null) && filled($line['name_bn'] ?? null))<span> | </span>@endif
                                @if (filled($line['name_bn'] ?? null))<span>{{ $line['name_bn'] }}</span>@endif
                            </p>
                        @endif
                    </div>
                </div>
                <button type="button" class="tap-action-muted text-rose-600" wire:click="removeLine({{ $index }})" aria-label="{{ __('invoices.remove_line') }}">
                    <x-app.icon name="trash" class="h-5 w-5" />
                </button>
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="col-span-2 flex items-center justify-between gap-2">
                    <span class="text-[var(--app-muted)]">{{ __('invoices.line_qty') }}</span>
                    <div class="document-qty-stepper">
                        <button type="button" wire:click="adjustLineQuantity({{ $index }}, -1)">−</button>
                        <span>{{ $line['quantity'] }}</span>
                        <button type="button" wire:click="adjustLineQuantity({{ $index }}, 1)">+</button>
                    </div>
                </div>
                <x-field.money step="0.01" min="0" :label="__('invoices.line_price_ex_vat')" name="lines.{{ $index }}.unit_price" wire:model.live="lines.{{ $index }}.unit_price" />
                <x-field.select :label="__('invoices.line_vat')" name="lines.{{ $index }}.vat_rate" wire:model.live="lines.{{ $index }}.vat_rate">
                    @foreach ($vatOptions as $value => $optionLabel)
                        <option value="{{ $value }}">{{ $optionLabel }}</option>
                    @endforeach
                </x-field.select>
                <x-field.select :label="__('invoices.summary_discount')" name="lines.{{ $index }}.discount_percent" wire:model.live="lines.{{ $index }}.discount_percent">
                    @foreach ($discountOptions as $value => $optionLabel)
                        <option value="{{ $value }}">{{ $optionLabel }}</option>
                    @endforeach
                </x-field.select>
                <div class="flex flex-col justify-end">
                    <span class="text-xs text-[var(--app-muted)]">{{ __('invoices.line_total_inc_vat') }}</span>
                    <span class="font-semibold tabular-nums">{{ number_format($amounts['line_inc_vat'], 2) }}</span>
                </div>
            </div>
        </article>
    @endforeach
</div>

<table class="document-product-lines-table" aria-label="{{ __('invoices.lines_section') }}">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('invoices.line_product') }}</th>
            <th>{{ __('invoices.line_qty') }}</th>
            <th>{{ __('invoices.line_unit') }}</th>
            <th>{{ __('invoices.line_price_ex_vat') }}</th>
            <th>{{ __('invoices.summary_discount') }}</th>
            <th>{{ __('invoices.line_vat') }}</th>
            <th>{{ __('invoices.line_total_inc_vat') }}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @php $rowNum = 0; @endphp
        @foreach ($lines as $index => $line)
            @continue(! filled($line['product_id'] ?? null))
            @php
                $rowNum++;
                $amounts = DocumentCreateUiSupport::lineAmounts($line);
                $thumb = filled($line['product_image_url'] ?? null) ? asset($line['product_image_url']) : null;
            @endphp
            <tr class="invoice-document-lines__row" wire:key="doc-line-row-{{ $index }}-{{ $line['product_id'] }}">
                <td class="tabular-nums">{{ $rowNum }}</td>
                <td>
                    <div class="flex items-start gap-2">
                        @if ($thumb)
                            <img src="{{ $thumb }}" alt="" class="document-product-thumb" loading="lazy" />
                        @else
                            <div class="document-product-thumb grid place-items-center text-[var(--app-muted)]"><x-app.icon name="photo" class="h-4 w-4" /></div>
                        @endif
                        <div class="min-w-0">
                            <p class="m-0 font-medium">{{ $line['name'] }}</p>
                            @if (filled($line['name_ar'] ?? null) || filled($line['name_bn'] ?? null))
                                <p class="m-0 text-xs text-[var(--app-muted)]">
                                    @if (filled($line['name_ar'] ?? null))<span dir="rtl">{{ $line['name_ar'] }}</span>@endif
                                    @if (filled($line['name_ar'] ?? null) && filled($line['name_bn'] ?? null))<span> | </span>@endif
                                    @if (filled($line['name_bn'] ?? null))<span>{{ $line['name_bn'] }}</span>@endif
                                </p>
                            @endif
                        </div>
                    </div>
                </td>
                <td>
                    <div class="document-qty-stepper">
                        <button type="button" wire:click="adjustLineQuantity({{ $index }}, -1)">−</button>
                        <span>{{ $line['quantity'] }}</span>
                        <button type="button" wire:click="adjustLineQuantity({{ $index }}, 1)">+</button>
                    </div>
                </td>
                <td>
                    <span class="text-xs font-medium">{{ $line['unit_label'] ?: 'PCS' }}</span>
                </td>
                <td class="min-w-[6.5rem]">
                    <input type="number" step="0.01" min="0" class="form-field__control w-full" wire:model.live="lines.{{ $index }}.unit_price" />
                </td>
                <td class="min-w-[5rem]">
                    <select class="form-field__control w-full" wire:model.live="lines.{{ $index }}.discount_percent">
                        @foreach ($discountOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="min-w-[5rem]">
                    <select class="form-field__control w-full" wire:model.live="lines.{{ $index }}.vat_rate">
                        @foreach ($vatOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="tabular-nums font-semibold">{{ number_format($amounts['line_inc_vat'], 2) }}</td>
                <td>
                    <button type="button" class="tap-action-muted text-rose-600" wire:click="removeLine({{ $index }})" aria-label="{{ __('invoices.remove_line') }}">
                        <x-app.icon name="trash" class="h-5 w-5" />
                    </button>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

@if ($filled->isEmpty())
    <p class="rounded-xl border border-dashed border-[var(--app-border)] px-4 py-6 text-center text-sm text-[var(--app-muted)]">
        {{ __('invoices.document_lines_empty') }}
    </p>
@endif

@if ($stockHint !== null)
    <p class="document-stock-hint">
        <x-app.icon name="cube" class="h-4 w-4" aria-hidden="true" />
        {{ __('invoices.stock_hint', ['qty' => number_format($stockHint['quantity'], 0), 'unit' => $stockHint['unit_label']]) }}
    </p>
@endif

<button
    type="button"
    class="tap-action-outline mt-3 min-h-10 w-full text-sm font-semibold sm:w-auto"
    x-on:click="$el.closest('.document-create-page')?.querySelector('.invoice-add-line-search input')?.focus()"
>
    {{ __('invoices.add_another_product') }}
</button>