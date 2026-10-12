{{--
    resources/views/components/topbar.blade.php

    Accepts a $title prop from the parent layout, e.g.
    <x-topbar title="Dashboard" />
--}}

@props(['title' => '', 'contextual' => false, 'workspace' => false])

<header class="am-topbar">
    <div class="am-topbar__left">
        {{-- Hamburger button - MOBILE ONLY --}}
        <button type="button" id="sidebarMenuBtn" aria-label="Toggle sidebar" aria-expanded="false" aria-controls="sidebar" class="am-icon-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
        </button>
        
        {{-- Contextual pages own their main heading; other modules retain their existing heading. --}}
        @if($workspace)
            {{-- Keep workspace orientation visible while JS reveals only the module
                 trail on scroll. Both trail parts share one transition to avoid an orphan slash. --}}
            <div class="am-workspace-heading">
                <span class="am-workspace-label">{{ match(session('auth_user.role_name')) { 'Association Member' => 'Member Workspace', 'Field Officer' => 'Field Officer Workspace', default => 'Admin Workspace' } }}</span>
                <div data-management-title-trail class="management-context-title is-concealed am-workspace-trail" aria-hidden="true">
                    <span aria-hidden="true" class="am-workspace-divider">/</span>
                    <p class="am-topbar__title" title="{{ $title }}">{{ $title }}</p>
                </div>
            </div>
        @elseif($contextual)
            <p class="am-topbar__title">{{ $title }}</p>
        @else
            <h1 class="am-topbar__title">{{ $title }}</h1>
        @endif
    </div>

    <div class="am-topbar__right">
        @php
        // The bell is available to authenticated workspace users. A failed
        // page query may leave a PostgreSQL transaction unusable, so the
        // shared top bar must still render without querying notifications.
        $unreadNotifications = 0;
        try {
            $notificationUser = app(\App\Services\SessionUserResolver::class)->resolve(request());
            $unreadNotifications = $notificationUser?->unreadNotifications()->count() ?? 0;
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);
        }
    @endphp

    <div class="am-notification-wrap" data-notification-root>
        <button
            type="button"
            class="am-notification-button"
            data-notification-toggle
            aria-expanded="false"
            aria-controls="am-notification-panel"
            aria-label="Notifications{{ $unreadNotifications > 0 ? ', '.$unreadNotifications.' unread' : '' }}"
        >
            {{-- Heroicons outline bell; the parent button provides its accessible name. --}}
            <svg
                class="am-notification-icon"
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                aria-hidden="true"
                focusable="false"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                />
            </svg>

            <span
                class="am-notification-count {{ $unreadNotifications === 0 ? 'hidden' : '' }}"
                data-notification-count
            >
                {{ $unreadNotifications }}
            </span>
        </button>

        <section
            id="am-notification-panel"
            class="am-notification-panel"
            data-notification-panel
            hidden
            aria-label="Notifications"
        >
            <div class="am-notification-header">
                <h2>Notifications</h2>

                <button type="button" data-notification-read-all>
                    Mark all as read
                </button>
            </div>

            <div data-notification-list>
                <p class="am-notification-empty">
                    Loading notifications...
                </p>
            </div>
        </section>
    </div>
        @php $authUser = session('auth_user'); @endphp
        @if(session('auth_user.role_name') === 'Association Member')
        <a href="{{ route('member.information') }}" class="am-topbar__user am-member-profile-link" aria-label="View association profile: {{ $authUser['name'] ?? 'My association' }}" title="View association profile">
            <div class="am-topbar__user-info">
                <span data-profile-wave class="am-topbar__user-name" aria-hidden="true">{{ $authUser['name'] ?? 'My association' }}</span>
            </div>
            <div class="am-avatar" aria-hidden="true">{{ strtoupper(substr($authUser['name'] ?? 'MA', 0, 2)) }}</div>
        </a>
        @else
        <div class="am-topbar__user">
            <div class="am-topbar__user-info">
                <span class="am-topbar__user-name">{{ $authUser['name'] ?? 'Program Administrator' }}</span>
            </div>
            <div class="am-avatar">{{ strtoupper(substr($authUser['name'] ?? 'PA', 0, 2)) }}</div>
        </div>
        @endif

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
