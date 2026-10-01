<x-dashboard-layout title="Member Record" topbar-title="Member Record">
<div data-member-workspace class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
    <a class="inline-flex min-h-11 items-center text-blue-800 underline" href="{{ route(session('auth_user.role_name') === 'Association Member' ? 'member.members' : 'membership.index') }}">← Back to members</a>
    <header class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><p class="mb-2 text-sm text-slate-500">{{ $member->association->name }}</p><h1 class="break-words text-2xl font-bold">{{ trim($member->first_name.' '.$member->last_name) }}</h1><div class="mt-3">@include('shared.partials.badge', ['label' => $member->is_archived ? 'Archived' : 'Active'])</div></header>
    <section class="rounded-xl border bg-white p-6">@include('shared.membership.partials.profile', ['record' => $member])</section>
    <p class="text-slate-600">{{ $member->is_archived ? 'Archived historical record' : 'Current member' }} · Registered {{ $member->date_registered?->format('F j, Y') }}</p>
</div>
</x-dashboard-layout>
