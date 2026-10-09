@php($title = $applicationsPage ? 'Applications' : 'Members')
<x-dashboard-layout :title="$title" :topbar-title="$title">
<div data-member-workspace class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    @include('association-member-user.partials.header', ['heading' => $title, 'description' => $applicationsPage ? 'Track membership applications and their review status. Only the assigned Field Officer may approve or reject an application.' : 'Official members of your association. Records are read-only.'])
    @if ($applicationsPage && $association && !$association->is_archived)
        <a class="am-user-button am-user-button-primary" href="{{ route('membership.applications.create') }}">Submit application</a>
    @endif
    @include('association-member-user.partials.filters', ['label' => $title, 'searchLabel' => 'Search name', 'placeholder' => 'Enter a member or applicant name', 'options' => $applicationsPage ? ['' => 'All statuses', 'Pending' => 'Pending', 'Approved' => 'Approved', 'Rejected' => 'Rejected'] : ['Current' => 'Current members', 'Archived' => 'Archived members', 'All' => 'All members'], 'reset' => route($applicationsPage ? 'member.applications' : 'member.members')])
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5"><h2 class="text-lg font-semibold">{{ $title }} <span class="text-slate-500">({{ $records->total() }})</span></h2><p class="mt-1 text-sm text-slate-500">Records belonging to your association.</p></div>
        <ul class="divide-y divide-slate-100">
            @forelse ($records as $record)
            <li class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0"><a class="break-words font-semibold text-blue-800 underline underline-offset-4" href="{{ route($applicationsPage ? 'membership.applications.show' : 'membership.members.show', $record) }}">{{ collect([$record->first_name, $record->middle_name, $record->last_name])->filter()->implode(' ') }}</a>
                    <p class="mt-2 text-sm text-slate-500">{{ $applicationsPage ? 'Submitted '.($record->created_at?->format('M j, Y') ?? 'date not recorded') : ($record->role_in_assoc ?: 'Member').' · Registered '.($record->date_registered?->format('M j, Y') ?? 'date not recorded') }}</p>
                </div>
                <div>@include('shared.partials.badge', ['label' => $applicationsPage ? $record->status?->status_name : ($record->is_archived ? 'Archived' : 'Active')])</div>
            </li>
            @empty
                <li class="px-5 py-12 text-center"><h3 class="font-semibold">No {{ strtolower($title) }} found</h3><p class="mt-2 text-sm text-slate-500">{{ $filters['search'] || $filters['status'] ? 'No records match these filters. Try another search or reset the filters.' : 'No applications have been recorded for this association yet.' }}</p></li>
            @endforelse
        </ul>
        <x-management-pagination :records="$records" />
    </section>
</div>
</x-dashboard-layout>
