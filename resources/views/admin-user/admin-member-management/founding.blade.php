<x-dashboard-layout title="Register Founding Member">
<div class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
    <a class="text-blue-800 underline" href="{{ route('admin.associations.show', $association) }}">Back to association</a>
    <header><h1 class="text-2xl font-bold">Register Founding Member</h1><p class="mt-2 text-slate-600">{{ $association->name }} · This one-time exception registers the first official member. Representative designation and private review credentials are separate steps. All later members require the normal application review.</p></header>
    @include('shared.membership.partials.feedback')
    <form method="POST" action="{{ route('admin.founding-member.store', $association) }}" class="space-y-6 rounded-xl border bg-white p-6">
        @csrf
        @include('shared.membership.partials.profile-fields')
        <label class="block"><span class="block font-semibold">Founding-member justification *</span><textarea name="justification" required maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 p-3">{{ is_string(old('justification')) ? old('justification') : '' }}</textarea><span class="text-sm text-slate-600">Explain the basis for this initial registration. This explanation is retained in the audit history.</span>@error('justification')<span class="block text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label class="flex items-start gap-3"><input type="checkbox" name="profile_verified" value="1" required @checked(old('profile_verified')) class="mt-1"><span>I have verified this person's profile information for this association.</span></label>
        <button class="rounded-lg bg-slate-800 px-5 py-3 font-semibold text-white">Register First Official Member</button>
    </form>
</div>
</x-dashboard-layout>
