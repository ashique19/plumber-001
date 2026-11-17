@props([
    'url',
    'label',
    'returnQuery' => null,
    'testId' => 'invoice-zatca-resubmit',
])

<form
    method="post"
    action="{{ $url }}"
    class="invoice-zatca-resubmit-form"
    data-testid="{{ $testId }}"
    onsubmit="const btn=this.querySelector('button[type=submit]');if(btn){btn.disabled=true;btn.setAttribute('aria-busy','true');}"
>
    @csrf
    @if (filled($returnQuery))
        <input type="hidden" name="return" value="{{ $returnQuery }}">
    @endif
    <button type="submit" class="tap-action-primary min-h-10 px-3 text-sm font-semibold" data-testid="{{ $testId }}-submit">
        {{ $label }}
    </button>
</form>