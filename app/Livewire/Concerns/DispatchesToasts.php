<?php

namespace App\Livewire\Concerns;

use App\Services\Invoicing\ZHeart\ZHeartApiException;
use App\Support\Invoicing\ZHeartApiUserMessage;

trait DispatchesToasts
{
    protected function toastSuccess(string $message, ?string $title = null): void
    {
        $this->dispatchToast('success', $message, $title);
    }

    protected function toastInfo(string $message, ?string $title = null): void
    {
        $this->dispatchToast('info', $message, $title);
    }

    protected function toastWarning(string $message, ?string $title = null, bool $dedupe = true): void
    {
        $this->dispatchToast('warning', $message, $title, persistent: false, dedupe: $dedupe);
    }

    protected function toastError(string $message, ?string $title = null, bool $persistent = true): void
    {
        $this->dispatchToast('error', $message, $title, persistent: $persistent);
    }

    protected function dispatchToast(
        string $type,
        string $message,
        ?string $title = null,
        bool $persistent = false,
        bool $dedupe = true,
    ): void {
        if ($message === '') {
            return;
        }

        $payload = [
            'type' => $type,
            'message' => $message,
            'title' => $title,
            'persistent' => $persistent || $type === 'error',
            'dedupe' => $dedupe,
        ];

        $this->dispatch(
            'toast',
            type: $type,
            message: $message,
            title: $title,
            persistent: $payload['persistent'],
            dedupe: $dedupe,
        );

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT);
        $this->js("window.showToast && window.showToast({$encoded})");
    }

    protected function toastSigningError(\Throwable $e): void
    {
        $message = $e instanceof ZHeartApiException
            ? ZHeartApiUserMessage::forException($e)
            : $e->getMessage();
        $this->toastError($message);
    }
}
