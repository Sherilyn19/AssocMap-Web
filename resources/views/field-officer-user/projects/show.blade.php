<x-dashboard-layout title="Project Details">
<div class="space-y-6"><a class="am-user-button am-user-button-secondary" href="{{ route('officer.projects.index') }}">Back to Projects</a><header><span class="am-officer-eyebrow">Project details</span><h1>{{ $project->title }}</h1></header>
@include('shared.membership.partials.feedback')
<dl class="grid gap-5 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
@foreach (['Association' => $project->association->name, 'Commodity' => $project->commodity_type, 'Program component' => $project->programComponent?->name, 'Implementation date' => $project->implementation_date?->format('M d, Y'), 'Status' => $project->is_archived ? 'Archived' : $project->status?->status_name, 'Termination date' => $project->terminated_on?->format('M d, Y'), 'Remarks' => $project->remarks] as $label => $value)
<div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1">@if($label === 'Status') @include('shared.partials.badge', ['label' => $value]) @else {{ $value ?? 'Not recorded' }} @endif</dd></div>
@endforeach
</dl>
<section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="text-lg font-semibold">Materials and delivery</h2><p class="mt-2 text-sm text-slate-600">Record the delivery date. Material definitions and the existing status remain read-only.</p>
<div class="mt-4 overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable records"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Material</th><th class="p-3">Quantity</th><th class="p-3">Unit cost (PHP)</th><th class="p-3">Recorded status</th><th class="p-3">Delivery date</th></tr></thead><tbody>
@forelse ($project->materials as $material)
<tr class="border-t"><td class="p-3">{{ $material->item_name }}</td><td class="p-3">{{ $material->quantity }} {{ $material->unit }}</td><td class="p-3">{{ $material->unit_cost ?? 'Not recorded' }}</td><td class="p-3">@include('shared.partials.badge', ['label' => $material->status?->status_name])</td><td class="p-3">
@if (! $project->is_archived && ! $project->association->is_archived)
<form method="POST" action="{{ route('officer.projects.delivery', [$project, $material]) }}" class="flex flex-wrap gap-2">@csrf @method('PATCH')<input type="hidden" name="_material_id" value="{{ $material->id }}"><input aria-label="Delivery date for {{ $material->item_name }}" type="date" name="delivery_date" aria-describedby="delivery-error-{{ $material->id }}" aria-invalid="{{ old('_material_id') == $material->id && $errors->has('delivery_date') ? 'true' : 'false' }}" value="{{ old('_material_id') == $material->id ? old('delivery_date') : $material->delivery_date?->format('Y-m-d') }}" class="rounded-lg border border-slate-300 p-2"><button class="am-officer-button">Save</button><p id="delivery-error-{{ $material->id }}" class="w-full text-sm text-red-800">{{ old('_material_id') == $material->id ? $errors->first('delivery_date') : '' }}</p></form>
@else
{{ $material->delivery_date?->format('M d, Y') ?? 'Not recorded' }}
@endif
</td></tr>
@empty
<tr><td colspan="5" class="p-4">No materials recorded.</td></tr>
@endforelse
</tbody></table></div></section></div>
</x-dashboard-layout>

