{{-- Two responsive presentations share the same actions; no capability disappears on mobile. --}}
<div class="hidden lg:block">
    <table class="w-full table-fixed divide-y divide-slate-200">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
            <tr>
                <th scope="col" class="w-[25%] px-4 py-3">Association</th>
                <th scope="col" class="w-[19%] px-4 py-3">Location / Program</th>
                <th scope="col" class="w-[23%] px-4 py-3">Assigned personnel</th>
                <th scope="col" class="w-[7%] px-2 py-3">Members</th>
                <th scope="col" class="w-[12%] px-3 py-3">Status</th>
                <th scope="col" class="w-[14%] px-3 py-3">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($associations as $association)
                <tr class="align-top hover:bg-slate-50/70">
                    <td class="break-words px-4 py-4"><p class="font-semibold text-slate-900">{{ $association->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $association->address }}</p></td>
                    <td class="break-words px-4 py-4 text-sm"><p>{{ $association->subUnit?->name }}, {{ $association->areaUnit?->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $association->programComponent?->name }}</p></td>
                    <td class="break-words px-4 py-4 text-sm"><p class="font-medium">{{ $association->fieldOfficer?->name ?? 'Not assigned' }}</p><p class="mt-1 text-xs text-slate-500">Representative: {{ $association->representative ? trim($association->representative->first_name.' '.$association->representative->last_name) : 'Not assigned' }}</p></td>
                    <td class="px-2 py-4 text-sm font-semibold tabular-nums">{{ $association->members_count }}</td>
                    <td class="px-3 py-4">@include('admin-pages.admin-association-management.partials.status')</td>
                    <td class="px-2 py-3">@include('admin-pages.admin-association-management.partials.actions')</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-600">No associations match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="divide-y divide-slate-200 lg:hidden">
    @forelse($associations as $association)
        <article class="space-y-4 p-4">
            <div class="flex items-start justify-between gap-3"><h3 class="min-w-0 break-words font-semibold text-slate-900">{{ $association->name }}</h3>@include('admin-pages.admin-association-management.partials.status')</div>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-xs text-slate-500">Location</dt><dd class="mt-1 break-words">{{ $association->subUnit?->name }}, {{ $association->areaUnit?->name }}</dd></div>
                <div><dt class="text-xs text-slate-500">Program</dt><dd class="mt-1">{{ $association->programComponent?->name }}</dd></div>
                <div><dt class="text-xs text-slate-500">Field Officer</dt><dd class="mt-1 break-words">{{ $association->fieldOfficer?->name }}</dd></div>
                <div><dt class="text-xs text-slate-500">Members</dt><dd class="mt-1 tabular-nums">{{ $association->members_count }}</dd></div>
            </dl>
            <div class="border-t border-slate-100 pt-2">@include('admin-pages.admin-association-management.partials.actions')</div>
        </article>
    @empty
        <p class="px-4 py-12 text-center text-sm text-slate-600">No associations match these filters.</p>
    @endforelse
</div>
