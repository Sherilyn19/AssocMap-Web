<form method="GET" class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-end" aria-label="Filter {{ strtolower($label) }}">
    <label class="min-w-0 flex-1 text-sm font-semibold text-slate-700">{{ $searchLabel ?? 'Search by title' }}
        <input class="am-user-control mt-2" name="search" value="{{ $filters['search'] }}" maxlength="255" placeholder="{{ $placeholder ?? 'Enter a title' }}">
    </label>
    <label class="text-sm font-semibold text-slate-700">{{ $statusLabel ?? 'Status' }}
        <select class="am-user-control mt-2" name="status">
            @foreach ($options as $value => $text)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $text }}</option>@endforeach
        </select>
    </label>
    <button class="am-user-button am-user-button-primary">Apply filters</button>
    <a class="am-user-button am-user-button-secondary" href="{{ $reset }}">Reset</a>
</form>
