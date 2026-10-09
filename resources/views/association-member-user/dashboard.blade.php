<x-dashboard-layout title="Association Dashboard" topbar-title="Association Dashboard">
<div data-member-workspace class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    @include('association-member-user.partials.header', ['heading' => 'Association Dashboard', 'description' => 'Welcome, '.session('auth_user.name').'. Keep track of your association’s members and livelihood activities.'])
    @if ($association)
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Association summary">
        @foreach ($counts as $label => $count)
            <a class="am-user-summary" href="{{ [route('member.members'), route('member.applications', ['status' => 'Pending']), route('member.projects'), route('member.trainings')][$loop->index] }}">
                <span class="text-sm font-medium text-slate-600">{{ $label }}</span><strong data-count-up class="mt-2 block text-3xl tabular-nums">{{ number_format($count) }}</strong>
                <span class="mt-2 block text-xs text-slate-500">{{ $label === 'Pending applications' ? 'Awaiting Field Officer review' : 'Non-archived records' }}</span>
            </a>
        @endforeach
    </section>
    @endif
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="quick-links">
        <h2 id="quick-links" class="text-lg font-semibold">Your workspace</h2>
        <nav class="mt-4 flex flex-wrap gap-3" aria-label="Quick navigation">
            @foreach (['information' => 'Association Profile', 'members' => 'Members', 'applications' => 'Applications', 'projects' => 'Projects', 'trainings' => 'Trainings', 'production' => 'Production Records'] as $route => $label)
                <a class="am-user-button am-user-button-secondary" href="{{ route('member.'.$route) }}">{{ $label }} <span aria-hidden="true">→</span></a>
            @endforeach
        </nav>
    </section>
    @if ($association)
    <div class="grid gap-6 xl:grid-cols-2">
        @foreach ([['Recent applications', $applications, 'applications'], ['Current projects', $projects, 'projects'], ['Recent and scheduled trainings', $trainings, 'trainings'], ['Latest production records', $production, 'production']] as [$label, $records, $type])
        <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold">{{ $label }}</h2><a class="text-sm font-semibold text-blue-800 underline underline-offset-4" href="{{ route('member.'.$type) }}">View all<span class="sr-only"> {{ strtolower($label) }}</span></a></div>
            <ul class="mt-3 divide-y divide-slate-100">
                @forelse ($records as $record)
                    <li class="flex flex-wrap items-start justify-between gap-3 py-4">
                        <div class="min-w-0 flex-1">
                        @if ($type === 'applications')
                            <a class="break-words font-semibold text-slate-800 hover:underline" href="{{ route('membership.applications.show', $record) }}">{{ $record->first_name }} {{ $record->last_name }}</a>
                            <p class="mt-1 text-sm text-slate-500">Submitted {{ $record->created_at?->format('M j, Y') ?? 'date not recorded' }}</p>
                        @elseif ($type === 'production')
                            <p class="break-words font-semibold">{{ $record->project_title }}</p><p class="mt-1 text-sm text-slate-500">{{ $record->quarter_name }} · {{ $record->year }}</p>
                            <p class="mt-2 text-sm">Target {{ $record->target_output !== null ? number_format($record->target_output, 2) : 'Not recorded' }} · Actual {{ $record->actual_output !== null ? number_format($record->actual_output, 2) : 'Not recorded' }}</p>
                        @else
                            <a class="break-words font-semibold text-slate-800 hover:underline" href="{{ route('member.'.$type.'.show', $record) }}">{{ $record->title }}</a>
                            @if ($type === 'trainings')<p class="mt-1 text-sm text-slate-500">{{ $record->date_conducted?->format('M j, Y') ?? 'Date not recorded' }} · {{ $record->venue ?: 'Venue not recorded' }}</p>@endif
                        @endif
                        </div>
                        @if (in_array($type, ['applications', 'projects'])) @include('shared.partials.badge', ['label' => $record->status?->status_name]) @endif
                    </li>
                @empty
                    <li class="py-8 text-sm leading-6 text-slate-500">No {{ strtolower($label) }} have been recorded for this association yet.</li>
                @endforelse
            </ul>
        </section>
        @endforeach
    </div>
    @endif
</div>
</x-dashboard-layout>
