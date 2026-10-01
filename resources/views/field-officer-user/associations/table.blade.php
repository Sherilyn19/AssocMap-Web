<div class="overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable records"><table class="w-full text-left text-sm">
<thead><tr class="border-b"><th class="p-3">Association</th><th class="p-3">Municipality</th><th class="p-3">Program component</th><th class="p-3">Members</th><th class="p-3">Status</th></tr></thead>
<tbody>@forelse ($associations as $association)
<tr class="border-b"><td class="p-3"><a class="font-semibold text-assocmap-primary underline" href="{{ route('officer.associations.show', $association) }}">{{ $association->name }}</a></td><td class="p-3">{{ $association->areaUnit?->name ?? 'Not recorded' }}</td><td class="p-3">{{ $association->programComponent?->name ?? 'Not recorded' }}</td><td class="p-3">{{ $association->members_count }}</td><td class="p-3">@include('shared.partials.badge', ['label' => $association->is_archived ? 'Archived' : ($association->status?->status_name ?? 'Not recorded')])</td></tr>
@empty
<tr><td colspan="5" class="p-4 text-slate-500">No assigned associations match these criteria.</td></tr>
@endforelse</tbody></table></div>

