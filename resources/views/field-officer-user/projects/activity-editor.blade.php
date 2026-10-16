<section data-project-editor class="space-y-4">
    <p class="text-sm text-slate-500">{{ $project->title }}</p>
    <form method="POST" action="{{ $activity ? route('officer.projects.activities.update', [$project, $activity->id]) : route('officer.projects.activities.store', $project) }}" data-project-write class="space-y-4">
        @csrf
        @if($activity) @method('PUT') @endif
        <label class="block text-sm font-semibold">Activity title<input class="mt-2 w-full" name="title" maxlength="255" required value="{{ $activity?->title }}"></label>
        <div class="grid grid-cols-2 gap-3">
            @foreach(['starts_on' => 'Start date', 'ends_on' => 'Target end date'] as $field => $label)
                <label class="block text-sm font-semibold">{{ $label }}<input class="mt-2 w-full" type="date" name="{{ $field }}" required min="{{ $project->implementation_date?->format('Y-m-d') }}" value="{{ $activity?->{$field} }}"></label>
            @endforeach
        </div>
        <label class="block text-sm font-semibold">Progress<select name="state" class="mt-2 w-full">@foreach(['Planned', 'Ongoing', 'Completed'] as $state)<option @selected(($activity?->state ?? 'Planned') === $state)>{{ $state }}</option>@endforeach</select></label>
        <label class="block text-sm font-semibold">Notes<textarea class="mt-2 w-full" name="notes" maxlength="3000" rows="3">{{ $activity?->notes }}</textarea></label>
        <p class="rounded-xl bg-teal-50 p-3 text-sm text-teal-900">Responsible person: {{ $project->association->fieldOfficer?->name ?? 'Unassigned' }} <x-info>The association's assigned Field Officer is responsible automatically. Activities have no budget or cost fields.</x-info></p>
        <button class="fo-action am-button-green" type="submit">Save activity</button>
    </form>
</section>
