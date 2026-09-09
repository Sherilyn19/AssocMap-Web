<x-dashboard-layout title="Membership Application">
<div class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
    <a class="text-blue-800 underline" href="{{ route('membership.index') }}">Back to Member Management</a>
    <header><h1 class="text-2xl font-bold">Application #{{ $application->id }}</h1><p class="mt-2 font-semibold text-slate-600">{{ $application->status->status_name }}</p></header>
    @include('membership.partials.feedback')
    <section class="rounded-xl border bg-white p-6">@include('membership.partials.profile', ['record' => $application])</section>
    @if ($application->reviewed_at)
        <section class="space-y-2 rounded-xl border bg-white p-6">
            <h2 class="text-lg font-bold">Review Record</h2>
            <p>Reviewed by {{ $application->reviewer?->first_name }} {{ $application->reviewer?->last_name }} on {{ $application->reviewed_at->format('F j, Y g:i A') }}.</p>
            @if($application->rejection_reason)<p class="whitespace-pre-wrap break-words">Reason: {{ $application->rejection_reason }}</p>@endif
            @if($application->member)<a class="inline-block text-blue-800 underline" href="{{ route('membership.members.show', $application->member) }}">View official member record</a>@endif
        </section>
    @elseif ($canReview)
        <form method="POST" action="{{ route('membership.applications.review', $application) }}" class="space-y-5 rounded-xl border bg-white p-6">
            @csrf @method('PATCH')
            <h2 class="text-lg font-bold">Representative Review</h2>
            <p class="text-sm text-slate-600">Only {{ $application->association->representative?->first_name }} {{ $application->association->representative?->last_name }}, the current designated representative, should use this form. Approval creates an official member; rejection retains this application and its reason. A recorded decision cannot be changed here.</p>
            <label class="block"><span class="block font-semibold">Decision *</span><select name="decision" required class="mt-1 w-full rounded-lg border border-slate-300 p-3"><option value="">Select decision</option><option value="Approved" @selected(old('decision') === 'Approved')>Approve</option><option value="Rejected" @selected(old('decision') === 'Rejected')>Reject</option></select></label>
            <label class="block"><span class="block font-semibold">Rejection reason (required when rejecting)</span><textarea name="rejection_reason" maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 p-3">{{ is_string(old('rejection_reason')) ? old('rejection_reason') : '' }}</textarea></label>
            {{-- Never repopulate this field after a failed request. --}}
            <label class="block"><span class="block font-semibold">Private review passphrase *</span><input type="password" name="review_passphrase" autocomplete="off" required maxlength="72" class="mt-1 w-full rounded-lg border border-slate-300 p-3"></label>
            <button class="rounded-lg bg-slate-800 px-5 py-3 font-semibold text-white">Confirm and Record Decision</button>
        </form>
    @else
        <p class="rounded-xl border bg-slate-50 p-5 text-slate-700">This application is awaiting its designated representative's review. Review requires an active association and representative. Field Officers and administrators have read-only review access.</p>
    @endif
</div>
</x-dashboard-layout>
