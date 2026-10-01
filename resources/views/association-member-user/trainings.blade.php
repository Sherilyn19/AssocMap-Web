<x-dashboard-layout title="Association Trainings" topbar-title="Association Trainings">
<div data-member-workspace class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    @include('association-member-user.partials.header', ['heading' => 'Association Trainings', 'description' => 'Browse scheduled activities and training records for your association.'])
    @include('association-member-user.partials.filters', ['label' => 'Trainings', 'statusLabel' => 'Record state', 'options' => ['Current' => 'Current records', 'Archived' => 'Archived records', 'All' => 'All records'], 'reset' => route('member.trainings')])
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5"><h2 class="text-lg font-semibold">Training records <span class="text-slate-500">({{ $trainings->total() }})</span></h2></div>
        <ul class="divide-y divide-slate-100">
            @forelse ($trainings as $training)
                <li class="grid gap-4 p-5 md:grid-cols-[1fr_12rem_auto] md:items-center"><div class="min-w-0"><a class="break-words font-semibold text-blue-800 underline underline-offset-4" href="{{ route('member.trainings.show', $training) }}">{{ $training->title }}</a><p class="mt-2 text-sm text-slate-500">{{ $training->training_type ?: 'Type not recorded' }} · {{ $training->programComponent?->name ?? 'Component not recorded' }}</p><p class="mt-1 break-words text-sm text-slate-600">{{ $training->venue ?: 'Venue not recorded' }}</p></div><div class="text-sm"><p>{{ $training->date_conducted?->format('M j, Y') ?? 'Date not recorded' }}</p>@if($training->end_date)<p class="mt-1 text-slate-500">to {{ $training->end_date->format('M j, Y') }}</p>@endif</div><div>@include('shared.partials.badge', ['label' => $training->is_archived ? 'Archived' : (\App\Models\Training::STAGES[$training->stage] ?? 'Stage not recorded')])</div></li>
            @empty <li class="px-5 py-12 text-center"><h3 class="font-semibold">No trainings found</h3><p class="mt-2 text-sm text-slate-500">No training records match this view. Try resetting your filters.</p></li> @endforelse
        </ul>
        <x-management-pagination :records="$trainings" />
    </section>
</div>
</x-dashboard-layout>
