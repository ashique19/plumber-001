<?php

namespace App\Livewire\Concerns;

/**
 * Inline Livewire field validation via validateOnly() after the user has touched a field.
 */
trait ValidatesFieldsLive
{
    /**
     * @var array<string, bool>
     */
    public array $liveTouchedFields = [];

    /**
     * @return array<string, mixed>
     */
    abstract protected function liveValidationRules(): array;

    public function updated($property): void
    {
        if (! $this->shouldValidateLiveOnUpdate($property)) {
            return;
        }

        $this->touchLiveField($property);
        $this->validateFieldLive($property);
    }

    public function touchLiveField(string $property): void
    {
        $this->liveTouchedFields[$property] = true;
    }

    public function resetLiveValidationState(): void
    {
        $this->liveTouchedFields = [];
        $this->resetValidation();
    }

    public function validateLiveField(string $property): void
    {
        $this->validateFieldLive($property);
    }

    protected function validateFieldLive(string $property): void
    {
        if (! ($this->liveTouchedFields[$property] ?? false)) {
            return;
        }

        $rules = $this->liveValidationRules();

        if (! array_key_exists($property, $rules)) {
            return;
        }

        $this->validateOnly($property, [$property => $rules[$property]]);
    }

    protected function shouldValidateLiveOnUpdate(string $property): bool
    {
        $rules = $this->liveValidationRules();

        return array_key_exists($property, $rules);
    }
}