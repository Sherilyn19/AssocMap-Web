{{--
    ============================================================
    View    : dashboard/admin.blade.php
    Route   : GET /admin/dashboard
    Role    : System Administrator only
    ============================================================
--}}
<x-dashboard-layout title="Admin Dashboard">

    {{-- ── Welcome Banner ─────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-assocmap-border shadow-card p-8 sm:p-10">

        <div class="flex items-start gap-5">

            {{-- Avatar circle with initials --}}
            <div class="w-16 h-16 rounded-full bg-assocmap-primary flex-shrink-0
                        flex items-center justify-center shadow-sm">
                <span class="text-white text-xl font-bold select-none">
                    {{-- First letter of name --}}
                    {{ strtoupper(substr($user['name'], 0, 1)) }}
                </span>
            </div>

            <div class="flex flex-col gap-1">

                {{-- Role badge --}}
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full
                             text-xs font-semibold bg-green-100 text-assocmap-primary
                             w-fit">
                    System Administrator
                </span>

                {{-- Welcome heading --}}
                <h1 class="text-2xl font-bold text-assocmap-text mt-1">
                    Welcome, {{ $user['name'] }}!
                </h1>

                <p class="text-sm text-assocmap-secondary">
                    You are logged in as the System Administrator of AssocMAP.
                    You have full access to all modules and system settings.
                </p>

            </div>
        </div>

        {{-- Divider --}}
        <hr class="my-7 border-assocmap-border" />

        {{-- Phase 2 placeholder notice --}}
        <div class="rounded-xl border border-dashed border-assocmap-border
                    bg-assocmap-bg px-6 py-5 text-center">
            <p class="text-sm text-assocmap-secondary">
                📊 &nbsp;Dashboard statistics and module shortcuts will be
                available in <strong class="text-assocmap-text">Capstone 2</strong>.
            </p>
            <p class="text-xs text-assocmap-secondary mt-1">
                Modules: User Management · Association Management ·
                GIS Mapping · Monitoring · Reports · Audit Log
            </p>
        </div>

    </div>

    {{-- ── Quick Info Cards ─────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">

        @foreach ([
            ['label' => 'Associations',   'icon' => '🏘️',  'note' => 'Manage all registered fisherfolk associations'],
            ['label' => 'Members',        'icon' => '👥',  'note' => 'View and manage member records'],
            ['label' => 'GIS Map',        'icon' => '🗺️',  'note' => 'Publish and manage map locations'],
        ] as $card)
            <div class="bg-white rounded-xl border border-assocmap-border shadow-card
                        p-5 flex flex-col gap-2">
                <span class="text-2xl">{{ $card['icon'] }}</span>
                <span class="font-semibold text-assocmap-text text-sm">{{ $card['label'] }}</span>
                <span class="text-xs text-assocmap-secondary">{{ $card['note'] }}</span>
                <span class="text-[10px] text-assocmap-secondary/60 mt-1 italic">Available in Capstone 2</span>
            </div>
        @endforeach

    </div>

</x-dashboard-layout>
