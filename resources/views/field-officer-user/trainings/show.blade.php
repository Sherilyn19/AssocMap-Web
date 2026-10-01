<x-dashboard-layout title="Training Details">
<div class="space-y-6">
<a class="am-user-button am-user-button-secondary" href="{{ route('officer.trainings.index') }}">Back to Training Records</a>
@include('shared.membership.partials.feedback')
@include('field-officer-user.trainings.details')
</div>
@include('shared.projects.dialog')
</x-dashboard-layout>
