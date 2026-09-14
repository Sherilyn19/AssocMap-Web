{{-- The filter card follows the project layout while retaining all member query names.
     Keep municipality/barangay data hooks intact: the member script owns their dependent options. --}}
<section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="member-filters-title">
    <header>
        <h2 id="member-filters-title" class="text-lg font-bold text-slate-900">Filter Members</h2>
        <p class="mt-1 text-sm text-slate-500">Find members by name, association, or membership details.</p>
    </header>
    <form method="GET" action="{{ route('members.index') }}" class="mt-5 space-y-4">
        {{-- Search gets its own row; the remaining filters form balanced rows at each breakpoint. --}}
        <div>
            <label for="member-filter-search" class="block text-sm font-semibold text-slate-700">Search members</label>
            <div class="relative mt-1.5">
                <svg aria-hidden="true" class="pointer-events-none absolute left-3.5 top-3.5 h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="10.5" cy="10.5" r="6.5"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                <input id="member-filter-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Search by member or association name"
                       class="min-h-12 w-full rounded-lg border border-slate-300 bg-white py-2 pl-11 pr-3 text-sm text-slate-900 placeholder:text-slate-400 transition duration-150 hover:border-slate-400 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
<label class="block">
                    <span class="text-sm font-semibold text-slate-700">Association</span>
                    <select
                        name="association_id"
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All associations</option>
                        @foreach ($associations as $association)
                            <option value="{{ $association->id }}" @selected((string) ($filters['association_id'] ?? '') === (string) $association->id)>
                                {{ $association->name }}{{ $association->is_archived ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Record state</span>
                    <select
                        name="record_state"
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="current" @selected(($filters['record_state'] ?? 'current') === 'current')>Current</option>
                        <option value="archived" @selected(($filters['record_state'] ?? '') === 'archived')>Archived</option>
                        <option value="all" @selected(($filters['record_state'] ?? '') === 'all')>All</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Municipality</span>
                    <select
                        name="area_unit_id"
                        data-filter-municipality
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All municipalities</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality->id }}" @selected((string) ($filters['area_unit_id'] ?? '') === (string) $municipality->id)>
                                {{ $municipality->name }}{{ $municipality->is_archived ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Barangay</span>
                    <select
                        name="sub_unit_id"
                        data-filter-barangay
                        data-all-label="All barangays"
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               disabled:bg-slate-100 disabled:text-slate-400
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">Select municipality first</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Sex</span>
                    <select
                        name="sex_id"
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All</option>
                        @foreach ($sexOptions as $sex)
                            <option value="{{ $sex->id }}" @selected((string) ($filters['sex_id'] ?? '') === (string) $sex->id)>
                                {{ $sex->sex_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Association role</span>
                    <select
                        name="role_in_assoc"
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All roles</option>
                        @foreach ($roleOptions as $role)
                            <option value="{{ $role }}" @selected(($filters['role_in_assoc'] ?? '') === $role)>
                                {{ $role }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Beneficiary type</span>
                    <select
                        name="beneficiary_type"
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All beneficiary types</option>
                        @foreach ($beneficiaryTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['beneficiary_type'] ?? '') === $type)>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Registered from</span>
                    <input
                        type="date"
                        name="registered_from"
                        value="{{ $filters['registered_from'] ?? '' }}"
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Registered to</span>
                    <input
                        type="date"
                        name="registered_to"
                        value="{{ $filters['registered_to'] ?? '' }}"
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Sort by</span>
                    <select
                        name="sort"
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="name_asc" @selected(($filters['sort'] ?? 'name_asc') === 'name_asc')>Name A-Z</option>
                        <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Name Z-A</option>
                        <option value="registered_desc" @selected(($filters['sort'] ?? '') === 'registered_desc')>Newest Registered</option>
                        <option value="registered_asc" @selected(($filters['sort'] ?? '') === 'registered_asc')>Oldest Registered</option>
                        <option value="association_asc" @selected(($filters['sort'] ?? '') === 'association_asc')>Association A-Z</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Rows per page</span>
                    <select
                        name="per_page"
                        class="mt-1.5 min-h-11 w-full transition duration-150 hover:border-slate-400 disabled:hover:border-slate-300 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected((int) ($filters['per_page'] ?? 15) === $option)>
                                {{ $option }}
                            </option>
                        @endforeach
                    </select>
                </label>
        </div>
        {{-- Keep the hint and action order aligned with Project Management.
             Submitting remains an explicit GET request, preserving shareable filtered URLs. --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Choose filters, then apply to update the records.</p>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('members.index') }}"
                   class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">Reset filters</a>
                <button type="submit"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16l-6 7v6l-4 2v-8Z"/></svg>
                    Apply filters
                </button>
            </div>
        </div>
    </form>
</section>
