<x-dashboard-layout title="Submit Membership Application" topbar-title="Submit Membership Application">
<div data-member-workspace class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
    <a class="inline-flex min-h-11 items-center text-blue-800 underline" href="{{ route('member.applications') }}">← Back to applications</a>
    <header class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h1 class="text-2xl font-bold">Submit Membership Application</h1><p class="mt-2 break-words text-sm leading-6 text-slate-600">{{ $association->name }} · Submission creates a Pending application. It does not add an official member.</p></header>
    @include('shared.membership.partials.feedback')
    <form method="POST" action="{{ route('membership.applications.store') }}" class="space-y-6 rounded-xl border bg-white p-6">
        @csrf
        {{-- The association is derived from login; there is deliberately no association selector. --}}
        @include('shared.membership.partials.profile-fields')
        <button class="am-user-button am-user-button-primary">Submit for review</button>
    </form>
</div>
</x-dashboard-layout>
