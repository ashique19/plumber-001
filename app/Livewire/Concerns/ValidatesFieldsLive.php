<?php

namespace App\Livewire\Concerns;

/**
 * Inline Livewire field validation via validateOnly() on property updates.
 */
trait ValidatesFieldsLive
{
    /**
     * @return array<string, mixed>
     */
    abstract protected function liveValidationRules(): array;

    public function updated($property): void
    {
        $this->validateFieldLive($property);
    }

    public function validateLiveField(string $property): void
    {
        $this->validateFieldLive($property);
    }

    protected function validateFieldLive(string $property): void
    {
        $rules = $this->liveValidationRules();

        if (! array_key_exists($property, $rules)) {
            return;
        }

        $this->validateOnly($property, [$property => $rules[$property]]);
    }
}
