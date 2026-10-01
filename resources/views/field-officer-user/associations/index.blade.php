<x-dashboard-layout title="My Associations">
    <div class="space-y-6">
        <header>
            <span class="am-officer-eyebrow">BFAR SAAD Phase II</span>
            <h1>My Associations</h1>
            <p class="mt-1 text-sm leading-6 text-slate-600">View association profiles and program records within your current assignments.</p>
        </header>
        @include('shared.membership.partials.feedback')
        <form method="GET" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2 xl:grid-cols-5">
            <label class="text-sm">Search
                <input name="search" value="{{ $filters['search'] ?? '' }}" class="mt-2 w-full rounded-lg border-slate-300" placeholder="Association name">
            </label>
            @foreach (['area_unit_id' => ['Municipality', $areas, 'name'], 'program_component_id' => ['Program component', $components, 'name'], 'status_id' => ['Status', $statuses, 'status_name']] as $key => [$label, $options, $name])
                <label class="text-sm">{{ $label }}
                    <select name="{{ $key }}" class="mt-2 w-full rounded-lg border-slate-300">
                        <option value="">All</option>
                        @foreach ($options as $option)
                            <option value="{{ $option->id }}" @selected(($filters[$key] ?? '') == $option->id)>{{ $option->$name }}</option>
                        @endforeach
                        @if ($key === 'status_id')
                            <option value="archived" @selected(($filters[$key] ?? '') === 'archived')>Archived</option>
                        @endif
                    </select>
                </label>
            @endforeach
            <div class="flex items-end gap-3">
                <button class="am-officer-button">Filter</button>
                <a class="am-user-button am-user-button-secondary" href="{{ route('officer.associations.index') }}">Reset</a>
            </div>
        </form>
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-5"><h2 class="text-lg font-semibold">Association records</h2><p class="mt-1 text-sm text-slate-600">Records matching your current filters and assignments.</p></div>
            <div class="p-5">
            @include('field-officer-user.associations.table')
            </div>
            <x-management-pagination :records="$associations" label="Association records pagination" />
        </section>
    </div>
</x-dashboard-layout>
