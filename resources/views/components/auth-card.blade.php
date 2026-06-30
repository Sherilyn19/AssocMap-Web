{{--
    Component : auth-card
    Purpose   : Centered white card container for the Login page.
    Props     : $class — optional extra Tailwind classes
--}}
@props(['class' => ''])

<div class="min-h-screen flex items-center justify-center bg-assocmap-bg px-4 py-10">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-card
                border border-assocmap-border p-8 sm:p-10 {{ $class }}">
        {{ $slot }}
    </div>
</div>
