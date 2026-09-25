{{-- Share these action buttons across municipality and barangay layouts.
     Keep the dialog controls and PATCH forms connected to the existing checks
     for archived records and valid input. --}}
@php
    $entityLabel = $entity === 'municipality' ? 'Municipality' : 'Barangay';
    $routeGroup = $entity === 'municipality' ? 'municipalities' : 'barangays';
    $archiveAction = $record->is_archived ? 'restore' : 'archive';
    // Desktop and mobile barangay controls coexist in the DOM, so callers supply
    // distinct form IDs; the menu ID follows that same unique identifier.
    $menuId = $formId . '-actions';
    $actionClass = 'inline-flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500 disabled:cursor-not-allowed disabled:opacity-50';
    $archiveMessage = $entity === 'municipality'
        ? 'This municipality will be archived. It cannot be archived if current barangays or associations still reference it.'
        : 'This barangay will be archived. Current association references must be resolved first.';
@endphp

<div class="flex flex-wrap items-center gap-1 {{ ($alignRight ?? false) ? 'justify-end' : '' }}" data-prevent-card-toggle>
    <button type="button" class="{{ $actionClass }}"
            aria-label="View {{ $entity }} {{ $record->name }}"
            data-area-view-url="{{ route('areas.' . $routeGroup . '.show', $record->id) }}">View</button>

    <button type="button" class="{{ $actionClass }}" @disabled($record->is_archived)
            aria-label="Edit {{ $entity }} {{ $record->name }}"
            title="{{ $record->is_archived ? 'Restore before editing' : 'Edit ' . $entity }}"
            data-{{ $entity }}-modal-open="edit" data-{{ $entity }}='@json($payload)'>Edit</button>

{{-- Use the shared menu controls for Escape and arrow keys, closing on outside
         clicks, keeping the menu on screen, and returning focus after a dialog closes. --}}
    <div class="relative" data-am-dropdown>
        <button type="button" class="{{ $actionClass }}" data-am-dropdown-toggle
                aria-label="More actions for {{ $entity }} {{ $record->name }}"
                aria-expanded="false" aria-controls="{{ $menuId }}">More</button>
        <div id="{{ $menuId }}" data-am-dropdown-menu
             class="absolute right-0 z-40 mt-1 hidden w-48 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
            <button type="button" data-confirm-open
                    data-confirm-tone="{{ $archiveAction }}"
                    data-confirm-target="{{ $formId }}"
                    data-confirm-title="{{ ucfirst($archiveAction) . ' ' . $entityLabel . '?' }}"
                    data-confirm-message="{{ $record->is_archived ? 'This ' . $entity . ' will become current again.' : $archiveMessage }}"
                    data-confirm-label="{{ ucfirst($archiveAction) }}"
                    class="{{ $actionClass }} w-full {{ $record->is_archived ? '!text-emerald-800' : '!text-red-800' }}">
                {{ ucfirst($archiveAction) }} {{ $entityLabel }}
            </button>
        </div>
    </div>

    <form id="{{ $formId }}" action="{{ route('areas.' . $routeGroup . '.' . $archiveAction, $record->id) }}" method="POST" class="hidden">
        @csrf
        @method('PATCH')
    </form>
</div>
