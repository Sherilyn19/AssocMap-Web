<x-dashboard-layout title="Training Details">
<div data-training-page class="pm-page mx-auto max-w-7xl space-y-5">
    <nav aria-label="Breadcrumb" class="text-sm text-slate-600"><a class="hover:underline" href="{{ route('trainings.index', ['scope' => $training->is_archived ? 'archived' : 'active']) }}">Training Management</a> / Training Details</nav>
    @include('admin-pages.admin-project-management.partials.feedback')
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0"><p class="mb-1 text-xs font-semibold uppercase text-slate-500">{{ $training->is_archived ? 'Archived training' : 'Training record' }} #{{ $training->id }}</p><h1 class="break-words text-2xl font-bold">{{ $training->title }}</h1><p class="mt-1 text-sm text-slate-600">{{ $training->association?->name ?? 'Association unavailable' }}</p></div>
        @if($writable)<a href="{{ route('trainings.edit', $training) }}" class="pm-primary">Edit Training</a>@endif
    </header>
    @if(!$writable)<div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $training->is_archived ? 'This training is archived. Restore it to manage participants and attendance.' : 'This association is archived or unavailable. Restore the association before making changes.' }}</div>@endif
    <section class="rounded-xl border border-slate-200 bg-white p-5" aria-label="Training information">
        <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach(['Program component' => $training->programComponent?->name, 'Training type' => $training->training_type, 'Training date' => $training->date_conducted?->format('M d, Y'), 'Venue' => $training->venue, 'Conducted by' => $training->conducted_by, 'Training cost' => $training->training_cost !== null ? '₱'.number_format((float)$training->training_cost, 2) : null] as $label => $value)
                <div class="min-w-0"><dt class="text-xs font-medium uppercase text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words text-sm text-slate-900">{{ $value ?: 'Not set' }}</dd></div>
            @endforeach
            <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs font-medium uppercase text-slate-500">Remarks</dt><dd class="mt-1 whitespace-pre-line break-words text-sm text-slate-700">{{ $training->remarks ?: 'No remarks.' }}</dd></div>
        </dl>
    </section>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Attendance summary">
        @foreach(['Participants' => $participants->total(), 'Present' => $counts->get('Present', 0), 'Absent' => $counts->get('Absent', 0), 'Pending' => $counts->get('Pending', 0)] as $label => $value)
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-sm text-slate-600">{{ $label }}</p><p class="mt-1 text-2xl font-bold">{{ $value }}</p></div>
        @endforeach
    </div>
    @if($writable)
    <section class="rounded-xl border border-slate-200 bg-white p-5" aria-label="Register participant">
        <h2 class="font-semibold">Register a participant</h2><p class="mt-1 text-sm text-slate-600">Choose an active member of {{ $training->association->name }}. Attendance starts as Pending.</p>
        @if($eligibleMembers->isNotEmpty())
        <form method="POST" action="{{ route('trainings.participants.store', $training) }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-0 flex-1"><label class="pm-label" for="participant-member">Association member</label><select id="participant-member" name="member_id" class="pm-input" required><option value="">Select a member</option>@foreach($eligibleMembers as $member)<option value="{{ $member->id }}" @selected((string)old('member_id') === (string)$member->id)>{{ $member->last_name }}, {{ $member->first_name }} {{ $member->middle_name }} (#{{ $member->id }})</option>@endforeach</select></div>
            <button type="submit" class="pm-primary">Add Participant</button>
        </form>
        @else<p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">No eligible members remain. All active members are already registered, or this association has no active members.</p>@endif
    </section>
    @endif
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white" aria-label="Participants and attendance">
        <div class="border-b border-slate-200 p-5"><h2 class="font-semibold">Participants and attendance</h2><p class="mt-1 text-sm text-slate-600">Record Present or Absent on or after the training date. Pending means attendance has not been recorded.</p></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th scope="col" class="px-5 py-3">Member</th><th scope="col" class="px-5 py-3">Attendance</th>@if($writable)<th scope="col" class="px-5 py-3">Actions</th>@endif</tr></thead><tbody class="divide-y divide-slate-100">
        @forelse($participants as $participant)
            @php($memberName = $participant->member ? trim($participant->member->first_name.' '.$participant->member->middle_name.' '.$participant->member->last_name) : 'Member unavailable')
            <tr><td class="px-5 py-4"><p class="font-medium">{{ $memberName }}</p>@if($participant->member?->is_archived)<p class="mt-1 text-xs text-amber-800">Archived member · attendance retained</p>@endif</td><td class="px-5 py-4">
                @if($writable)
                <form method="POST" action="{{ route('trainings.participants.attendance', [$training, $participant->id]) }}" class="flex flex-wrap items-end gap-2">
                    @csrf @method('PATCH')
                    <div><label for="attendance-{{ $participant->id }}" class="sr-only">Attendance for {{ $memberName }}</label><select id="attendance-{{ $participant->id }}" name="attendance_status_id" class="pm-input" required>@foreach($attendanceStatuses as $status)<option value="{{ $status->id }}" @selected($participant->attendance_status_id == $status->id) @disabled($status->status_name !== 'Pending' && !$training->canRecordAttendance())>{{ $status->status_name }}</option>@endforeach</select></div>
                    <button type="submit" class="pm-action border border-slate-300" aria-label="Save attendance for {{ $memberName }}">Save</button>
                </form>
                @else {{ $participant->attendanceStatus?->status_name ?? 'Unknown' }} @endif
            </td>
            @if($writable)<td class="px-5 py-4"><details><summary class="cursor-pointer text-sm font-medium text-red-800">Remove</summary><form method="POST" action="{{ route('trainings.participants.destroy', [$training, $participant->id]) }}" class="mt-2 space-y-2">@csrf @method('DELETE')<p class="max-w-xs text-xs text-slate-600">Remove {{ $memberName }} and their attendance from this training?</p><button class="pm-action border border-red-200 text-red-800" type="submit">Confirm removal</button></form></details></td>@endif</tr>
        @empty<tr><td colspan="{{ $writable ? 3 : 2 }}" class="px-5 py-10 text-center text-slate-500">No participants registered yet.</td></tr>@endforelse
        </tbody></table></div>
        <x-management-pagination :records="$participants" :numbered="true" label="Participant pagination" />
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        @if($training->is_archived)
            <form method="POST" action="{{ route('trainings.restore', $training) }}">@csrf @method('PATCH')<p class="mb-3 text-sm text-slate-600">Restore this training to the active register. The association must be active.</p><button type="submit" class="pm-primary">Restore Training</button></form>
        @else
            <details><summary class="cursor-pointer font-semibold text-slate-700">Archive this training</summary><p class="my-3 text-sm text-slate-600">Archiving removes this training from the active register. Its participants and attendance remain available, and you can restore it later.</p><form method="POST" action="{{ route('trainings.archive', $training) }}">@csrf @method('PATCH')<button type="submit" class="pm-action border border-slate-300">Confirm Archive</button></form></details>
        @endif
    </section>
</div>
</x-dashboard-layout>
