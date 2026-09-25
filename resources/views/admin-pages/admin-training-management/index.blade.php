<x-dashboard-layout title="Training Management">
<div data-training-page data-management-register class="pm-page mx-auto max-w-7xl space-y-5">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-slate-900">Training Management</h1><p class="mt-1 text-sm text-slate-600">Manage association trainings, participants, and attendance.</p></div>
        <a href="{{ route('trainings.create') }}" class="pm-primary">Create Training</a>
    </header>
    @include('admin-pages.admin-project-management.partials.feedback')
    <div class="grid gap-3 sm:grid-cols-3" aria-label="Training summary">
        @foreach(['total' => 'All training records', 'active' => 'Active records', 'archived' => 'Archived records'] as $key => $label)
        <div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-600">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ $summary->$key }}</p></div>
        @endforeach
    </div>
    <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5" aria-label="Filter trainings">
        <form method="GET" action="{{ route('trainings.index') }}" class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div><label for="training-search" class="pm-label">Search</label><input id="training-search" name="search" type="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Title, venue, association" class="pm-input"></div>
            <div><label for="training-scope" class="pm-label">Record scope</label><select id="training-scope" name="scope" class="pm-input"><option value="active" @selected($scope === 'active')>Active</option><option value="archived" @selected($scope === 'archived')>Archived</option></select></div>
            <div><label for="training-association" class="pm-label">Association</label><select id="training-association" name="association_id" class="pm-input"><option value="">All associations</option>@foreach($associations as $association)<option value="{{ $association->id }}" @selected((string)($filters['association_id'] ?? '') === (string)$association->id)>{{ $association->name }}</option>@endforeach</select></div>
            <div><label for="training-period" class="pm-label">Training date</label><select id="training-period" name="period" class="pm-input"><option value="">All dates</option>@foreach(['upcoming' => 'Upcoming', 'past' => 'Today and earlier', 'undated' => 'Date not set'] as $value => $label)<option value="{{ $value }}" @selected(($filters['period'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="flex gap-2"><button class="pm-primary" type="submit">Apply</button><a href="{{ route('trainings.index') }}" class="pm-action border border-slate-300">Reset</a></div>
        </form>
    </section>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white" aria-label="Training records">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">{{ $scope === 'archived' ? 'Archived' : 'Active' }} trainings <span class="ml-1 text-slate-500">({{ $trainings->total() }})</span></h2><p class="mt-1 text-xs text-slate-500">Active means not archived. Training dates do not confirm completion.</p></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th scope="col" class="px-5 py-3">Training / Association</th><th scope="col" class="px-5 py-3">Date / Venue</th><th scope="col" class="px-5 py-3">Participants</th><th scope="col" class="px-5 py-3">Cost</th><th scope="col" class="px-5 py-3">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($trainings as $training)
                    <tr class="hover:bg-slate-50"><td class="max-w-sm px-5 py-4"><a class="font-semibold text-slate-900 hover:underline" href="{{ route('trainings.show', $training) }}">{{ $training->title }}</a><p class="mt-1 text-slate-600">{{ $training->association?->name ?? 'Association unavailable' }}</p><p class="mt-1 text-xs text-slate-500">{{ $training->programComponent?->name ?? 'No component set' }}</p></td><td class="max-w-xs px-5 py-4"><p class="whitespace-nowrap">{{ $training->date_conducted?->format('M d, Y') ?? 'Date not set' }}</p><p class="mt-1 text-xs text-slate-500">{{ $training->venue ?? 'Venue not set' }}</p></td><td class="px-5 py-4">{{ $training->participants_count }}</td><td class="whitespace-nowrap px-5 py-4">{{ $training->training_cost !== null ? '₱'.number_format((float)$training->training_cost, 2) : 'Not set' }}</td><td class="px-5 py-4"><a class="pm-action border border-slate-300" href="{{ route('trainings.show', $training) }}" aria-label="View {{ $training->title }}">View</a></td></tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center"><p class="font-semibold text-slate-700">No trainings found</p><p class="mt-1 text-slate-500">Change the filters or create a training to get started.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-management-pagination :records="$trainings" :numbered="true" label="Training pagination" />
    </section>
</div>
</x-dashboard-layout>
