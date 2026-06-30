{{--
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

    {{-- Agency logos row --}}
    <div class="flex items-center justify-center gap-5">

        {{-- DA Logo --}}
        <div class="flex flex-col items-center gap-1">
            <div class="{{ $size === 'lg' ? 'w-20 h-20' : 'w-14 h-14' }}
                        rounded-full border-2 border-assocmap-border bg-white
                        shadow-sm flex items-center justify-center overflow-hidden"
                 title="Department of Agriculture">
                <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg"
                     class="{{ $size === 'lg' ? 'w-16 h-16' : 'w-11 h-11' }}"
                     aria-label="Department of Agriculture Logo" role="img">
                    <circle cx="40" cy="40" r="38" fill="#1F6E3D"/>
                    <circle cx="40" cy="40" r="32" fill="white"/>
                    <circle cx="40" cy="40" r="26" fill="#1F6E3D"/>
                    <path d="M40 54 L40 30" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M40 36 Q34 30 32 24" stroke="white" stroke-width="2" stroke-linecap="round" fill="none"/>
                    <path d="M40 36 Q46 30 48 24" stroke="white" stroke-width="2" stroke-linecap="round" fill="none"/>
                    <path d="M40 42 Q35 37 33 31" stroke="white" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                    <path d="M40 42 Q45 37 47 31" stroke="white" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                    <ellipse cx="40" cy="50" rx="7" ry="3.5" fill="white" opacity="0.9"/>
                    <path d="M47 50 L52 47 L52 53 Z" fill="white" opacity="0.9"/>
                    <text x="40" y="70" text-anchor="middle"
                          font-family="Arial,sans-serif" font-size="5" font-weight="bold" fill="white">DA</text>
                </svg>
            </div>
            @if($size === 'sm')
                <span class="text-[10px] text-assocmap-secondary font-medium">DA</span>
            @endif
        </div>

        {{-- Separator --}}
        <div class="h-10 w-px bg-assocmap-border"></div>

        {{-- BFAR Logo --}}
        <div class="flex flex-col items-center gap-1">
            <div class="{{ $size === 'lg' ? 'w-20 h-20' : 'w-14 h-14' }}
                        rounded-full border-2 border-assocmap-border bg-white
                        shadow-sm flex items-center justify-center overflow-hidden"
                 title="Bureau of Fisheries and Aquatic Resources">
                <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg"
                     class="{{ $size === 'lg' ? 'w-16 h-16' : 'w-11 h-11' }}"
                     aria-label="Bureau of Fisheries and Aquatic Resources Logo" role="img">
                    <circle cx="40" cy="40" r="38" fill="#0D5C8A"/>
                    <circle cx="40" cy="40" r="32" fill="white"/>
                    <circle cx="40" cy="40" r="26" fill="#0D5C8A"/>
                    <path d="M27 45 Q31 40 35 45 Q39 50 43 45 Q47 40 51 45 Q53 47 55 45"
                          stroke="white" stroke-width="2" fill="none" stroke-linecap="round"/>
                    <path d="M27 50 Q31 45 35 50 Q39 55 43 50 Q47 45 51 50 Q53 52 55 50"
                          stroke="white" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                    <ellipse cx="40" cy="35" rx="8" ry="5" fill="white"/>
                    <path d="M48 35 L55 30 L55 40 Z" fill="white"/>
                    <circle cx="37" cy="34" r="1.2" fill="#0D5C8A"/>
                    <text x="40" y="70" text-anchor="middle"
                          font-family="Arial,sans-serif" font-size="4.5" font-weight="bold" fill="white">BFAR</text>
                </svg>
            </div>
            @if($size === 'sm')
                <span class="text-[10px] text-assocmap-secondary font-medium">BFAR</span>
            @endif
        </div>
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
