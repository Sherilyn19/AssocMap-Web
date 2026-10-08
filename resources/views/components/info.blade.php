@props([
    'label' => 'Additional information',
])

{{-- Native disclosure supports clicking, tapping, and keyboard activation. --}}
<details class="am-info" data-monitoring-info>
    <summary aria-label="{{ $label }}">
        {{-- Heroicons: information-circle outline. --}}
        <svg xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="1.5"
             aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="m11.25 11.25.041-.02a.75.75 0 0 1
                     1.063.852l-.708 2.836a.75.75 0 0 0
                     1.063.852l.041-.02M21 12a9 9 0 1
                     1-18 0 9 9 0 0 1 18 0ZM12 8.25h.008v.008H12V8.25Z"/>
        </svg>
    </summary>

    <div class="am-info__content">
        {{ $slot }}
    </div>
</details>