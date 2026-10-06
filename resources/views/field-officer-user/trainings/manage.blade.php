
<section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="text-lg font-semibold">Attendance</h2>
@if (! $training->canRecordAttendance())<p id="attendance-timing" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Present and Absent attendance can only be recorded on or after the training date. Participants may be registered as Pending.{{ $training->date_conducted ? '' : ' The training date has not been recorded.' }}</p>@endif
@if ($editable && $members->isEmpty())
<p class="my-4 text-sm text-slate-600">No additional active members are available to register.</p>
@elseif ($editable)
<form data-training-write method="POST" action="{{ route('officer.trainings.participants.store', $training) }}" class="my-4 flex flex-wrap items-end gap-3">@csrf
<label class="text-sm">Register an association member<select required name="member_id" class="mt-2 block max-w-full rounded-lg border border-slate-300 p-3"><option value="">Choose a member</option>@foreach ($members as $member)<option value="{{ $member->id }}" @selected(old('member_id') == $member->id)>{{ $member->last_name }}, {{ $member->first_name }}</option>@endforeach</select></label>
<button class="am-officer-button">Register participant</button></form>
@endif
<div class="overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable records"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Member</th><th class="p-3">Attendance</th></tr></thead><tbody>
@forelse ($participants as $participant)
<tr class="border-t"><td class="p-3">{{ $participant->member->last_name }}, {{ $participant->member->first_name }}</td><td class="p-3">
@if ($editable && ! $participant->member->is_archived)
<form data-training-write method="POST" action="{{ route('officer.trainings.participants.attendance', [$training, $participant]) }}" class="flex flex-wrap gap-2">@csrf @method('PATCH')<input type="hidden" name="_participant_id" value="{{ $participant->id }}"><select aria-label="Attendance for {{ $participant->member->first_name }}" name="attendance_status_id" aria-describedby="attendance-error-{{ $participant->id }}{{ ! $training->canRecordAttendance() ? ' attendance-timing' : '' }}" aria-invalid="{{ old('_participant_id') == $participant->id && $errors->has('attendance_status_id') ? 'true' : 'false' }}" class="rounded-lg border border-slate-300 p-2">@foreach ($statuses as $status)<option value="{{ $status->id }}" @disabled($status->status_name !== 'Pending' && ! $training->canRecordAttendance()) @selected((old('_participant_id') == $participant->id ? old('attendance_status_id') : $participant->attendance_status_id) == $status->id)>{{ $status->status_name }}</option>@endforeach</select><button class="am-officer-button">Save attendance</button><p id="attendance-error-{{ $participant->id }}" class="w-full text-sm text-red-800">{{ old('_participant_id') == $participant->id ? $errors->first('attendance_status_id') : '' }}</p></form>
@else
@include('shared.partials.badge', ['label' => $participant->attendanceStatus?->status_name])
@endif
</td></tr>
@empty
<tr><td colspan="2" class="p-4">No participants registered.</td></tr>
@endforelse
</tbody></table></div><div class="mt-4">{{ $participants->links() }}</div></section>
