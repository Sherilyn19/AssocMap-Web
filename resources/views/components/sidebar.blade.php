{{--
    resources/views/components/sidebar.blade.php

    Data-driven nav list: one array, one @foreach — not 11 hand-copied
    <li> blocks. Adding a module later means adding one row here, nowhere
    else (avoids the Duplicated Code / Shotgun Surgery smells).

    Each item's route is guarded with Route::has() so this never throws
    a RouteNotFoundException while a module's routes haven't been built
    yet — it renders as an inert "#" link until the real
    route is added, then activates automatically.

    Collapse/mobile-drawer state is plain CSS classes (.is-collapsed /
    .is-open) defined in resources/css/app.css via @layer components,
    toggled by resources/js/sidebar.js. Deliberately not using nested
    Tailwind arbitrary-value variants here — they're fragile against the
    JIT class scanner and not worth the risk on a real submission.
--}}

@php
    $navItems = [
        ['route' => 'dashboard.admin',    'label' => 'Dashboard',              'icon' => 'M3 3h7v7H3V3Zm11 0h7v7h-7V3ZM3 14h7v7H3v-7Zm11 0h7v7h-7v-7Z'],
        ['route' => 'users.index',        'label' => 'User Management',        'icon' => 'M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 20c0-3.3 2.7-6 6-6s6 2.7 6 6M15.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM14 20c.3-2.7 2-4.6 4.5-4.9'],
        ['route' => 'areas.index',        'label' => 'Area Management',        'icon' => 'M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21ZM12 12.3a2.3 2.3 0 1 0 0-4.6 2.3 2.3 0 0 0 0 4.6Z'],
        ['route' => 'admin.associations.index', 'label' => 'Association Management', 'icon' => 'M4 21V10l8-6 8 6v11M9 21v-6h6v6'],
        ['route' => 'members.index',      'label' => 'Member Management',      'icon' => 'M12 11.4a3.4 3.4 0 1 0 0-6.8 3.4 3.4 0 0 0 0 6.8ZM4.5 20c0-4.1 3.4-7 7.5-7s7.5 2.9 7.5 7'],
        ['route' => 'projects.index',     'label' => 'Project Management',     'icon' => 'M4 7h16v13H4V7Zm4 0V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7'],
        ['route' => 'gis.index',          'label' => 'GIS Mapping',            'icon' => 'M9 4 3 6.5v13L9 17l6 2.5 6-2.5v-13L15 6.5 9 4ZM9 4v13M15 6.5v13'],
        ['route' => 'trainings.index',    'label' => 'Training Management',    'icon' => 'M4 6.5 12 3l8 3.5-8 3.5-8-3.5ZM7 10.5V16c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5v-5.5'],
        ['route' => 'monitoring.index',   'label' => 'Monitoring Module',      'icon' => 'M3 17l5-6 4 4 8-9M15 6h5v5'],
        ['route' => 'reports.index',      'label' => 'Reports & Analytics',    'icon' => 'M4 10h4v10H4V10Zm6-4h4v14h-4V6Zm6 7h4v7h-4v-7Z'],
        ['route' => 'audit-logs.index',   'label' => 'Audit Log',              'icon' => 'M7 3h10a1 1 0 0 1 1 1v16l-3-2-3 2-3-2-3 2V4a1 1 0 0 1 1-1ZM9 8h6M9 11.5h6'],
    ];
@endphp

<aside id="sidebar" aria-label="Primary navigation" class="am-sidebar">
    {{-- Brand --}}
    <div class="am-sidebar__brand">
        <div class="am-sidebar__mark">
            <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                <circle cx="20" cy="20" r="19" fill="#fff"/>
                <path d="M20 8c-6 4-9.5 9-9.5 13.5A9.5 9.5 0 0 0 20 31a9.5 9.5 0 0 0 9.5-9.5C29.5 17 26 12 20 8Z" fill="#2f9e52"/>
                <path d="M20 8c-3.6 2.4-6.2 5.4-7.8 8.8C15 15.4 18 14 20 11.5c2 2.5 5 3.9 7.8 5.3C26.2 13.4 23.6 10.4 20 8Z" fill="#e07b1a"/>
                <circle cx="20" cy="22.5" r="3.4" fill="#0a3d7a"/>
            </svg>
        </div>
        <div class="am-sidebar__brand-text">
            <p class="am-sidebar__brand-name">AssocMap</p>
            <p class="am-sidebar__brand-role">{{ session('auth_user')['role_name'] ?? 'System Administrator' }}</p>
        </div>
        <button type="button" id="sidebarCollapseBtn" aria-label="Collapse sidebar" class="am-sidebar__collapse-btn">
            <svg id="sidebarCollapseIcon" width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="am-sidebar__nav">
        @foreach ($navItems as $item)
            @php
                $hasRoute = Route::has($item['route']);
                $routeParts = explode('.', $item['route']);
                array_pop($routeParts);
                $isActive = $hasRoute && request()->routeIs(implode('.', $routeParts) . '.*');
                $href = $hasRoute ? route($item['route']) : '#';
            @endphp
            <a href="{{ $href }}" data-tip="{{ $item['label'] }}" class="am-nav-link {{ $isActive ? 'is-active' : '' }}">
                <svg class="am-nav-link__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="{{ $item['icon'] }}" />
                </svg>
                <span class="am-nav-link__label">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="am-sidebar__footer">BFAR Region VII — Cebu</div>
</aside>

{{-- Mobile drawer overlay --}}
<div id="sidebarOverlay" class="am-sidebar-overlay"></div>
