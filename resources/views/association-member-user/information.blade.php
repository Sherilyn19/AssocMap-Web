<x-dashboard-layout title="Association Profile" topbar-title="Association Profile">
<div data-member-workspace class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    @include('association-member-user.partials.header', ['heading' => 'Association Profile', 'description' => 'Your association’s registration, location and designated contacts. This information is read-only.'])
    @if ($association)
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold">Association profile</h2>@include('shared.partials.badge', ['label' => $association->is_archived ? 'Archived' : $association->status?->status_name])</div>
        <dl class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['Association name' => $association->name, 'Municipality' => $association->areaUnit?->name, 'Barangay' => $association->subUnit?->name, 'Program component' => $association->programComponent?->name, 'Address' => $association->address, 'Date joined' => $association->date_joined?->format('F j, Y')] as $label => $value)
                <div class="min-w-0"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-2 break-words font-medium">{{ $value ?: 'Not recorded' }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section class="grid gap-4 md:grid-cols-2" aria-label="Association contacts">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-medium text-slate-500">Assigned Field Officer</h2><p class="mt-2 break-words text-lg font-semibold">{{ $association->fieldOfficer?->name ?? 'Not assigned' }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-medium text-slate-500">Association Representative</h2><p class="mt-2 break-words text-lg font-semibold">{{ $association->representative && $association->representative->association_id == $association->id && !$association->representative->is_archived ? trim($association->representative->first_name.' '.$association->representative->last_name) : 'Not designated' }}</p><p class="mt-2 text-sm text-slate-500">Reviews membership applications using a separate private credential.</p></div>
    </section>
    @endif
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-semibold">Association account</h2><p class="mt-2 break-words">{{ session('auth_user.name') }}</p><p class="mt-1 break-words text-sm text-slate-600">{{ session('auth_user.email') }}</p><p class="mt-3 text-sm text-slate-500">This shared account represents your association. Contact the System Administrator for account corrections.</p></section>
</div>
</x-dashboard-layout>
