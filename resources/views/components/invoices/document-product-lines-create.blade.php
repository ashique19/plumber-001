@props([
    'lines' => [],
    'stockHint' => null,
    'vatOptions' => ['0' => '0%', '5' => '5%', '15' => '15%'],
    'discountOptions' => ['0' => '0%', '5' => '5%', '10' => '10%', '15' => '15%'],
])

@php
    use App\Support\Invoicing\DocumentCreateUiSupport;
    $filled = collect($lines)->filter(fn ($row) => DocumentCreateUiSupport::lineIsPresent($row));
    $unitOptions = DocumentCreateUiSupport::productUnitOptions();
    $itemTypeOptions = DocumentCreateUiSupport::itemTypeOptions();
    $vatCategoryOptions = DocumentCreateUiSupport::vatZatcaCategoryOptions();
@endphp

<section class="invoice-document-lines" wire:key="invoice-document-lines-section" aria-label="{{ __('invoices.lines_section') }}">
@if ($filled->isEmpty())
    <p class="rounded-xl border border-dashed border-[var(--app-border)] px-4 py-6 text-center text-sm text-[var(--app-muted)]">
        {{ __('invoices.document_lines_empty') }}
    </p>
@else
    <div class="document-product-line-cards lg:hidden" wire:key="doc-line-cards-root">
        @foreach ($lines as $index => $line)
            @continue(! DocumentCreateUiSupport::lineIsPresent($line))
            @php
                $lineId = (string) ($line['line_id'] ?? 'line-'.$index);
                $amounts = DocumentCreateUiSupport::lineAmounts($line);
                $thumb = filled($line['product_image_url'] ?? null) ? asset($line['product_image_url']) : null;
                $isCustom = DocumentCreateUiSupport::isCustomLine($line);
            @endphp
            <article
                class="document-product-line-card invoice-document-lines__row"
                wire:key="doc-line-card-{{ $lineId }}"
                data-document-line-id="{{ $lineId }}"
                data-document-line-index="{{ $index }}"
                @if ($isCustom) data-custom-line="1" @endif
            >
                <div class="mb-2 flex items-start justify-between gap-2">
                    <div class="flex min-w-0 flex-1 items-start gap-2">
                        @if ($thumb)
                            <img src="{{ $thumb }}" alt="" class="document-product-thumb" loading="lazy" />
                        @else
                            <div class="document-product-thumb grid place-items-center text-[var(--app-muted)]"><x-app.icon name="photo" class="h-4 w-4" /></div>
                        @endif
                        <div class="min-w-0 flex-1">
                            @if ($isCustom)
                                <p class="m-0 mb-2 text-xs font-semibold text-[var(--app-muted)]">{{ __('invoices.custom_line') }}</p>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="block text-xs font-medium text-[var(--app-muted)]">
                                        {{ __('invoices.line_name_en') }}
                                        <input type="text" class="form-field__control mt-1 w-full" wire:model.live="lines.{{ $index }}.name" required />
                                    </label>
                                    <label class="block text-xs font-medium text-[var(--app-muted)]">
                                        {{ __('invoices.line_name_ar') }}
                                        <input type="text" dir="rtl" class="form-field__control mt-1 w-full" wire:model.live="lines.{{ $index }}.name_ar" required />
                                    </label>
                                    <label class="block text-xs font-medium text-[var(--app-muted)]">
                                        {{ __('invoices.line_name_bn') }}
                                        <input type="text" class="form-field__control mt-1 w-full" wire:model.live="lines.{{ $index }}.name_bn" />
                                    </label>
                                </div>
                            @else
                                <p class="m-0 text-sm font-semibold">{{ $line['name'] }}</p>
                                @if (filled($line['name_ar'] ?? null) || filled($line['name_bn'] ?? null))
                                    <p class="m-0 text-xs text-[var(--app-muted)]">
                                        @if (filled($line['name_ar'] ?? null))<span dir="rtl">{{ $line['name_ar'] }}</span>@endif
                                        @if (filled($line['name_ar'] ?? null) && filled($line['name_bn'] ?? null))<span> | </span>@endif
                                        @if (filled($line['name_bn'] ?? null))<span>{{ $line['name_bn'] }}</span>@endif
                                    </p>
                                @endif
                            @endif
                        </div>
                    </div>
                    <button type="button" class="tap-action-muted text-rose-600" wire:click="removeLine({{ $index }})" aria-label="{{ __('invoices.remove_line') }}">
                        <x-app.icon name="trash" class="h-5 w-5" />
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    @if ($isCustom)
                        <x-field.select :label="__('invoices.line_item_type')" name="lines.{{ $index }}.item_type" wire:model.live="lines.{{ $index }}.item_type">
                            @foreach ($itemTypeOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </x-field.select>
                        <x-field.select :label="__('invoices.line_unit')" name="lines.{{ $index }}.product_unit_id" wire:model.live="lines.{{ $index }}.product_unit_id">
                            @foreach ($unitOptions as $unit)
                                <option value="{{ $unit['id'] }}">{{ $unit['label'] }}</option>
                            @endforeach
                        </x-field.select>
                    @endif
                    <div class="col-span-2 flex items-center justify-between gap-2">
                        <span class="text-[var(--app-muted)]">{{ __('invoices.line_qty') }}</span>
                        <div class="document-qty-stepper" data-testid="line-qty-stepper-{{ $lineId }}">
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
                    @if ($isCustom)
                        <x-field.select :label="__('invoices.line_vat_category')" name="lines.{{ $index }}.vat_zatca_category" wire:model.live="lines.{{ $index }}.vat_zatca_category">
                            @foreach ($vatCategoryOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </x-field.select>
                    @endif
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

    <div class="document-product-lines-scroll hidden lg:block" wire:key="doc-line-table-root">
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
                    @continue(! DocumentCreateUiSupport::lineIsPresent($line))
                    @php
                        $lineId = (string) ($line['line_id'] ?? 'line-'.$index);
                        $rowNum++;
                        $amounts = DocumentCreateUiSupport::lineAmounts($line);
                        $thumb = filled($line['product_image_url'] ?? null) ? asset($line['product_image_url']) : null;
                        $isCustom = DocumentCreateUiSupport::isCustomLine($line);
                    @endphp
                    <tr
                        class="invoice-document-lines__row"
                        wire:key="doc-line-row-{{ $lineId }}"
                        data-document-line-id="{{ $lineId }}"
                        data-document-line-index="{{ $index }}"
                        @if ($isCustom) data-custom-line="1" @endif
                    >
                        <td class="tabular-nums">{{ $rowNum }}</td>
                        <td>
                            <div class="flex items-start gap-2">
                                @if ($thumb)
                                    <img src="{{ $thumb }}" alt="" class="document-product-thumb" loading="lazy" />
                                @else
                                    <div class="document-product-thumb grid place-items-center text-[var(--app-muted)]"><x-app.icon name="photo" class="h-4 w-4" /></div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    @if ($isCustom)
                                        <p class="m-0 mb-1 text-xs font-semibold text-[var(--app-muted)]">{{ __('invoices.custom_line') }}</p>
                                        <div class="grid grid-cols-1 gap-1.5 xl:grid-cols-2">
                                            <input type="text" class="form-field__control w-full" wire:model.live="lines.{{ $index }}.name" placeholder="{{ __('invoices.line_name_en') }}" required />
                                            <input type="text" dir="rtl" class="form-field__control w-full" wire:model.live="lines.{{ $index }}.name_ar" placeholder="{{ __('invoices.line_name_ar') }}" required />
                                            <input type="text" class="form-field__control w-full xl:col-span-2" wire:model.live="lines.{{ $index }}.name_bn" placeholder="{{ __('invoices.line_name_bn') }}" />
                                            <select class="form-field__control w-full" wire:model.live="lines.{{ $index }}.item_type" aria-label="{{ __('invoices.line_item_type') }}">
                                                @foreach ($itemTypeOptions as $option)
                                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                            <select class="form-field__control w-full" wire:model.live="lines.{{ $index }}.vat_zatca_category" aria-label="{{ __('invoices.line_vat_category') }}">
                                                @foreach ($vatCategoryOptions as $option)
                                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @else
                                        <p class="m-0 font-medium">{{ $line['name'] }}</p>
                                        @if (filled($line['name_ar'] ?? null) || filled($line['name_bn'] ?? null))
                                            <p class="m-0 text-xs text-[var(--app-muted)]">
                                                @if (filled($line['name_ar'] ?? null))<span dir="rtl">{{ $line['name_ar'] }}</span>@endif
                                                @if (filled($line['name_ar'] ?? null) && filled($line['name_bn'] ?? null))<span> | </span>@endif
                                                @if (filled($line['name_bn'] ?? null))<span>{{ $line['name_bn'] }}</span>@endif
                                            </p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="document-qty-stepper" data-testid="line-qty-stepper-{{ $lineId }}">
                                <button type="button" wire:click="adjustLineQuantity({{ $index }}, -1)">−</button>
                                <span>{{ $line['quantity'] }}</span>
                                <button type="button" wire:click="adjustLineQuantity({{ $index }}, 1)">+</button>
                            </div>
                        </td>
                        <td>
                            @if ($isCustom)
                                <select class="form-field__control w-full min-w-[5.5rem]" wire:model.live="lines.{{ $index }}.product_unit_id" aria-label="{{ __('invoices.line_unit') }}">
                                    @foreach ($unitOptions as $unit)
                                        <option value="{{ $unit['id'] }}">{{ $unit['label'] }}</option>
                                    @endforeach
                                </select>
                            @else
                                <span class="text-xs font-medium">{{ $line['unit_label'] ?: 'PCS' }}</span>
                            @endif
                        </td>
                        <td class="min-w-[6.5rem]">
                            <x-field.money step="0.01" min="0" :label="__('invoices.line_price_ex_vat')" name="lines.{{ $index }}.unit_price" wire:model.live="lines.{{ $index }}.unit_price" wrapper-class="!mb-0" />
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
    </div>
@endif

@if ($stockHint !== null)
    <p class="document-stock-hint">
        <x-app.icon name="cube" class="h-4 w-4" aria-hidden="true" />
        {{ __('invoices.stock_hint', ['qty' => number_format($stockHint['quantity'], 0), 'unit' => $stockHint['unit_label']]) }}
    </p>
@endif
</section>
