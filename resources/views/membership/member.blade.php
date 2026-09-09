<x-dashboard-layout title="Member Record">
<div class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
    <a class="text-blue-800 underline" href="{{ route('membership.index') }}">Back to Member Management</a>
    <h1 class="text-2xl font-bold">Official Member #{{ $member->id }}</h1>
    <section class="rounded-xl border bg-white p-6">@include('membership.partials.profile', ['record' => $member])</section>
    <p class="text-slate-600">{{ $member->is_archived ? 'Archived historical record' : 'Current member' }} · Registered {{ $member->date_registered?->format('F j, Y') }}</p>
</div>
</x-dashboard-layout>
