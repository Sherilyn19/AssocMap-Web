{{--
    resources/views/components/logo-header.blade.php
    Component : logo-header
    Purpose   : DA + BFAR agency logos with AssocMAP title.
                Used on Landing (size="lg") and Login (size="sm").
    Props     : $size         — "sm" | "lg"
                $showSubtitle — bool
                $subtitle     — subtitle text
--}}
@props([
    'showSubtitle' => true,
    'subtitle'     => 'DA-BFAR Region VII · SAAD Phase II',
    'size'         => 'sm',
])

<div class="flex flex-col items-center gap-4 text-center">

    {{-- Agency Logos --}}
<div class="flex items-center justify-center">
    <img src="https://res.cloudinary.com/dibojpqg2/image/upload/v1790550205/BFAR-DA-logo_nvr6oa.png"
         alt="DA BFAR Logo"
         class="{{ $size === 'lg' ? 'w-40' : 'w-28' }}">
</div>

    {{-- System title --}}
    <div class="flex flex-col items-center gap-1">
        <h1 class="font-bold tracking-tight text-assocmap-primary
                   {{ $size === 'lg' ? 'text-5xl sm:text-6xl' : 'text-2xl' }}">
            AssocMAP
        </h1>
        @if ($showSubtitle)
            <p class="text-assocmap-secondary leading-snug
                      {{ $size === 'lg' ? 'text-base sm:text-lg' : 'text-xs' }}">
                {{ $subtitle }}
            </p>
        @endif
    </div>

</div>
