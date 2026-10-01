<section class="overflow-hidden rounded-xl border border-slate-200 bg-white" aria-label="Training records">
    <div class="border-b border-slate-200 p-5"><h2 class="text-lg font-semibold">Training records <span class="text-slate-500">({{ $trainings->total() }})</span></h2><p class="mt-1 text-sm text-slate-500">Schedules, attendance, and project details in one view.</p></div>
    <div class="am-training-table-wrap" tabindex="0" role="region" aria-label="Training records table">
    <table class="am-training-table am-training-register"><thead><tr><th>Training</th><th>Schedule</th><th>Training purpose</th><th>Attendance recorded</th><th><span class="sr-only">Details</span></th></tr></thead><tbody>
    @forelse ($trainings as $training)
        @php($percentage = $training->registered_count ? (int) round($training->recorded_count / $training->registered_count * 100) : 0)
        <tr>
            <td><a data-training-open class="font-semibold text-slate-900 hover:underline" href="{{ route($readOnly ? 'member.trainings.show' : 'officer.trainings.show', $training) }}">{{ $training->title }}</a><span class="am-training-table__sub">{{ $training->training_type ?: 'Type not provided' }} · {{ $training->programComponent?->name ?? 'Component not provided' }}</span>@if(! $readOnly)<span class="am-training-table__sub">{{ $training->association->name }}</span>@endif<span class="am-training-table__sub">{{ $training->venue ?: 'Venue not provided' }}</span></td>
            <td>@include('shared.trainings.dates', ['compact' => true])</td>
            <td><span class="am-training-mobile-label">Training purpose</span>@include('shared.trainings.category') @if($training->is_archived)<span class="am-training-table__sub">Archived</span>@endif</td>
            <td><span class="am-training-mobile-label">Attendance recorded</span><div class="am-training-table__progress"><span class="text-xs text-slate-600">{{ $training->registered_count ? $training->recorded_count.' / '.$training->registered_count.' recorded · '.$percentage.'%' : 'No participants' }}</span><progress class="am-project-progress mt-2" value="{{ $percentage }}" max="100" aria-label="Attendance recorded for {{ $training->title }}">{{ $percentage }}%</progress></div></td>
            <td><a data-training-open class="am-user-button am-user-button-secondary" href="{{ route($readOnly ? 'member.trainings.show' : 'officer.trainings.show', $training) }}">View details<span class="sr-only">: {{ $training->title }}</span></a></td>
        </tr>
    @empty
        <tr><td colspan="5" class="py-10 text-center"><strong>No trainings found</strong><span class="am-training-table__sub">No records found for the selected criteria. Try resetting your filters.</span></td></tr>
    @endforelse
    </tbody></table>
    </div>
    <x-management-pagination :records="$trainings" label="Training records pagination" />
</section>
