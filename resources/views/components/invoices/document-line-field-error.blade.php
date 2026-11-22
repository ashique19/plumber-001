@props(['name'])

@error($name)
    <p {{ $attributes->class(['form-field__error mt-1']) }} data-testid="line-field-error" data-line-error="{{ $name }}" role="alert">{{ $message }}</p>
@enderror
