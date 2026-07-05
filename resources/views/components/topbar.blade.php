{{--
    resources/views/components/topbar.blade.php

    Accepts a $title prop from the parent layout, e.g.
    <x-topbar title="Dashboard" />
--}}

@props(['title' => ''])

<header class="am-topbar">
    <div class="am-topbar__left">
        <button type="button" id="sidebarMenuBtn" aria-label="Toggle sidebar" aria-expanded="false" class="am-icon-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
        </button>
        <h1 class="am-topbar__title">{{ $title }}</h1>
    </div>

    <div class="am-topbar__right">
        @php $authUser = session('auth_user'); @endphp
        <div class="am-topbar__user">
            <div class="am-topbar__user-info">
                <span class="am-topbar__user-name">{{ $authUser['name'] ?? 'Program Administrator' }}</span>
                <span class="am-topbar__user-role">{{ $authUser['role_name'] ?? 'System Administrator' }}</span>
            </div>
            <div class="am-avatar">{{ strtoupper(substr($authUser['name'] ?? 'PA', 0, 2)) }}</div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="am-logout-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M8 5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h3"/>
                </svg>
                Logout
            </button>
        </form>
    </div>
</header>
