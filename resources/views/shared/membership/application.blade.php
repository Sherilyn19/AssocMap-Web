<x-dashboard-layout title="Membership Application" topbar-title="Membership Application">
<div data-member-workspace class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
    <a class="inline-flex min-h-11 items-center text-blue-800 underline" href="{{ route(session('auth_user.role_name') === 'Association Member' ? 'member.applications' : 'membership.index') }}">← Back to applications</a>
    <header class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><p class="mb-2 break-words text-sm text-slate-500">{{ $application->association->name }}</p><h1 class="text-2xl font-bold">Application #{{ $application->id }}</h1><div class="mt-3">@include('shared.partials.badge', ['label' => $application->status->status_name])</div></header>
    @include('shared.membership.partials.feedback')
    <section class="rounded-xl border bg-white p-6">@include('shared.membership.partials.profile', ['record' => $application])</section>
    @if ($application->reviewed_at)
        <section class="space-y-2 rounded-xl border bg-white p-6">
            <h2 class="text-lg font-bold">Review Record</h2>
            <p>Reviewed by {{ $application->reviewer?->first_name }} {{ $application->reviewer?->last_name }} on {{ $application->reviewed_at->format('F j, Y g:i A') }}.</p>
            @if($application->rejection_reason)<p class="whitespace-pre-wrap break-words">Reason: {{ $application->rejection_reason }}</p>@endif
            @if($application->member)<a class="inline-block text-blue-800 underline" href="{{ route('membership.members.show', $application->member) }}">View official member record</a>@endif
        </section>
    @elseif ($canReview)
        <form data-representative-review method="POST" action="{{ route('membership.applications.review', $application) }}" class="space-y-5 rounded-xl border bg-white p-6">
            @csrf @method('PATCH')
            <h2 class="text-lg font-bold">Representative Review</h2>
            <p class="text-sm text-slate-600">Only {{ $application->association->representative?->first_name }} {{ $application->association->representative?->last_name }}, the current designated representative, should use this form. Approval creates an official member; rejection retains this application and its reason. A recorded decision cannot be changed here.</p>
            <label class="block"><span class="block font-semibold">Decision *</span><select name="decision" required class="mt-1 w-full rounded-lg border border-slate-300 p-3"><option value="">Select decision</option><option value="Approved" @selected(old('decision') === 'Approved')>Approve</option><option value="Rejected" @selected(old('decision') === 'Rejected')>Reject</option></select></label>
            <label class="block"><span class="block font-semibold">Rejection reason (required when rejecting)</span><textarea name="rejection_reason" maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 p-3">{{ is_string(old('rejection_reason')) ? old('rejection_reason') : '' }}</textarea></label>
            {{-- Never repopulate this field after a failed request. --}}
            <label class="block"><span class="block font-semibold">Private review passphrase *</span><input type="password" name="review_passphrase" autocomplete="off" required maxlength="72" class="mt-1 w-full rounded-lg border border-slate-300 p-3"></label>
            <label class="flex items-start gap-3 text-sm leading-6"><input class="mt-1" type="checkbox" required name="decision_confirmed" value="1">I have checked this application and understand that the recorded decision cannot be changed here.</label>
            <button class="am-user-button am-user-button-primary">Confirm and record decision</button>
        </form>
    @elseif ($canUnlockReview ?? false)
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Awaiting representative review</h2><p class="mt-2 text-sm leading-6 text-slate-600">The shared association account does not grant approval authority. Only the designated representative may open review actions.</p>
            <details class="mt-4"><summary class="cursor-pointer py-3 font-semibold text-blue-800">Designated representative access</summary>
                <form method="POST" action="{{ route('membership.applications.review-access', $application) }}" class="mt-3 space-y-4">
                    @csrf
                    <label class="block text-sm font-semibold">Private review passphrase<input class="am-user-control mt-2" type="password" name="review_passphrase" required maxlength="72" autocomplete="off"></label>
                    <button class="am-user-button am-user-button-primary">Verify representative</button>
                </form>
            </details>
        </section>
    @else
        <p class="rounded-xl border bg-slate-50 p-5 text-slate-700">This application is awaiting its designated representative's review. Review requires an active association and representative. Field Officers and administrators have read-only review access.</p>
    @endif
</div>
</x-dashboard-layout>
