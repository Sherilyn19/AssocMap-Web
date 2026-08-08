{{--
    resources/views/components/admin-modal.blade.php

    Generic accessible modal shell for authenticated Admin pages.
    JavaScript owns opening, focus trapping, Escape/backdrop close, and focus return.
--}}
@props([
    'id',
    'title',
    'description' => null,
    'size' => 'lg',
])

@php
    $maxWidth = match ($size) {
        'sm' => 'max-w-md',
        'md' => 'max-w-2xl',
        'xl' => 'max-w-6xl',
        default => 'max-w-4xl',
    };
@endphp

<div
    id="{{ $id }}"
    data-modal
    aria-hidden="true"
    class="fixed inset-0 z-50 hidden"
>
    <div
        data-modal-backdrop
        class="flex min-h-full items-center justify-center bg-slate-950/50 p-4"
    >
        <section
            data-modal-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $id }}-title"
            @if($description) aria-describedby="{{ $id }}-description" @endif
            tabindex="-1"
            class="flex max-h-[90vh] w-full {{ $maxWidth }} flex-col overflow-hidden rounded-2xl
                   border border-slate-200 bg-white shadow-2xl"
        >
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="{{ $id }}-title" class="text-lg font-bold text-slate-900">
                        {{ $title }}
                    </h2>
                    @if($description)
                        <p id="{{ $id }}-description" class="mt-1 text-sm leading-5 text-slate-600">
                            {{ $description }}
                        </p>
                    @endif
                </div>

                <button
                    type="button"
                    data-close-modal
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                           border border-slate-200 text-slate-500 transition hover:bg-slate-50
                           hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400"
                    aria-label="Close dialog"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                {{ $slot }}
            </div>

            @isset($footer)
                <footer class="border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    {{ $footer }}
                </footer>
            @endisset
        </section>
    </div>
</div>