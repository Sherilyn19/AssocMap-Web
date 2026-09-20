    {{-- Filters --}}
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="association-filters-title">
        <h2 id="association-filters-title" class="mb-4 text-lg font-bold text-slate-900">Filters</h2>
        <form method="GET" action="{{ route('admin.associations.index') }}" class="space-y-4" data-association-filters>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block xl:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Search</span>
                    <div class="relative mt-1.5">
                        <svg class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-slate-400"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-4.35-4.35m1.1-5.4a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                        </svg>
                        <input
                            type="search"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Association name or address"
                            class="min-h-11 w-full rounded-lg border border-slate-300 bg-white py-2 pl-10 pr-3
                                   text-sm text-slate-900 placeholder:text-slate-400
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        >
                    </div>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Municipality</span>
                    <select name="area_unit_id" data-filter-municipality
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2
                                   text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All municipalities</option>
                        @foreach ($filterMunicipalities as $municipality)
                            <option value="{{ $municipality->id }}"
                                @selected((string) ($filters['area_unit_id'] ?? '') === (string) $municipality->id)>
                                {{ $municipality->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Barangay</span>
                    <select name="sub_unit_id" data-filter-barangay
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2
                                   text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All barangays</option>
                        @foreach($filterBarangays as $barangay)
                            @if(empty($filters['area_unit_id']) || (string) $barangay->area_unit_id === (string) $filters['area_unit_id'])
                                <option value="{{ $barangay->id }}" @selected((string) ($filters['sub_unit_id'] ?? '') === (string) $barangay->id)>{{ $barangay->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Program component</span>
                    <select name="program_component_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All components</option>
                        @foreach ($programComponents as $component)
                            <option value="{{ $component->id }}"
                                @selected((string) ($filters['program_component_id'] ?? '') === (string) $component->id)>
                                {{ $component->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Field Officer</span>
                    <select name="field_officer_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All Field Officers</option>
                        @foreach ($filterOfficers as $officer)
                            <option value="{{ $officer->id }}"
                                @selected((string) ($filters['field_officer_id'] ?? '') === (string) $officer->id)>
                                {{ $officer->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Operational status</span>
                    <select name="status_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Active and inactive</option>
                        @foreach ($associationStatuses as $status)
                            <option value="{{ $status->id }}"
                                @selected((string) ($filters['status_id'] ?? '') === (string) $status->id)>
                                {{ $status->status_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Record state</span>
                    <select name="archive_state"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="current" @selected(($filters['archive_state'] ?? 'current') === 'current')>Current</option>
                        <option value="archived" @selected(($filters['archive_state'] ?? '') === 'archived')>Archived</option>
                        <option value="all" @selected(($filters['archive_state'] ?? '') === 'all')>All records</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Sort</span>
                    <select name="sort"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="name_asc" @selected(($filters['sort'] ?? 'name_asc') === 'name_asc')>Name A–Z</option>
                        <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Name Z–A</option>
                        <option value="date_joined_desc" @selected(($filters['sort'] ?? '') === 'date_joined_desc')>Newest joined</option>
                        <option value="date_joined_asc" @selected(($filters['sort'] ?? '') === 'date_joined_asc')>Oldest joined</option>
                        <option value="created_desc" @selected(($filters['sort'] ?? '') === 'created_desc')>Recently created</option>
                        <option value="updated_desc" @selected(($filters['sort'] ?? '') === 'updated_desc')>Recently updated</option>
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <label class="flex items-center gap-2 text-sm text-slate-600">Rows per page
                    <select name="per_page" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2">
                        @foreach([10, 15, 25, 50] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 10) === $size)>{{ $size }}</option>@endforeach
                    </select>
                </label>
                <p data-filter-hint class="text-sm text-slate-500" aria-live="polite">Choose filters, then apply to update the records.</p>
                <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.associations.index') }}"
                   class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-4 py-2
                          text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset filters
                </a>
                <button type="submit"
                        class="inline-flex min-h-10 items-center rounded-lg bg-slate-800 px-4 py-2
                               text-sm font-semibold text-white hover:bg-slate-700">
                    Apply filters
                </button>
                </div>
            </div>
        </form>
    </section>

