@extends('layouts.member')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

@php
    $association = Auth::user()->association;
    $totalMembers    = $association?->members()->where('is_archived', false)->count() ?? 0;
    $activeProjects  = $association?->projects()->where('is_archived', false)->count() ?? 0;
    $trainings       = $association?->trainings()->where('is_archived', false)->count() ?? 0;
    $pendingApps     = $association?->memberApplications()
                        ->whereHas('status', fn($q) => $q->where('status_name', 'Pending'))
                        ->count() ?? 0;
@endphp

<div style="font-family: 'Space Grotesk', sans-serif;">

    {{-- Welcome Banner --}}
    <div class="rounded-xl p-5 mb-6 flex items-center justify-between"
         style="background: linear-gradient(135deg, #0A3D7A 0%, #1D6FAA 100%);
                border: none;">
        <div>
            <p style="font-size: 13px; color: rgba(255,255,255,0.7);
                      text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                Welcome back
            </p>
            <h2 style="font-size: 22px; font-weight: 700; color: white; margin-bottom: 2px;">
                {{ Auth::user()->name }}
            </h2>
            <p style="font-size: 13px; color: rgba(255,255,255,0.7);">
                {{ $association->name ?? 'Association' }}
            </p>
        </div>
        <div style="opacity: 0.15;">
            <svg xmlns="http://www.w3.org/2000/svg" style="width: 80px; height: 80px;"
                 fill="white" viewBox="0 0 24 24">
                <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10
                         0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3
                         3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356
                         -1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0
                         11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">

        {{-- Total Members --}}
        <div class="bg-white rounded-xl p-5"
             style="border: 1px solid #E5E7EB; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div class="flex items-center justify-between mb-3">
                <p style="font-size: 13px; font-weight: 500; color: #6B7280;">Total Members</p>
                <div style="width: 36px; height: 36px; background: #EFF6FF;
                            border-radius: 8px; display: flex; align-items: center;
                            justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                         viewBox="0 0 24 24" stroke="#0A3D7A" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10
                                 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3
                                 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356
                                 -1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0
                                 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <p style="font-size: 28px; font-weight: 700; color: #111827;">
                {{ $totalMembers }}
            </p>
            <p style="font-size: 12px; color: #6B7280; margin-top: 4px;">
                Active registered members
            </p>
        </div>

        {{-- Active Projects --}}
        <div class="bg-white rounded-xl p-5"
             style="border: 1px solid #E5E7EB; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div class="flex items-center justify-between mb-3">
                <p style="font-size: 13px; font-weight: 500; color: #6B7280;">Active Projects</p>
                <div style="width: 36px; height: 36px; background: #DCFCE7;
                            border-radius: 8px; display: flex; align-items: center;
                            justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                         viewBox="0 0 24 24" stroke="#166534" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4
                                 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
            </div>
            <p style="font-size: 28px; font-weight: 700; color: #111827;">
                {{ $activeProjects }}
            </p>
            <p style="font-size: 12px; color: #6B7280; margin-top: 4px;">
                Ongoing & planned projects
            </p>
        </div>

        {{-- Trainings Conducted --}}
        <div class="bg-white rounded-xl p-5"
             style="border: 1px solid #E5E7EB; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div class="flex items-center justify-between mb-3">
                <p style="font-size: 13px; font-weight: 500; color: #6B7280;">Trainings</p>
                <div style="width: 36px; height: 36px; background: #FEF3C7;
                            border-radius: 8px; display: flex; align-items: center;
                            justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                         viewBox="0 0 24 24" stroke="#92400E" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3
                                 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867
                                 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052
                                 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.966
                                 8.966 0 00-6 2.292m0-14.25v14.25"/>
                    </svg>
                </div>
            </div>
            <p style="font-size: 28px; font-weight: 700; color: #111827;">
                {{ $trainings }}
            </p>
            <p style="font-size: 12px; color: #6B7280; margin-top: 4px;">
                Trainings conducted
            </p>
        </div>

        {{-- Pending Applications --}}
        <div class="bg-white rounded-xl p-5"
             style="border: 1px solid #E5E7EB; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div class="flex items-center justify-between mb-3">
                <p style="font-size: 13px; font-weight: 500; color: #6B7280;">Pending Applications</p>
                <div style="width: 36px; height: 36px; background: #FEE2E2;
                            border-radius: 8px; display: flex; align-items: center;
                            justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                         viewBox="0 0 24 24" stroke="#991B1B" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0
                                 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2
                                 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
            <p style="font-size: 28px; font-weight: 700; color: #111827;">
                {{ $pendingApps }}
            </p>
            <p style="font-size: 12px; color: #6B7280; margin-top: 4px;">
                Awaiting review
            </p>
        </div>

    </div>

    {{-- Quick Links --}}
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">

        <a href="{{ route('member.members.index') }}"
           class="bg-white rounded-xl p-5 flex items-center gap-4"
           style="border: 1px solid #E5E7EB; text-decoration: none;
                  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
                  transition: box-shadow 0.15s;">
            <div style="width: 44px; height: 44px; background: #EFF6FF;
                        border-radius: 10px; display: flex; align-items: center;
                        justify-content: center; flex-shrink: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none"
                     viewBox="0 0 24 24" stroke="#0A3D7A" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10
                             0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3
                             3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356
                             -1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0
                             11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p style="font-size: 14px; font-weight: 600; color: #111827;">View Members</p>
                <p style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                    Browse association members
                </p>
            </div>
        </a>

        <a href="{{ route('member.applications.index') }}"
           class="bg-white rounded-xl p-5 flex items-center gap-4"
           style="border: 1px solid #E5E7EB; text-decoration: none;
                  box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="width: 44px; height: 44px; background: #FEF3C7;
                        border-radius: 10px; display: flex; align-items: center;
                        justify-content: center; flex-shrink: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none"
                     viewBox="0 0 24 24" stroke="#92400E" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2
                             0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002
                             2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div>
                <p style="font-size: 14px; font-weight: 600; color: #111827;">
                    Applications
                </p>
                <p style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                    Review member applications
                </p>
            </div>
        </a>

        <a href="{{ route('member.monitoring.index') }}"
           class="bg-white rounded-xl p-5 flex items-center gap-4"
           style="border: 1px solid #E5E7EB; text-decoration: none;
                  box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="width: 44px; height: 44px; background: #DCFCE7;
                        border-radius: 10px; display: flex; align-items: center;
                        justify-content: center; flex-shrink: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none"
                     viewBox="0 0 24 24" stroke="#166534" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2
                             0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2
                             2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0
                             0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2
                             2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div>
                <p style="font-size: 14px; font-weight: 600; color: #111827;">Monitoring</p>
                <p style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                    Production & income records
                </p>
            </div>
        </a>

    </div>

</div>

@endsection