{{--
    Component : text-input
    Purpose   : Labeled form input with inline error display.
    Props     : $id, $label, $type, $name, $value,
                $required, $placeholder, $autocomplete
--}}
@props([
    'id'           => '',
    'label'        => '',
    'type'         => 'text',
    'name'         => '',
    'value'        => '',
    'required'     => false,
    'placeholder'  => '',
    'autocomplete' => 'off',
])

<div class="flex flex-col gap-1.5">

    @if ($label)
        <label for="{{ $id }}" class="text-sm font-medium text-assocmap-text">
            {{ $label }}
            @if ($required)
                <span class="text-red-500 ml-0.5" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete }}"
        @if ($required) required @endif
        {{ $attributes->merge([
            'class' => 'w-full px-4 py-2.5 rounded-lg border text-sm
                        text-assocmap-text placeholder-assocmap-secondary bg-white
                        border-assocmap-border
                        focus:outline-none focus:ring-2 focus:ring-assocmap-primary
                        focus:border-assocmap-primary transition-colors duration-150
                        ' . ($errors->has($name) ? 'border-red-400 focus:ring-red-400' : ''),
        ]) }}
    />

    @error($name)
        <p class="text-xs text-red-500 mt-0.5" role="alert">{{ $message }}</p>
    @enderror

</div>
