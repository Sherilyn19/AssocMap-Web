<x-dashboard-layout title="Training Records">
<div class="fo-coverage fo-training-ui space-y-6" data-fo-training-page>
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <span class="am-officer-eyebrow">BFAR SAAD Phase II</span>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">Training Records</h1>
            <p class="mt-2 text-sm text-slate-600">
                Training and attendance within your assigned associations.
            </p>
        </div>

        <a href="{{ route('officer.trainings.create') }}"
           data-training-panel data-panel-create
           data-panel-title="Create training"
           class="fo-action am-button-green">
            <span aria-hidden="true">＋</span>
            Create training
        </a>
    </header>

    @include('shared.membership.partials.feedback')

    <form method="GET" action="{{ route('officer.trainings.index') }}"
          class="fo-filter-card">
        <div class="fo-section-heading">
            <h2 class="font-semibold">Filter training records</h2>
            <span class="fo-pill fo-pill-teal">Your assignments only</span>
        </div>

        {{-- Compact fields follow the same layout as Assigned Areas. --}}
        <div class="grid gap-4 p-5 md:grid-cols-3">
            <label>
                <span>Search</span>
                <input name="search" maxlength="255"
                       value="{{ request('search') }}"
                       placeholder="Training title" class="mt-2 w-full">
            </label>

            <label>
                <span>Association</span>
                <select name="association_id" class="mt-2 w-full">
                    <option value="">All assigned associations</option>
                    @foreach($associations as $option)
                        <option value="{{ $option->id }}"
                            @selected($association?->id === $option->id)>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Record state</span>
                <select name="scope" class="mt-2 w-full">
                    @foreach(['current' => 'Current', 'archived' => 'Archived', 'all' => 'All records'] as $key => $label)
                        <option value="{{ $key }}" @selected($scope === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t p-4">
            <a href="{{ route('officer.trainings.index') }}" class="fo-action">Reset</a>
            <button class="fo-action am-button-green">Apply filters</button>
        </div>
    </form>

    {{-- Keep the existing scoped, paginated table and attendance calculations. --}}
    @include('shared.trainings.table', ['readOnly' => false])
</div>

@include('shared.trainings.dialog')
</x-dashboard-layout>