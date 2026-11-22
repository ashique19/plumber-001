@props(['name'])

@error($name)
    <span class="form-field__error" role="alert"@if (str_starts_with((string) $name, 'lines.')) data-testid="line-field-error" data-line-error="{{ $name }}"@endif>
        <x-app.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ $message }}</span>
    </span>
@enderror
