<header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-widest text-slate-600">My association · BFAR SAAD Phase II</span>
    <h1 class="mt-3 break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $heading }}</h1>
    <p class="mt-2 break-words font-medium text-slate-700">{{ $association?->name ?? 'Association not assigned' }}</p>
    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $description }}</p>
</header>
@include('shared.partials.feedback')
@if (!$association)
    <section role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">Your account has no association assigned. Contact the System Administrator to link your account. Association records are unavailable until then.</section>
@elseif ($association->is_archived)
    <section role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">This association is archived. Records are available for reference; membership submission and review are unavailable.</section>
@endif
