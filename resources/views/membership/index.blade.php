<x-dashboard-layout title="Member Management">
<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <header class="rounded-xl border border-slate-200 bg-white p-6">
        <h1 class="text-2xl font-bold text-slate-900">Member Management</h1>
        <p class="mt-2 text-slate-600">View members and applications within your authorized associations.</p>
        @if ($canSubmit)
            <a href="{{ route('membership.applications.create') }}" class="mt-4 inline-flex rounded-lg bg-slate-800 px-4 py-3 font-semibold text-white">Submit Application</a>
        @endif
    </header>
    @include('membership.partials.feedback')
    <form method="GET" class="flex flex-wrap items-end gap-4 rounded-xl border bg-white p-4">
        <label class="flex-1"><span class="block text-sm font-semibold">Search name</span><input name="search" value="{{ request('search') }}" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 p-3"></label>
        <label><span class="block text-sm font-semibold">Application status</span><select name="status" class="mt-1 rounded-lg border border-slate-300 p-3"><option value="">All statuses</option>@foreach (['Pending', 'Approved', 'Rejected'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></label>
        <button class="rounded-lg bg-slate-800 px-4 py-3 text-white">Apply Filters</button>
        <a href="{{ route('membership.index') }}" class="rounded-lg border px-4 py-3">Reset</a>
    </form>
    <section class="rounded-xl border bg-white p-5">
        <h2 class="text-lg font-bold">Applications <span class="text-slate-500">({{ $applications->total() }})</span></h2>
        <p class="mt-1 text-sm text-slate-600">Pending applications require the designated representative's private review passphrase.</p>
        <ul class="mt-4 divide-y">
            @forelse ($applications as $application)
                <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                    <div><a class="font-semibold text-blue-800 underline" href="{{ route('membership.applications.show', $application) }}">{{ $application->first_name }} {{ $application->last_name }}</a><p class="text-sm text-slate-600">{{ $application->association->name }}</p></div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm">{{ $application->status->status_name }}</span>
                </li>
            @empty <li class="py-6 text-slate-600">No applications match your filters.</li> @endforelse
        </ul>
        {{ $applications->links() }}
    </section>
    <section class="rounded-xl border bg-white p-5">
        <h2 class="text-lg font-bold">Current Official Members <span class="text-slate-500">({{ $members->total() }})</span></h2>
        <ul class="mt-4 divide-y">
            @forelse ($members as $member)
                <li class="py-4"><a class="font-semibold text-blue-800 underline" href="{{ route('membership.members.show', $member) }}">{{ $member->first_name }} {{ $member->last_name }}</a><p class="text-sm text-slate-600">{{ $member->association->name }} · {{ $member->role_in_assoc ?: 'Member' }}</p></li>
            @empty <li class="py-6 text-slate-600">No current members match your search.</li> @endforelse
        </ul>
        {{ $members->links() }}
    </section>
</div>
</x-dashboard-layout>
