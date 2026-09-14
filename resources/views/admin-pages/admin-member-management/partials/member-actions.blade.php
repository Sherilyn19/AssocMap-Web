{{-- Match project action spacing across desktop and mobile while retaining member
     modal hooks. Representative protection still controls archive availability. --}}
@php
    $actionClass = 'inline-flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500';
@endphp
<div class="flex flex-wrap items-center gap-1">
    <button type="button" data-member-details="{{ $detailPayloadJson }}" class="{{ $actionClass }}" aria-label="View {{ $memberFullName }}">View</button>

    @if (!$member->is_archived)
        <button type="button" data-edit-member="{{ $editPayloadJson }}" class="{{ $actionClass }}" aria-label="Edit {{ $memberFullName }}">Edit</button>
        <details class="relative" data-member-action-menu>
            <summary class="{{ $actionClass }} cursor-pointer" aria-label="More actions for {{ $memberFullName }}">More</summary>
            <div class="absolute right-0 z-20 min-w-40 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                @if ($isRepresentative)
                    <span class="block rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700">Representative</span>
                    <p class="px-3 py-2 text-xs text-slate-600">Assign a different Association Representative before archiving this member.</p>
                @else
                    <button type="button" data-archive-member
                            data-archive-url="{{ route('members.archive', $member) }}"
                            data-member-name="{{ $memberFullName }}"
                            class="{{ $actionClass }} w-full !text-red-800">
                        Archive Member
                    </button>
                @endif
            </div>
        </details>
    @endif
</div>
