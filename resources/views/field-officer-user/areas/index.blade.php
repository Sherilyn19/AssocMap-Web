<x-dashboard-layout title="My Areas">

<div class="space-y-6">

<header>
    <span class="am-officer-eyebrow">
        BFAR SAAD Phase II
    </span>

    <h1>
        My Areas
    </h1>

    <p class="mt-1 text-sm leading-6 text-slate-600">
        View municipalities connected to your current association assignments.
    </p>
</header>


<section class="rounded-xl border border-slate-200 bg-white overflow-hidden">


<div class="border-b border-slate-200 p-5">

<h2 class="text-lg font-semibold">
Assigned Areas
</h2>

<p class="text-sm text-slate-600 mt-1">
Municipalities under your assigned associations.
</p>

</div>



<div class="grid gap-4 p-5">


@forelse($areas as $area)

<a href="{{ route('officer.areas.show', $area->id) }}"
class="block rounded-xl border border-slate-200 bg-white p-5 hover:shadow-md transition">


<h3 class="text-lg font-semibold">
{{ $area->name }}
</h3>


<p class="mt-2 text-sm text-slate-600">
Assigned Associations:
{{ $area->associations_count }}
</p>


<p class="mt-3 text-sm font-medium underline">
View details
</p>


</a>


@empty

<p class="text-sm text-slate-500">
No assigned areas found.
</p>

@endforelse


</div>


<div class="p-5">

<x-management-pagination
:records="$areas"
label="Area records pagination"
/>

</div>


</section>


</div>

</x-dashboard-layout>