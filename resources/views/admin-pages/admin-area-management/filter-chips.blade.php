@php
    $keys = $tab === 'municipalities'
        ? ['search' => 'Search', 'status' => 'Archive state', 'muni_sort' => 'Sort']
        : ['brgy_search' => 'Search', 'area_unit_id' => 'Municipality', 'brgy_status' => 'Archive state', 'brgy_sort' => 'Sort'];
@endphp
<div class="mb-3 flex flex-wrap gap-2" aria-label="Applied filters">
    @foreach ($keys as $key => $label)
        @if (filled($filters[$key] ?? null) && (!str_ends_with($key, '_sort') || $filters[$key] !== 'name'))
            @php
                $value = match ($key) {
                    'area_unit_id' => $filterMunicipalities->firstWhere('id', $filters[$key])?->name ?? 'Unknown municipality',
                    'status', 'brgy_status' => $filters[$key] === 'active' ? 'Current' : 'Archived',
                    'muni_sort', 'brgy_sort' => $filters[$key] === 'updated_at' ? 'Recently Updated' : 'Newest Created',
                    default => $filters[$key],
                };
                $remaining = \Illuminate\Support\Arr::except($filters, [$key, $tab === 'municipalities' ? 'muni_page' : 'brgy_page']);
            @endphp
            <a class="rounded-full border border-assocmap-border bg-white px-3 py-1 text-xs"
               href="{{ route('areas.index', array_merge($remaining, ['tab' => $tab])) }}"
               aria-label="Remove {{ $label }} filter: {{ $value }}">{{ $label }}: {{ $value }} <span aria-hidden="true">×</span></a>
        @endif
    @endforeach
</div>
