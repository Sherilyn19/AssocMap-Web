{{--
    Component : primary-button
    Purpose   : Reusable green CTA button (Landing + Login pages).
    Props     : $type  — HTML button type (default: "button")
                $class — optional extra classes
--}}
@props(['type' => 'button', 'class' => ''])

<button
    type="{{ $type }}"
    {{ $attributes->merge([
        'class' => 'inline-flex items-center justify-center w-full
                    px-6 py-3 rounded-lg font-semibold text-sm
                    text-white tracking-wide
                    bg-assocmap-primary hover:bg-assocmap-hover
                    focus:outline-none focus:ring-2 focus:ring-offset-2
                    focus:ring-assocmap-primary active:scale-[0.98]
                    transition-all duration-150
                    disabled:opacity-60 disabled:cursor-not-allowed ' . $class,
    ]) }}
>
    {{ $slot }}
</button>
