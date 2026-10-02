{{-- Shared navigation uses existing module routes and shell state classes. --}}

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
        ['route' => 'admin.audit-logs.index', 'label' => 'Audit Log',           'icon' => 'M7 3h10a1 1 0 0 1 1 1v16l-3-2-3 2-3-2-3 2V4a1 1 0 0 1 1-1ZM9 8h6M9 11.5h6'],
    ];
    $moduleIcons = array_column($navItems, 'icon', 'route');
    // These roles use scoped membership routes; admin links must not be their navigation.
    if (session('auth_user.role_name') !== 'System Administrator') {
        $dashboard = session('auth_user.role_name') === 'Field Officer' ? 'dashboard.officer' : 'dashboard.member';
        $navItems = [
            ['route' => $dashboard, 'label' => 'Dashboard', 'icon' => $navItems[0]['icon']],
            ['route' => 'membership.index', 'label' => 'Member Management', 'icon' => $navItems[4]['icon']],
            ['route' => session('auth_user.role_name') === 'Field Officer' ? 'gis.officer.index' : 'gis.viewer', 'label' => 'GIS Mapping', 'icon' => $navItems[6]['icon']],
        ];
        if (session('auth_user.role_name') === 'Field Officer') {
            $navItems = [
                $navItems[0],
                ['route' => 'officer.areas.index', 'label' => 'My Areas', 'icon' => $moduleIcons['admin.associations.index']],
                ['route' => 'officer.associations.index', 'label' => 'My Associations', 'icon' => $moduleIcons['admin.associations.index']],
                ['route' => 'membership.index', 'label' => 'Members and Applications (read-only)', 'icon' => $navItems[1]['icon']],
                ['route' => 'officer.projects.index', 'label' => 'Projects and Delivery', 'icon' => $moduleIcons['projects.index']],
                ['route' => 'officer.trainings.index', 'label' => 'Training Records', 'icon' => $moduleIcons['trainings.index']],
                ['route' => 'monitoring.index', 'label' => 'Monitoring', 'icon' => 'M3 17l5-6 4 4 8-9M15 6h5v5'],
                $navItems[2],
                ['route' => 'officer.reports.index', 'label' => 'My Reports', 'icon' => $moduleIcons['reports.index']],
            ];
        }
    }
    if (session('auth_user.role_name') === 'Association Member') {
        $navItems = [];
        foreach ([
            'dashboard.member' => ['Dashboard', 'dashboard.admin'],
            'member.information' => ['Association Profile', 'admin.associations.index'],
            'member.members' => ['Members', 'members.index'],
            'member.applications' => ['Applications', 'members.index'],
            'member.projects' => ['Projects', 'projects.index'],
            'member.trainings' => ['Trainings', 'trainings.index'],
            'member.production' => ['Production Records', 'monitoring.index'],
            'gis.viewer' => ['Association Map', 'gis.index'],
        ] as $route => [$label, $icon]) {
            $navItems[] = ['route' => $route, 'label' => $label, 'icon' => $moduleIcons[$icon]];
        }
    }
@endphp

<aside id="sidebar" aria-label="Primary navigation" class="am-sidebar">
    {{-- Brand --}}
    <div class="am-sidebar__brand">
        <div class="am-sidebar__mark">
    <img src="https://res.cloudinary.com/dibojpqg2/image/upload/v1790480817/AssocMap-Logo-Without_title_muyzbt.png"
         alt="AssocMap Logo"
         class="w-10 h-10">
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
        <button type="button" id="sidebarCloseBtn" aria-label="Close navigation" class="am-sidebar__close-btn">
            <span aria-hidden="true">✕</span>
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
                if (session('auth_user.role_name') === 'Association Member') {
                    $isActive = request()->routeIs($item['route'], $item['route'].'.*');
                    if ($item['route'] === 'member.members') $isActive = $isActive || request()->routeIs('membership.members.*');
                    if ($item['route'] === 'member.applications') $isActive = $isActive || request()->routeIs('membership.applications.*');
                }
                $href = $hasRoute ? route($item['route']) : '#';
            @endphp
            @continue(! $hasRoute)
            <a href="{{ $href }}" aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}" @if($isActive) aria-current="page" @endif class="am-nav-link {{ $isActive ? 'is-active' : '' }}">
                <svg aria-hidden="true" class="am-nav-link__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
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

