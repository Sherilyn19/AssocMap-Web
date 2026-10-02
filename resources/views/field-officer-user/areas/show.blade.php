<x-dashboard-layout title="Area Details">

<div class="space-y-6">


<a class="am-user-button am-user-button-secondary"
href="{{ route('officer.areas.index') }}">
Back to My Areas
</a>


<header>

<span class="am-officer-eyebrow">
Area Details
</span>


<h1>
{{ $area->name }}
</h1>

</header>



<section class="rounded-xl border border-slate-200 bg-white p-6">


<h2 class="text-lg font-semibold mb-4">
Assigned Associations
</h2>



<div class="grid gap-4">


@forelse($area->associations as $association)


<div class="rounded-xl border border-slate-200 p-4">


<h3 class="font-semibold">
{{ $association->name }}
</h3>


<p class="text-sm text-slate-600">
Barangay:
{{ $association->subUnit?->name ?? 'Not recorded' }}
</p>


<p class="text-sm text-slate-600">
Program:
{{ $association->programComponent?->name ?? 'Not recorded' }}
</p>


</div>


@empty

<p class="text-sm text-slate-500">
No assigned associations found.
</p>

@endforelse


</div>


</section>


</div>

</x-dashboard-layout>