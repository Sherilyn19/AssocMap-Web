{{--
    View    : auth/login.blade.php  (Route: GET /login)
    Purpose : AssocMAP login page.
              - Email + Password fields with validation
              - Password visibility toggle (vanilla JS)
              - Login button with loading spinner
              - Contact Support link
              No self-registration. No forgot password.
--}}
<x-app-layout title="Login">

    <x-auth-card>

        {{-- Branding --}}
        <x-logo-header size="sm" subtitle="GIS-Based Program Monitoring System" />

        <hr class="my-6 border-assocmap-border" />

        {{-- Session error alert (e.g. invalid credentials flash) --}}
        @if (session('error'))
            <div role="alert"
                 class="mb-5 flex items-start gap-3 rounded-lg border border-red-200
                        bg-red-50 px-4 py-3 text-sm text-red-700">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="mt-0.5 h-4 w-4 flex-shrink-0"
                     viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{--
            Login Form
            Action  : POST /login  (login.submit route)
            Full auth logic wired in Capstone 2.
        --}}
        <form
            method="POST"
            action="{{ route('login.submit') }}"
            novalidate
            aria-label="AssocMAP Login Form"
            class="flex flex-col gap-5"
        >
            @csrf

            {{-- Email Address --}}
            <x-text-input
                id="email"
                label="Email Address"
                type="email"
                name="email"
                placeholder="you@bfar.gov.ph"
                autocomplete="email"
                :value="old('email')"
                required
            />

            {{-- Password with visibility toggle --}}
            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-sm font-medium text-assocmap-text">
                    Password
                    <span class="text-red-500 ml-0.5" aria-hidden="true">*</span>
                </label>

                <div class="relative">
                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                        class="w-full px-4 py-2.5 pr-11 rounded-lg border text-sm
                               text-assocmap-text placeholder-assocmap-secondary bg-white
                               border-assocmap-border
                               focus:outline-none focus:ring-2 focus:ring-assocmap-primary
                               focus:border-assocmap-primary transition-colors duration-150
                               @error('password') border-red-400 focus:ring-red-400 @enderror"
                    />

                    {{-- Toggle button — aria-pressed updated by JS below --}}
                    <button
                        type="button"
                        id="password-toggle"
                        aria-controls="password"
                        aria-pressed="false"
                        aria-label="Show password"
                        class="absolute inset-y-0 right-0 flex items-center px-3
                               text-assocmap-secondary hover:text-assocmap-primary
                               focus:outline-none focus:text-assocmap-primary
                               transition-colors duration-150"
                    >
                        {{-- Eye (password hidden) --}}
                        <svg id="icon-eye" xmlns="http://www.w3.org/2000/svg"
                             class="w-5 h-5" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>

                        {{-- Eye-off (password visible) --}}
                        <svg id="icon-eye-off" xmlns="http://www.w3.org/2000/svg"
                             class="w-5 h-5 hidden" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
                                     a18.45 18.45 0 0 1 5.06-5.94"/>
                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
                                     a18.5 18.5 0 0 1-2.16 3.19"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>

                @error('password')
                    <p class="text-xs text-red-500 mt-0.5" role="alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- Submit --}}
            <x-primary-button type="submit" class="mt-1">
                <svg id="login-spinner"
                     class="hidden w-4 h-4 mr-2 animate-spin"
                     xmlns="http://www.w3.org/2000/svg"
                     fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                Login
            </x-primary-button>

        </form>

        {{-- Contact Support --}}
        <p class="mt-6 text-center text-xs text-assocmap-secondary">
            Having trouble accessing your account?
            <a href="mailto:support@bfar.gov.ph"
               class="font-medium text-assocmap-primary hover:text-assocmap-hover
                      underline underline-offset-2 transition-colors duration-150">
                Contact Support
            </a>
        </p>

    </x-auth-card>

    {{--
        Password visibility toggle + submit spinner.
        Inline and scoped to this view only (SRP).
        No Alpine, no jQuery — plain DOM API.
    --}}
    <script>
        (function () {
            'use strict';

            var toggle     = document.getElementById('password-toggle');
            var input      = document.getElementById('password');
            var iconEye    = document.getElementById('icon-eye');
            var iconEyeOff = document.getElementById('icon-eye-off');

            if (!toggle || !input) return;

            // Toggle password visibility
            toggle.addEventListener('click', function () {
                var isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                iconEye.classList.toggle('hidden', isHidden);
                iconEyeOff.classList.toggle('hidden', !isHidden);
                toggle.setAttribute('aria-pressed', String(isHidden));
                toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            });

            // Show spinner on form submit — prevents double-click
            var form    = toggle.closest('form');
            var spinner = document.getElementById('login-spinner');
            var btn     = form ? form.querySelector('[type="submit"]') : null;

            if (form && spinner && btn) {
                form.addEventListener('submit', function () {
                    spinner.classList.remove('hidden');
                    btn.disabled = true;
                });
            }
        })();
    </script>

</x-app-layout>
