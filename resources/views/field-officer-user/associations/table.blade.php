<div class="overflow-x-auto" tabindex="0" role="region"
     aria-label="Assigned association records">
    <table class="fo-table">
        <caption class="sr-only">Assigned associations</caption>
        <thead>
            <tr>
                <th scope="col">Association / ID</th>
                <th scope="col">City / Municipality / Program</th>
                <th scope="col">Members</th>
                <th scope="col">Status</th>
                <th scope="col">Details</th>
            </tr>
        </thead>

        <tbody>
            @forelse($associations as $association)
                @php
                    $detailsUrl = route('officer.associations.show', $association);
                    $status = $association->is_archived
                        ? 'Archived'
                        : ($association->status?->status_name ?? 'Not recorded');
                @endphp

                <tr>
                    <th scope="row" class="min-w-[14rem]">
                        <a href="{{ $detailsUrl }}"
                           data-record-open
                           data-record-title="Association details"
                           class="font-semibold hover:underline">
                            {{ $association->name }}
                        </a>
                        <span class="fo-record-id">
                            ASSOC-{{ str_pad((string) $association->id, 6, '0', STR_PAD_LEFT) }}
                        </span>
                    </th>

                    <td>
                        <span class="font-medium">
                            {{ $association->areaUnit?->name ?? 'Not recorded' }}
                        </span>
                        <small class="mt-1 block text-slate-500">
                            {{ $association->programComponent?->name ?? 'Not recorded' }}
                        </small>
                    </td>

                    <td>
                        {{-- This existing count excludes archived members. --}}
                        <span class="font-semibold tabular-nums">
                            {{ number_format($association->members_count) }}
                        </span>
                        <small class="mt-1 block text-slate-500">
                            Non-archived
                        </small>
                    </td>

                    <td>
                        <span class="fo-pill {{ match($status) {
                            'Active' => 'fo-pill-green',
                            'Archived' => 'fo-pill-amber',
                            default => 'fo-pill-slate',
                        } }}">
                            {{ $status }}
                        </span>
                    </td>

                    <td>
                        <a href="{{ $detailsUrl }}"
                           data-record-open
                           data-record-title="Association details"
                           class="fo-action"
                           aria-label="View {{ $association->name }}">
                            View
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.5"
                                 aria-hidden="true">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      d="m9 5 7 7-7 7"/>
                            </svg>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-slate-500">
                        <p class="font-semibold">No matching associations</p>
                        <p class="mt-1 text-sm">
                            Try another search or reset your filters.
                        </p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>