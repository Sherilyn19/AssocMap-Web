<x-dashboard-layout title="Field Officer Dashboard">
<div class="space-y-6">
    <header><span class="am-officer-eyebrow">BFAR SAAD Phase II</span><h1 class="text-2xl font-bold">Welcome, {{ $actor->name }}</h1><p class="mt-2 text-sm text-slate-600">{{ $actor->email }} · Field Officer · Active</p><p class="mt-2 text-sm text-slate-600">Operational records for your currently assigned associations.</p></header>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Summary">
    @foreach ($counts as $label => $count)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm text-slate-600">{{ $label }}</h2><p class="mt-3 text-3xl font-bold">{{ number_format($count) }}</p></div>
    @endforeach
    </section>
    <nav class="flex flex-wrap gap-4" aria-label="Quick actions"><a class="inline-flex items-center rounded-lg bg-assocmap-primary px-4 py-3 text-sm font-semibold text-white" href="{{ route('monitoring.create', 'production') }}">Record production</a><a class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-semibold" href="{{ route('gis.officer.index') }}">GIS Mapping</a><a class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-semibold" href="{{ route('officer.reports.index') }}">My Reports</a></nav>
    <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="mb-4 text-lg font-semibold">My Associations</h2>@include('field-officer-user.associations.table')<a class="mt-4 inline-block text-sm font-semibold underline" href="{{ route('officer.associations.index') }}">View all assigned associations</a></section>
    <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="mb-4 text-lg font-semibold">Recent Production Monitoring</h2><div class="overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable records"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">Association</th><th class="p-3">Project</th><th class="p-3">Quarter / Year</th><th class="p-3">Achievement</th></tr></thead><tbody>
    @forelse ($recent as $record)
        <tr class="border-b"><td class="p-3">{{ $record->association_name }}</td><td class="p-3">{{ $record->project_title }}</td><td class="p-3">{{ $record->quarter_name }} / {{ $record->year }}</td><td class="p-3">{{ \App\Support\MonitoringProgress::label($record->target_output, $record->actual_output) }}</td></tr>
    @empty
        <tr><td colspan="4" class="p-4 text-slate-500">No monitoring records found.</td></tr>
    @endforelse
    </tbody></table></div></section>
</div>
</x-dashboard-layout>
