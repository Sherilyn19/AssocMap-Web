{{-- Archived records remain viewable. Hiding edit controls is presentation only; the service enforces read-only writes. --}}
<x-dashboard-layout title="Project Details" topbar-title="Administration">
<div data-pm-page class="pm-page space-y-5">
    <header class="space-y-3">
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-xs leading-5 text-slate-600">
            <a class="hover:underline" href="{{ route('projects.index', ['scope'=>$project->is_archived ? 'archived' : 'active']) }}">Project Management</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Project Details</span>
        </nav>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <h1 class="min-w-0 break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $project->title }}</h1>
                    @include('admin-pages.admin-project-management.partials.badge', ['label'=>$project->status?->status_name ?? 'Not recorded'])
                    @if($project->is_archived)
                        @include('admin-pages.admin-project-management.partials.badge', ['label'=>'Archived'])
                    @endif
                </div>
                <p class="mt-1 break-words text-sm leading-6 text-slate-600">{{ $project->association?->name ?? 'Association not recorded' }}</p>
            </div>
            @unless($project->is_archived)
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <a class="pm-primary" href="{{ route('projects.edit',$project) }}">Edit Project</a>
                    <details data-pm-menu class="relative">
                        <summary class="pm-action cursor-pointer gap-2 border border-slate-300" aria-label="More project actions">
                            More
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </summary>
                        <form class="absolute right-0 z-20 min-w-44 rounded-lg border border-slate-200 bg-white p-2 shadow-lg" method="POST" action="{{ route('projects.archive',$project) }}" data-pm-archive data-project-title="{{ $project->title }}">
                            @csrf
                            @method('PATCH')
                            <button class="pm-action text-red-800" type="submit">Archive Project</button>
                        </form>
                    </details>
                </div>
            @endunless
        </div>
    </header>
    @if($project->is_archived)<p class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">Archived record — project information and materials are read-only.</p>@endif
    @include('admin-pages.admin-project-management.partials.feedback')
    <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5" aria-labelledby="project-information">
        <h2 id="project-information" class="font-semibold">Project Information</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
            @foreach(['Association'=>$project->association?->name, 'Program Component'=>$project->programComponent?->name, 'Commodity Type'=>$project->commodity_type, 'Implementation Date'=>$project->implementation_date?->format('M j, Y'), 'Budget'=>$project->budget === null ? null : '₱'.number_format((float)$project->budget,2)] as $label=>$value)
                <div class="min-w-0">
<dt class="text-xs text-slate-600">{{ $label }}</dt>
<dd class="mt-1 break-words font-medium">{{ $value ?: 'Not recorded' }}</dd>
</div>
            @endforeach
            <div class="sm:col-span-2 lg:col-span-3">
<dt class="text-xs text-slate-600">Remarks</dt>
<dd class="mt-1 whitespace-pre-line break-words">{{ $project->remarks ?: 'Not recorded' }}</dd>
</div>
        </dl>
    </section>
    {{-- Sum only recorded costs; unknown costs make this a partial estimate, not confirmed spending. --}}
    @php
        $missingCosts = $project->materials->whereNull('unit_cost')->count();
        $recordedCost = $project->materials->whereNotNull('unit_cost')->sum(fn($item) => $item->total_cost);
    @endphp
    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5" aria-labelledby="project-materials">
        <header class="flex flex-wrap items-start justify-between gap-3">
<div>
<h2 id="project-materials" class="font-semibold">Project Materials</h2>
<p class="mt-1 text-sm text-slate-600">{{ $project->materials->count() }} material records</p>
</div>@unless($project->is_archived)<a class="pm-primary" href="#material-new" data-pm-open-editor>+ Add Material</a>@endunless</header>
        <div class="rounded-lg bg-slate-50 p-3 text-sm">
<span>{{ $missingCosts ? 'Recorded Material Cost' : 'Total Material Cost' }}</span>
<strong class="ml-2 tabular-nums">₱{{ number_format($recordedCost,2) }}</strong>@if($missingCosts)<p class="mt-1 text-xs text-slate-600">Incomplete total: {{ $missingCosts }} material records have no unit cost.</p>@endif<p class="mt-1 text-xs text-slate-600">Calculated from quantity × unit cost. This is not an expenditure total.</p>
</div>
        @unless($project->is_archived)@include('admin-pages.admin-project-management.partials.material-form',['material'=>null])@endunless
        @if($project->materials->isEmpty())<p class="py-6 text-center text-sm text-slate-600">No materials recorded.</p>@else
        <div class="hidden xl:block">
<table class="w-full table-fixed text-left text-sm">
<caption class="sr-only">Project material records</caption>
<thead class="border-y bg-slate-50 text-xs text-slate-700">
<tr>
<th scope="col" class="w-[23%] p-2">Material</th>
<th scope="col" class="w-[12%] p-2">Qty / Unit</th>
<th scope="col" class="w-[14%] p-2 text-right">Unit Cost</th>
<th scope="col" class="w-[16%] p-2 text-right">Calculated Total Cost</th>
<th scope="col" class="w-[13%] p-2">Delivery Date</th>
<th scope="col" class="w-[13%] p-2">Material Status</th>
<th scope="col" class="w-[9%] p-2">Action</th>
</tr>
</thead>
<tbody class="divide-y">
        @foreach($project->materials as $material)<tr class="align-top">
<th scope="row" class="break-words p-2 font-medium">{{ $material->item_name }}</th>
<td class="break-words p-2">{{ $material->quantity }} {{ $material->unit }}</td>
<td class="break-words p-2 text-right tabular-nums">{{ $material->unit_cost === null ? 'Not recorded' : '₱'.number_format((float)$material->unit_cost,2) }}</td>
<td class="break-words p-2 text-right tabular-nums">{{ $material->unit_cost === null ? 'Not recorded' : '₱'.number_format($material->total_cost,2) }}</td>
<td class="p-2">{{ $material->delivery_date?->format('M j, Y') ?? 'Not recorded' }}</td>
<td class="p-2">{{ $material->status?->status_name ?? 'Not recorded' }}</td>
<td class="p-1">@unless($project->is_archived)<a class="pm-action" href="#material-{{ $material->id }}" data-pm-open-editor aria-label="Edit {{ $material->item_name }}">Edit</a>@else<span class="text-xs text-slate-600">Read-only</span>@endunless</td>
</tr>@endforeach
        </tbody>
</table>
</div>
        <div class="divide-y xl:hidden">@foreach($project->materials as $material)<article class="space-y-3 py-4">
<h3 class="break-words font-semibold">{{ $material->item_name }}</h3>
<dl class="grid grid-cols-2 gap-3 text-sm">@foreach(['Quantity / Unit'=>$material->quantity.' '.$material->unit,'Unit Cost'=>$material->unit_cost === null ? 'Not recorded' : '₱'.number_format((float)$material->unit_cost,2),'Calculated Total Cost'=>$material->unit_cost === null ? 'Not recorded' : '₱'.number_format($material->total_cost,2),'Delivery Date'=>$material->delivery_date?->format('M j, Y') ?? 'Not recorded','Material Status'=>$material->status?->status_name ?? 'Not recorded'] as $label=>$value)<div class="min-w-0">
<dt class="text-xs text-slate-600">{{ $label }}</dt>
<dd class="break-words">{{ $value }}</dd>
</div>@endforeach</dl>@unless($project->is_archived)<a class="pm-action border" href="#material-{{ $material->id }}" data-pm-open-editor aria-label="Edit {{ $material->item_name }}">Edit Material</a>@endunless</article>@endforeach</div>
        @endif
        @unless($project->is_archived)@foreach($project->materials as $material)@include('admin-pages.admin-project-management.partials.material-form')@endforeach
@endunless
    </section>
    <section class="text-sm text-slate-600" aria-label="Project activity">
<p>Project information last updated: <span class="font-medium">{{ $project->updated_at?->format('M j, Y, g:i A') ?? 'Not recorded' }}</span>
</p>
</section>
    @include('admin-pages.admin-project-management.partials.archive-dialog')
</div>
</x-dashboard-layout>
