<x-dashboard-layout title="Admin Dashboard" topbar-title="Dashboard">
    <div data-admin-dashboard class="space-y-6">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-widest text-assocmap-primary">BFAR SAAD Phase II</p>
                <h1 class="mt-2 break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Welcome, {{ $user['name'] ?? 'Administrator' }}.</h1>
                <p class="mt-2 text-sm text-slate-600">Your overview of associations, members, and project implementation.</p>
            </div>
            <a href="{{ route('dashboard.admin') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Refresh overview</a>
        </header>

        @if (session('error'))
            <p role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ session('error') }}</p>
        @endif

        @if ($unavailable ?? false)
            <section role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                <h2 class="font-semibold text-amber-950">Dashboard data is temporarily unavailable</h2>
                <p class="mt-2 text-sm text-amber-900">We couldn't load the latest records. Refresh the overview to try again.</p>
            </section>
        @else
            @php
                $pendingUrl = route('members.applications.index', $pendingStatusId ? ['status_id' => $pendingStatusId, 'sort' => 'submitted_asc'] : []);
                $cards = [
                    ['label' => 'Current associations', 'value' => $counts['associations'], 'note' => 'Non-archived association records', 'url' => route('admin.associations.index', ['archive_state' => 'current']), 'icon' => 'M3 21V9l9-6 9 6v12M9 21v-7h6v7M7 10h1m8 0h1'],
                    ['label' => 'Current members', 'value' => $counts['members'], 'note' => 'Non-archived official members', 'url' => route('members.index', ['record_state' => 'current']), 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
                    ['label' => 'Current projects', 'value' => $counts['projects'], 'note' => 'Across all implementation statuses', 'url' => route('projects.index'), 'icon' => 'M3 7h18v14H3zM8 7V3h8v4M3 12h18M10 12v3h4v-3'],
                    ['label' => 'Pending applications', 'value' => $counts['pending'], 'note' => 'Awaiting representative review', 'url' => $pendingUrl, 'icon' => 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0'],
                ];
            @endphp
            <section aria-label="Dashboard summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($cards as $card)
                    <a href="{{ $card['url'] }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-assocmap-primary hover:shadow-md">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-600">{{ $card['label'] }}</span>
                            <svg aria-hidden="true" class="h-9 w-9 shrink-0 rounded-lg bg-assocmap-bg p-2 text-assocmap-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $card['icon'] }}"/></svg>
                        </div>
                        <p class="mt-4 text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($card['value']) }}</p>
                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ $card['note'] }}</p>
                        <p class="mt-4 text-xs font-semibold text-assocmap-primary">View records <span aria-hidden="true">&rarr;</span></p>
                    </a>
                @endforeach
            </section>

            <div class="grid items-start gap-6 xl:grid-cols-3">
                <section aria-labelledby="recent-associations-heading" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                        <div><h2 id="recent-associations-heading" class="font-semibold text-slate-900">Recent associations</h2><p class="mt-1 text-xs text-slate-500">The five newest current records.</p></div>
                        <a href="{{ route('admin.associations.index', ['sort' => 'created_desc']) }}" class="text-sm font-semibold text-assocmap-primary hover:underline">View all <span aria-hidden="true">&rarr;</span></a>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($recentAssociations as $association)
                            <li><a href="{{ route('admin.associations.show', $association->id) }}" class="flex flex-col gap-2 px-5 py-4 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0"><p class="break-words text-sm font-semibold text-slate-800">{{ $association->name }}</p><p class="mt-1 break-words text-xs text-slate-500">{{ $association->municipality ?? 'Municipality not assigned' }}</p></div>
                                <span class="w-fit shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ $association->status_name ?? 'Status not set' }}</span>
                            </a></li>
                        @empty
                            <li class="px-5 py-10 text-center text-sm text-slate-500">No current associations yet. Add an association from Association Management to get started.</li>
                        @endforelse
                    </ul>
                </section>

                <section aria-labelledby="projects-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 id="projects-heading" class="font-semibold text-slate-900">Project implementation</h2>
                    <p class="mt-1 text-xs text-slate-500">Status of {{ number_format($counts['projects']) }} current projects.</p>
                    <div class="mt-5 space-y-5">
                        @foreach ($projectStatuses as $label => $total)
                            <div>
                                <div class="mb-2 flex justify-between gap-3 text-sm"><span class="text-slate-600">{{ $label }}</span><span class="font-semibold tabular-nums text-slate-900">{{ number_format($total) }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full {{ $label === 'Completed' ? 'bg-assocmap-primary' : ($label === 'Ongoing' ? 'bg-blue-600' : 'bg-slate-400') }}" style="width: {{ $counts['projects'] ? round($total / $counts['projects'] * 100, 2) : 0 }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                    @if ($counts['projects'] === 0)
                        <p class="mt-4 text-sm text-slate-500">No current projects to display.</p>
                    @endif
                    <a href="{{ route('projects.index') }}" class="mt-6 inline-flex min-h-11 items-center text-sm font-semibold text-assocmap-primary hover:underline">Manage projects <span aria-hidden="true" class="ml-2">&rarr;</span></a>
                </section>

                <section aria-labelledby="applications-heading" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                        <div><h2 id="applications-heading" class="font-semibold text-slate-900">Awaiting representative review</h2><p class="mt-1 text-xs leading-5 text-slate-500">Five oldest pending applications. Administrators can view application details.</p></div>
                        <a href="{{ $pendingUrl }}" class="text-sm font-semibold text-assocmap-primary hover:underline">View pending <span aria-hidden="true">&rarr;</span></a>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($pendingApplications as $application)
                            <li><a href="{{ route('members.applications.show', $application->id) }}" class="flex flex-col gap-2 px-5 py-4 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0"><p class="break-words text-sm font-semibold text-slate-800">{{ $application->first_name }} {{ $application->last_name }}</p><p class="mt-1 break-words text-xs text-slate-500">{{ $application->association_name ?? 'Association unavailable' }}{{ $application->association_archived ? ' · Archived association' : '' }}</p></div>
                                <span class="shrink-0 text-xs text-slate-500">{{ $application->created_at ? 'Submitted '.\Illuminate\Support\Carbon::parse($application->created_at)->format('M j, Y') : 'Submission date unavailable' }}</span>
                            </a></li>
                        @empty
                            <li class="px-5 py-10 text-center text-sm text-slate-500">No pending applications to display.</li>
                        @endforelse
                    </ul>
                </section>

                <section aria-labelledby="coverage-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 id="coverage-heading" class="font-semibold text-slate-900">Association coverage</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Top five municipalities by current associations.</p>
                    <ul class="mt-4 divide-y divide-slate-100">
                        @forelse ($coverage as $area)
                            <li><a href="{{ route('admin.associations.index', ['area_unit_id' => $area->id, 'archive_state' => 'current']) }}" class="flex items-center justify-between gap-4 rounded py-3 text-sm hover:text-assocmap-primary"><span class="min-w-0 break-words">{{ $area->name }}</span><span class="shrink-0 rounded-lg bg-assocmap-bg px-2.5 py-1 font-semibold tabular-nums text-assocmap-primary">{{ number_format($area->total) }}</span></a></li>
                        @empty
                            <li class="py-5 text-sm text-slate-500">No association coverage to display yet.</li>
                        @endforelse
                    </ul>
                    <a href="{{ route('areas.index') }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-assocmap-primary hover:underline">Manage areas <span aria-hidden="true" class="ml-2">&rarr;</span></a>
                </section>
            </div>
            <p class="text-xs leading-5 text-slate-500">Loaded {{ $generatedAt->format('M j, Y, g:i a T') }}. Counts reflect each register; archived associations may still have member or application records.</p>
        @endif

        <section aria-labelledby="quick-access-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="quick-access-heading" class="font-semibold text-slate-900">Quick access</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([['route' => 'admin.associations.index', 'label' => 'Manage associations'], ['route' => 'members.index', 'label' => 'Manage members'], ['route' => 'projects.create', 'label' => 'Create a project'], ['route' => 'users.index', 'label' => 'Manage user access']] as $shortcut)
                    <a href="{{ route($shortcut['route']) }}" class="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 hover:border-assocmap-primary hover:bg-assocmap-bg">{{ $shortcut['label'] }} <span aria-hidden="true">&rarr;</span></a>
                @endforeach
            </div>
        </section>
    </div>
</x-dashboard-layout>
