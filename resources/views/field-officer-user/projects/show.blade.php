<x-dashboard-layout title="Project Details">
<div class="space-y-6">
<a class="am-user-button am-user-button-secondary" href="{{ route('officer.projects.index') }}">Back to Projects</a>
@include('shared.membership.partials.feedback')
@include('field-officer-user.projects.details')
</div>
</x-dashboard-layout>
