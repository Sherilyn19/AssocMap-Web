<x-dashboard-layout title="Project Details" topbar-title="Project Details">
<div data-member-workspace class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <a class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-800 underline" href="{{ route('member.projects') }}">← Back to projects</a>
    @include('association-member-user.partials.header', ['heading' => $project->title, 'description' => 'Project information and recorded materials. Read-only access.'])
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold">Project details</h2>@include('shared.partials.badge', ['label' => $project->is_archived ? 'Archived' : $project->status?->status_name])</div>
        <dl class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['Commodity' => $project->commodity_type, 'Program component' => $project->programComponent?->name, 'Implementation date' => $project->implementation_date?->format('F j, Y'), 'Termination date' => $project->terminated_on?->format('F j, Y')] as $label => $value)
                <div class="min-w-0"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-2 break-words font-medium">{{ $value ?: 'Not recorded' }}</dd></div>
            @endforeach
        </dl>
        <h3 class="mt-6 text-sm text-slate-500">Remarks</h3><p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6">{{ $project->remarks ?: 'No remarks recorded.' }}</p>
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-semibold">Project materials</h2>
        <ul class="mt-4 divide-y divide-slate-100">
            @forelse ($project->materials as $material)
                <li class="grid gap-4 py-5 sm:grid-cols-2 lg:grid-cols-4"><div><h3 class="break-words font-semibold">{{ $material->item_name }}</h3><div class="mt-2">@include('shared.partials.badge', ['label' => $material->status?->status_name])</div></div><div><p class="text-sm text-slate-500">Quantity</p><p class="mt-1">{{ number_format($material->quantity, 2) }} {{ $material->unit }}</p></div><div><p class="text-sm text-slate-500">Unit cost / calculated total</p><p class="mt-1">{{ $material->unit_cost !== null ? '₱'.number_format($material->unit_cost, 2).' / ₱'.number_format($material->quantity * $material->unit_cost, 2) : 'Not recorded' }}</p></div><div><p class="text-sm text-slate-500">Delivery date</p><p class="mt-1">{{ $material->delivery_date?->format('M j, Y') ?? 'Not recorded' }}</p></div></li>
            @empty <li class="py-8 text-sm text-slate-500">No materials have been recorded for this project yet.</li> @endforelse
        </ul>
    </section>
</div>
</x-dashboard-layout>
