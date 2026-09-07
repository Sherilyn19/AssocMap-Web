{{-- The desktop table and mobile cards render the same records. Keep their fields and actions aligned when changing this partial. --}}
@if($records->isEmpty())
    <div class="p-8 text-center">
        <p class="font-semibold">No projects found.</p>
        <p class="mt-1 text-sm text-slate-600">{{ $emptyMessage ?? 'There are no projects in this group.' }}</p>
    </div>
@else
    <div class="hidden xl:block">
        <table class="w-full table-fixed text-left text-sm">
            <caption class="sr-only">{{ $caption ?? 'Project records' }}</caption>
            <thead class="border-y border-slate-200 bg-slate-50 text-xs text-slate-700">
                <tr>
                    <th scope="col" class="w-[24%] p-3">Project / Association</th>
                    <th scope="col" class="w-[18%] p-3">Classification / Commodity</th>
                    <th scope="col" class="w-[12%] p-3">Project status</th>
                    <th scope="col" class="w-[12%] p-3">Implementation</th>
                    <th scope="col" class="w-[14%] p-3 text-right">Budget</th>
                    <th scope="col" class="w-[8%] p-3 text-right">Materials</th>
                    <th scope="col" class="w-[12%] p-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
            @foreach($records as $project)
                <tr class="align-top hover:bg-slate-50">
                    <th scope="row" class="break-words p-3 font-normal">
                        <p class="font-semibold">{{ $project->title }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ $project->association?->name ?? 'Not recorded' }}</p>
                        @if($project->is_archived)<span class="mt-1 inline-block text-xs font-semibold text-slate-600">Record: Archived</span>@endif
                    </th>
                    <td class="break-words p-3">
<p>{{ $project->programComponent?->name ?? 'Not recorded' }}</p>
<p class="mt-1 text-xs text-slate-600">Commodity: {{ $project->commodity_type ?: 'Not recorded' }}</p>
</td>
                    <td class="p-3">@include('admin-pages.admin-project-management.partials.badge', ['label' => $project->status?->status_name ?? 'Not recorded'])</td>
                    <td class="p-3">{{ $project->implementation_date?->format('M j, Y') ?? 'Not recorded' }}</td>
                    <td class="break-words p-3 text-right tabular-nums">{{ $project->budget === null ? 'Not recorded' : '₱'.number_format((float)$project->budget, 2) }}</td>
                    <td class="p-3 text-right tabular-nums">{{ $project->materials_count }}</td>
                    <td class="p-2">@include('admin-pages.admin-project-management.partials.actions')</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="divide-y divide-slate-200 xl:hidden">
    @foreach($records as $project)
        <article class="space-y-3 p-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h3 class="min-w-0 break-words font-semibold">{{ $project->title }}</h3>
                @include('admin-pages.admin-project-management.partials.badge', ['label' => $project->status?->status_name ?? 'Not recorded'])
            </div>
            <p class="break-words text-sm text-slate-600">{{ $project->association?->name ?? 'Association not recorded' }}</p>
            @if($project->is_archived)<p class="text-xs font-semibold">Record: Archived</p>@endif
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div class="min-w-0">
<dt class="text-xs text-slate-600">Program Component</dt>
<dd class="break-words">{{ $project->programComponent?->name ?? 'Not recorded' }}</dd>
</div>
                <div class="min-w-0">
<dt class="text-xs text-slate-600">Commodity</dt>
<dd class="break-words">{{ $project->commodity_type ?: 'Not recorded' }}</dd>
</div>
                <div>
<dt class="text-xs text-slate-600">Implementation</dt>
<dd>{{ $project->implementation_date?->format('M j, Y') ?? 'Not recorded' }}</dd>
</div>
                <div class="min-w-0">
<dt class="text-xs text-slate-600">Budget</dt>
<dd class="break-words tabular-nums">{{ $project->budget === null ? 'Not recorded' : '₱'.number_format((float)$project->budget, 2) }}</dd>
</div>
                <div>
<dt class="text-xs text-slate-600">Materials</dt>
<dd>{{ $project->materials_count }} records</dd>
</div>
            </dl>
            @include('admin-pages.admin-project-management.partials.actions')
        </article>
    @endforeach
    </div>
@endif
