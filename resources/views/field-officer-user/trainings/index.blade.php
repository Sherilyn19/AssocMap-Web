<x-dashboard-layout title="Training Records">
<div class="space-y-6"><header><span class="am-officer-eyebrow">BFAR SAAD Phase II</span><h1>Training Records</h1><p class="mt-1 text-sm leading-6 text-slate-600">View training information and manage attendance for assigned associations.</p></header>
@include('shared.membership.partials.feedback')
@if ($association)
<div class="rounded-xl border border-assocmap-border bg-white p-4"><p class="text-sm text-slate-600">Association context</p><p class="font-semibold">{{ $association->name }}</p><a class="mt-2 inline-block text-sm underline" href="{{ route('officer.trainings.index') }}">Show all assigned associations</a></div>
@endif
<form method="GET" class="am-officer-filters"><label class="min-w-0 flex-1 text-sm font-semibold text-slate-700">Search training records<input name="search" value="{{ request('search') }}" placeholder="Search training title" class="mt-2 w-full"></label><input type="hidden" name="association_id" value="{{ $association?->id }}"><button class="am-officer-button">Search</button><a class="am-user-button am-user-button-secondary" href="{{ route('officer.trainings.index') }}">Reset</a></form>
<section class="overflow-hidden rounded-xl border border-slate-200 bg-white" aria-label="Training records"><div class="border-b border-slate-200 p-5"><h2 class="text-lg font-semibold">Training records</h2><p class="mt-1 text-sm text-slate-600">Records matching your current filters and assignments.</p></div><div tabindex="0" role="region" aria-label="Scrollable records" class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Training</th><th class="p-3">Association</th><th class="p-3">From / To</th><th class="p-3">Stage</th></tr></thead><tbody>
@forelse ($trainings as $training)
<tr class="border-t"><td class="p-3"><a class="font-semibold underline" href="{{ route('officer.trainings.show', $training) }}">{{ $training->title }}</a></td><td class="p-3">{{ $training->association->name }}</td><td class="p-3">{{ $training->date_conducted?->format('M d, Y') ?? 'Not recorded' }} / {{ $training->end_date?->format('M d, Y') ?? 'Not recorded' }}</td><td class="p-3">@include('shared.partials.badge', ['label' => $training->is_archived ? 'Archived' : (\App\Models\Training::STAGES[$training->stage] ?? 'Not recorded')])</td></tr>
@empty
<tr><td colspan="4" class="p-4">No records found for the selected criteria</td></tr>
@endforelse
</tbody></table></div><x-management-pagination :records="$trainings" label="Training records pagination" /></section></div>
</x-dashboard-layout>

