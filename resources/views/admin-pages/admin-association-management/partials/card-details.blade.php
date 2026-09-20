{{-- Render a usable section first; JS enhances this same content into a native dialog.
     Only the requested page of records is loaded, and each paginator has its own query key. --}}
@php
    $cardKey = $summaryKey ?? $relatedKey;
    $cardRecords = $summaryRecords ?? $relatedRecords;
    $isRegisterCard = isset($summaryKey);
@endphp
<section id="association-card-details" data-association-card-details="{{ $cardKey }}" data-close-url="{{ $cardCloseUrl }}" class="rounded-xl border border-slate-200 bg-white text-slate-900" aria-labelledby="association-card-title">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 p-4 sm:p-5">
        <div class="min-w-0">
            <h2 id="association-card-title" tabindex="-1" class="text-xl font-bold">{{ $cardLabels[$cardKey] }}</h2>
            <p class="mt-1 break-words text-sm text-slate-600">{{ $isRegisterCard ? 'All matching association records, independent of the main-list filters.' : $association->name.' — records matching this card only.' }}</p>
        </div>
        <a href="{{ $cardCloseUrl }}#association-card-{{ $cardKey }}" data-card-close class="am-association-action border border-slate-300">Close details</a>
    </header>
    <div class="divide-y divide-slate-100">
        @forelse($cardRecords as $record)
            @php
                $recordUrl = null;
                if ($isRegisterCard) {
                    $recordTitle = $record->name;
                    $recordDetail = ($record->subUnit?->name ?? '—').', '.($record->areaUnit?->name ?? '—');
                    $recordMeta = ($record->is_archived ? 'Archived' : 'Current').' · '.($record->status?->status_name ?? 'Unknown');
                    $recordUrl = route('admin.associations.show', ['association' => $record, ...$listState]);
                } elseif (in_array($cardKey, ['members', 'applications'], true)) {
                    $recordTitle = implode(' ', array_filter([$record->first_name, $record->middle_name, $record->last_name], fn ($part) => filled($part)));
                    $recordDetail = $cardKey === 'members' ? ($record->role_in_assoc ?: 'Member') : 'Pending application';
                    $recordMeta = $cardKey === 'members' ? $record->date_registered?->format('M j, Y') : $record->created_at?->format('M j, Y');
                    $recordUrl = route($cardKey === 'members' ? 'members.show' : 'members.applications.show', $record->id);
                } elseif (in_array($cardKey, ['projects', 'trainings'], true)) {
                    $recordTitle = $record->title;
                    $recordDetail = $cardKey === 'projects' ? 'Current project' : ($record->venue ?: 'Venue not recorded');
                    $recordMeta = $cardKey === 'projects' ? $record->implementation_date?->format('M j, Y') : $record->date_conducted?->format('M j, Y');
                    if ($cardKey === 'projects') $recordUrl = route('projects.show', $record->id);
                } else {
                    $recordTitle = $record->location_name ?: 'Unnamed location';
                    $recordDetail = $record->latitude !== null && $record->longitude !== null ? $record->latitude.', '.$record->longitude : 'Coordinates not recorded';
                    $recordMeta = $record->is_published ? 'Published' : 'Unpublished';
                }
            @endphp
            <article class="flex flex-wrap items-center justify-between gap-3 p-4 sm:px-5">
                <div class="min-w-0 flex-1 break-words"><h3 class="font-semibold">{{ $recordTitle }}</h3><p class="mt-1 text-sm text-slate-600">{{ $recordDetail }}</p><p class="mt-1 text-xs text-slate-500">{{ $recordMeta ?: 'Date not recorded' }}</p></div>
                @if($recordUrl)<a href="{{ $recordUrl }}" class="am-association-action border border-slate-300" aria-label="View {{ $recordTitle }}">View record</a>@endif
            </article>
        @empty
            <p class="p-8 text-center text-sm text-slate-600">No records match this card.</p>
        @endforelse
    </div>
    <x-management-pagination :records="$cardRecords" :numbered="true" />
</section>
