<?php

namespace App\Livewire\Concerns;

use App\Models\ProductSize;
use App\Models\ProductUnit;
use App\Support\Catalog\CatalogUnitsSizesSchema;
use App\Support\Catalog\UblUnitCode;
use Illuminate\Validation\Rule;

trait ManagesCatalogUnitsAndSizes
{
    public bool $showUnitForm = false;

    public ?int $editingUnitId = null;

    public string $unitNameEn = '';

    public string $unitNameAr = '';

    public string $unitNameBn = '';

    public string $unitCode = '';

    public bool $unitActive = true;

    public bool $showSizeForm = false;

    public ?int $editingSizeId = null;

    public string $sizeName = '';

    public string $sizeMeasurementType = '';

    public bool $sizeActive = true;

    public function startUnit(?int $id = null): void
    {
        $this->authorizeCatalogWrite();
        $this->resetUnitForm();
        $this->showUnitForm = true;

        if ($id !== null) {
            $unit = ProductUnit::query()->findOrFail($id);
            $this->editingUnitId = $unit->id;
            $this->unitNameEn = $unit->name_en;
            $this->unitNameAr = $unit->name_ar;
            $this->unitNameBn = $unit->name_bn;
            $this->unitCode = $unit->code;
            $this->unitActive = (bool) $unit->is_active;
        }
    }

    public function saveUnit(): void
    {
        $this->authorizeCatalogWrite();

        $validated = $this->validate([
            'unitNameEn' => ['required', 'string', 'max:120'],
            'unitNameAr' => ['required', 'string', 'max:120'],
            'unitNameBn' => ['required', 'string', 'max:120'],
            'unitCode' => [
                'required',
                'string',
                'max:16',
                Rule::unique('product_units', 'code')->ignore($this->editingUnitId),
            ],
            'unitActive' => ['boolean'],
        ]);

        $code = strtoupper(trim($validated['unitCode']));
        $payload = [
            'name_en' => $validated['unitNameEn'],
            'name_ar' => $validated['unitNameAr'],
            'name_bn' => $validated['unitNameBn'],
            'code' => $code,
            'ubl_unit_code' => UblUnitCode::forCatalogCode($code),
            'is_active' => $validated['unitActive'],
        ];

        if ($this->editingUnitId) {
            ProductUnit::query()->findOrFail($this->editingUnitId)->update($payload);
            $this->toastSuccess(__('catalog.saved_unit'));
        } else {
            ProductUnit::query()->create($payload);
            $this->toastSuccess(__('catalog.created_unit'));
        }

        $this->showUnitForm = false;
        $this->resetUnitForm();
    }

    public function startSize(?int $id = null): void
    {
        $this->authorizeCatalogWrite();
        $this->resetSizeForm();
        $this->showSizeForm = true;

        if ($id !== null) {
            $size = ProductSize::query()->findOrFail($id);
            $this->editingSizeId = $size->id;
            $this->sizeName = $size->name;
            $this->sizeMeasurementType = (string) ($size->measurement_type ?? '');
            $this->sizeActive = (bool) $size->is_active;
        }
    }

    public function saveSize(): void
    {
        $this->authorizeCatalogWrite();

        $validated = $this->validate([
            'sizeName' => ['required', 'string', 'max:120'],
            'sizeMeasurementType' => ['nullable', 'string', 'max:80'],
            'sizeActive' => ['boolean'],
        ]);

        $payload = [
            'name' => $validated['sizeName'],
            'measurement_type' => $validated['sizeMeasurementType'] ?: null,
            'is_active' => $validated['sizeActive'],
        ];

        if ($this->editingSizeId) {
            ProductSize::query()->findOrFail($this->editingSizeId)->update($payload);
            $this->toastSuccess(__('catalog.saved_size'));
        } else {
            ProductSize::query()->create($payload);
            $this->toastSuccess(__('catalog.created_size'));
        }

        $this->showSizeForm = false;
        $this->resetSizeForm();
    }

    public function catalogUnitsSizesReady(): bool
    {
        return CatalogUnitsSizesSchema::ready();
    }

    private function resetUnitForm(): void
    {
        $this->editingUnitId = null;
        $this->unitNameEn = '';
        $this->unitNameAr = '';
        $this->unitNameBn = '';
        $this->unitCode = '';
        $this->unitActive = true;
    }

    private function resetSizeForm(): void
    {
        $this->editingSizeId = null;
        $this->sizeName = '';
        $this->sizeMeasurementType = '';
        $this->sizeActive = true;
    }
}