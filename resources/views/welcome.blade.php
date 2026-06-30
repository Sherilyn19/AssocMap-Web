{{--
    ============================================================
    View    : welcome.blade.php  (Route: GET /)
    Purpose : AssocMAP public landing page.
    ============================================================
--}}
<x-app-layout title="Welcome">

    <main
        class="min-h-screen grid place-items-center px-4 py-12 bg-assocmap-bg"
        role="main"
        aria-label="AssocMAP Landing Page"
    >

        {{-- Landing Card --}}
        <div
            class="w-full max-w-lg bg-white rounded-2xl shadow-card
                   border border-assocmap-border
                   px-8 py-12 sm:px-14 sm:py-16
                   flex flex-col items-center gap-8 text-center"
        >

            {{-- Agency Logos + AssocMAP hero title --}}
            <x-logo-header size="lg" />

            {{-- Visual separator --}}
            <hr class="w-16 border-t-2 border-assocmap-border" />

            {{-- System description — one concise sentence --}}
            <p class="text-sm text-assocmap-secondary leading-relaxed max-w-xs">
                GIS-Based Program Monitoring System for Community Livelihood
                Associations under the SAAD Phase II Program in Cebu Province.
            </p>

            {{-- Single Login CTA — no register, no public map shortcut --}}
            <a
                href="{{ route('login') }}"
                class="inline-flex items-center justify-center w-full max-w-xs
                       px-6 py-3 rounded-lg font-semibold text-sm text-white
                       tracking-wide bg-assocmap-primary hover:bg-assocmap-hover
                       focus:outline-none focus:ring-2 focus:ring-offset-2
                       focus:ring-assocmap-primary active:scale-[0.98]
                       transition-all duration-150"
                aria-label="Login to AssocMAP"
            >
                {{-- Lock icon — inline SVG, no extra dependency --}}
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-4 h-4 mr-2 flex-shrink-0"
                     viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Login
            </a>


        </div>

        {{-- Page footer --}}
        <footer class="mt-8 text-center">
            <p class="text-xs text-assocmap-secondary">
                &copy; {{ date('Y') }} Department of Agriculture — Bureau of Fisheries
                and Aquatic Resources, Region VII.
                <br class="hidden sm:block" />
                Developed by Group Koinonia &middot; Cebu Technological University.
            </p>
        </footer>

    </main>

</x-app-layout>
