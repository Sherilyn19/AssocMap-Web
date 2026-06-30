{{--
    ============================================================
    Component : dashboard-layout
    Purpose   : Shared shell for all three dashboard views.
                Provides the top navigation bar with:
                  - AssocMAP branding
                  - Logged-in user name + role badge
                  - Logout button (POST /logout)
                The $slot renders the page-specific content.
    Props     : $title — page title for <title> tag
    ============================================================
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ $title ?? 'Dashboard' }} — AssocMAP</title>

    {{-- Inter font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-inter antialiased bg-assocmap-bg text-assocmap-text min-h-screen">

    {{-- ── Top Navigation Bar ─────────────────────────────── --}}
    <header class="bg-assocmap-primary shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                {{-- Brand --}}
                <div class="flex items-center gap-3">
                    <span class="text-white font-bold text-xl tracking-tight">
                        AssocMAP
                    </span>
                    <span class="hidden sm:block text-green-200 text-xs font-medium">
                        DA-BFAR Region VII · SAAD Phase II
                    </span>
                </div>

                {{-- User info + logout --}}
                <div class="flex items-center gap-4">

                    {{-- Role badge + name --}}
                    <div class="hidden sm:flex flex-col items-end">
                        <span class="text-white text-sm font-semibold leading-tight">
                            {{ session('auth_user.name') }}
                        </span>
                        <span class="text-green-200 text-xs leading-tight">
                            {{ session('auth_user.role_name') }}
                        </span>
                    </div>

                    {{--
                        Logout form — POST to /logout.
                        Must be a form (not a link) to include CSRF token.
                    --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5
                                   rounded-lg text-xs font-semibold text-assocmap-primary
                                   bg-white hover:bg-green-50
                                   focus:outline-none focus:ring-2 focus:ring-white
                                   active:scale-95 transition-all duration-150"
                            aria-label="Logout"
                        >
                            {{-- Logout icon --}}
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="w-3.5 h-3.5" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            Logout
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </header>

    {{-- ── Main Content ─────────────────────────────────────── --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        {{ $slot }}
    </main>

    {{-- ── Footer ───────────────────────────────────────────── --}}
    <footer class="border-t border-assocmap-border mt-auto py-4">
        <p class="text-center text-xs text-assocmap-secondary">
            &copy; {{ date('Y') }} DA-BFAR Region VII &mdash; AssocMAP v1.0
        </p>
    </footer>

</body>
</html>
