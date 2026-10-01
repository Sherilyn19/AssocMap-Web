<div class="flex flex-wrap gap-1.5 text-xs font-semibold">
    <span class="rounded-full px-2.5 py-1 {{ $association->status?->status_name === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $association->status?->status_name ?? 'Unknown' }}</span>
    <span class="rounded-full px-2.5 py-1 {{ $association->is_archived ? 'bg-slate-200 text-slate-700' : 'bg-blue-100 text-blue-800' }}">{{ $association->is_archived ? 'Archived' : 'Current' }}</span>
</div>
