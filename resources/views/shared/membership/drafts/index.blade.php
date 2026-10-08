<x-dashboard-layout title="Member Drafts">
    <div class="fo-coverage space-y-5">
        <header class="fo-card flex flex-wrap items-center justify-between gap-4 p-5">
            <div>
                <p class="fo-eyebrow">Member management</p>
                <h1 class="mt-2 text-2xl font-bold">Prospective-member drafts</h1>
            </div>

            @if($canCreate)
                <a href="{{ route('membership.drafts.create') }}"
                   class="fo-action am-button-green">
                    Create draft
                </a>
            @endif
        </header>

        @include('shared.membership.partials.feedback')

        <form method="GET" class="fo-card flex flex-wrap items-end gap-3 p-5">
            <label>
                <span class="mb-2 block font-semibold">Draft state</span>
                <select name="state" class="rounded-lg border border-slate-300 p-3">
                    <option value="">All states</option>
                    @foreach(['draft', 'submitted', 'cancelled'] as $state)
                        <option value="{{ $state }}" @selected(request('state') === $state)>
                            {{ ucfirst($state) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <button class="fo-action am-button-green">Apply filters</button>
            <a href="{{ route('membership.drafts.index') }}" class="fo-action">Reset</a>
            <a href="{{ route('membership.index') }}" class="fo-action">Members and applications</a>
        </form>

        <section class="fo-table-card">
            <div class="overflow-x-auto">
                <table class="fo-table">
                    <thead>
                        <tr>
                            <th>Draft</th>
                            <th>Prospective member</th>
                            <th>Association</th>
                            <th>Created by</th>
                            <th>State</th>
                            <th>Action</th>
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
                                <td>DRAFT-{{ str_pad((string) $draft->id, 6, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $name ?: 'Name not yet entered' }}</td>
                                <td>{{ $draft->association?->name }}</td>
                                <td>{{ $draft->creator?->name }}</td>
                                <td>
                                    <span class="fo-pill {{ $draft->state === 'draft' ? 'fo-pill-amber' : 'fo-pill-slate' }}">
                                        {{ ucfirst($draft->state) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('membership.drafts.show', $draft) }}"
                                       class="fo-action">
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No accessible drafts match this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-5">{{ $drafts->links() }}</div>
        </section>
    </div>
</x-dashboard-layout>