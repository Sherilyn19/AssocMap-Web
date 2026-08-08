{{--
    Compact member actions reused by desktop, tablet, and mobile layouts.
--}}
<div class="flex flex-wrap justify-end gap-2">
    <button
        type="button"
        data-member-details="{{ $detailPayloadJson }}"
        class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700
               transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400"
    >
        View
    </button>

    @if (!$member->is_archived)
        <button
            type="button"
            data-edit-member="{{ $editPayloadJson }}"
            class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700
                   transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400"
        >
            Edit
        </button>

        @if ($isRepresentative)
            <span
                class="inline-flex cursor-not-allowed items-center rounded-lg bg-blue-50 px-2.5 py-1.5
                       text-xs font-semibold text-blue-700"
                title="Assign a different Association Representative before archiving this member."
            >
                Representative
            </span>
        @else
            <button
                type="button"
                data-archive-member
                data-archive-url="{{ route('members.archive', $member) }}"
                data-member-name="{{ $memberFullName }}"
                class="rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-700
                       transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-400"
            >
                Archive
            </button>
        @endif
    @endif
</div>