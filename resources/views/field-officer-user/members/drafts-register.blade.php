{{-- This partial belongs inside the Members workspace, not a separate page. --}}
<form method="GET" action="{{ route('membership.index') }}" class="fo-filter-card">
    <input type="hidden" name="tab" value="drafts">

    <div class="grid gap-4 p-4 md:grid-cols-3">
        <label>
            <span class="mb-2 block text-sm font-semibold">Search name</span>
            <input type="search" name="search" maxlength="255"
                   value="{{ request('search') }}"
                   placeholder="Prospective member name"
                   class="w-full">
        </label>

        <label>
            <span class="mb-2 block text-sm font-semibold">Association</span>
            <select name="association_id" class="w-full">
                <option value="">All assigned associations</option>
                @foreach($associationOptions as $association)
                    <option value="{{ $association->id }}"
                            @selected((string) request('association_id') === (string) $association->id)>
                        {{ $association->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="mb-2 block text-sm font-semibold">Draft state</span>
            <select name="draft_state" class="w-full">
                <option value="">All states</option>
                @foreach(['draft', 'submitted', 'cancelled'] as $state)
                    <option value="{{ $state }}" @selected(request('draft_state') === $state)>
                        {{ ucfirst($state) }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
        <a href="{{ route('membership.index', ['tab' => 'drafts']) }}"
           class="fo-action">Reset</a>
        <button class="fo-action am-button-green">Apply filters</button>
    </div>
</form>

<section class="fo-table-card">
    <header class="fo-section-heading">
        <h2 class="font-semibold">Saved drafts</h2>
        <span class="fo-pill fo-pill-slate">
            {{ number_format($drafts->total()) }} records
        </span>
    </header>

    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Saved drafts">
        <table class="fo-table fo-members-table">
            <thead>
                <tr>
                    <th scope="col">No.</th>
                    <th scope="col">Name / ID</th>
                    <th scope="col">Association</th>
                    <th scope="col">State</th>
                    <th scope="col">Updated</th>
                    <th scope="col">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drafts as $draft)
                    @php
                        $name = trim(implode(' ', array_filter([
                            $draft->profile['first_name'] ?? null,
                            $draft->profile['middle_name'] ?? null,
                            $draft->profile['last_name'] ?? null,
                        ])));
                    @endphp
                    <tr>
                        <td data-label="No.">{{ $drafts->firstItem() + $loop->index }}</td>

                        <th scope="row" class="fo-members-name">
                            <a href="{{ route('membership.drafts.show', $draft) }}"
                               data-record-open data-record-title="Member draft"
                               class="font-semibold hover:underline">
                                {{ $name ?: 'Name not yet entered' }}
                            </a>
                            <span class="fo-record-id">
                                DRAFT-{{ str_pad((string) $draft->id, 6, '0', STR_PAD_LEFT) }}
                            </span>
                        </th>

                        <td data-label="Association">{{ $draft->association?->name }}</td>
                        <td data-label="State">
                            <span class="fo-pill {{ $draft->state === 'draft' ? 'fo-pill-amber' : 'fo-pill-slate' }}">
                                {{ ucfirst($draft->state) }}
                            </span>
                        </td>
                        <td data-label="Updated">
                            {{ $draft->updated_at?->format('M d, Y') }}
                        </td>
                        <td class="fo-members-action">
                            <a href="{{ route('membership.drafts.show', $draft) }}"
                               data-record-open data-record-title="Member draft"
                               class="fo-action">
                                {{ $draft->state === 'draft' ? 'Resume' : 'View' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="fo-members-empty text-center">
                            No saved drafts match your filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-management-pagination :records="$drafts" label="Draft pagination"/>
</section>