{{-- One action partial gives desktop and mobile the same permissions and URLs. --}}
@php
    $editData = [
        'id' => $association->id, 'name' => $association->name,
        'area_unit_id' => $association->area_unit_id, 'sub_unit_id' => $association->sub_unit_id,
        'program_component_id' => $association->program_component_id,
        'field_officer_id' => $association->field_officer_id, 'status_id' => $association->status_id,
        'address' => $association->address, 'date_joined' => $association->date_joined?->format('Y-m-d'),
        'update_url' => route('admin.associations.update', ['association' => $association, ...$listState]),
    ];
    $archiveAction = $association->is_archived ? 'restore' : 'archive';
@endphp
<div class="flex flex-wrap items-center gap-1">
    <a href="{{ route('admin.associations.show', ['association' => $association, ...$listState]) }}" class="am-association-action" aria-label="View {{ $association->name }}">View</a>
    @unless($association->is_archived)
        <button type="button" data-edit-association="{{ json_encode($editData) }}" class="am-association-action" aria-label="Edit {{ $association->name }}">Edit</button>
    @endunless
    <details class="relative" data-association-menu>
        <summary class="am-association-action cursor-pointer" aria-label="More actions for {{ $association->name }}">More</summary>
        <div class="absolute right-0 z-30 min-w-44 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
            <form method="POST" action="{{ route('admin.associations.'.$archiveAction, ['association' => $association, ...$listState]) }}"
                  data-confirm-form data-confirm-action="{{ $archiveAction }}" data-confirm-name="{{ $association->name }}"
                  data-confirm-message="{{ $association->is_archived ? 'Restore this association? GIS locations will remain unpublished.' : 'Archive this association? Existing records will remain and published GIS locations will be unpublished.' }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="am-association-action w-full {{ $association->is_archived ? '!text-emerald-800' : '!text-red-800' }}">{{ ucfirst($archiveAction) }} Association</button>
            </form>
        </div>
    </details>
</div>
