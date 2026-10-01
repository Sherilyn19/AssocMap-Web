<x-dashboard-layout title="Training Records">
<div class="space-y-6"><header><span class="am-officer-eyebrow">BFAR SAAD Phase II</span><h1>Training Records</h1><p class="mt-1 text-sm leading-6 text-slate-600">View training information and manage attendance for assigned associations.</p></header>
@include('shared.membership.partials.feedback')
@if ($association)
<div class="rounded-xl border border-assocmap-border bg-white p-4"><p class="text-sm text-slate-600">Association context</p><p class="font-semibold">{{ $association->name }}</p><a class="mt-2 inline-block text-sm underline" href="{{ route('officer.trainings.index') }}">Show all assigned associations</a></div>
@endif
<form method="GET" class="am-officer-filters"><label class="min-w-0 flex-1 text-sm font-semibold text-slate-700">Search training records<input name="search" value="{{ request('search') }}" placeholder="Search training title" class="mt-2 w-full"></label><input type="hidden" name="association_id" value="{{ $association?->id }}"><button class="am-officer-button">Search</button><a class="am-user-button am-user-button-secondary" href="{{ route('officer.trainings.index') }}">Reset</a></form>
@include('shared.trainings.table', ['readOnly' => false])
</div>
@include('shared.trainings.dialog')
</x-dashboard-layout>

