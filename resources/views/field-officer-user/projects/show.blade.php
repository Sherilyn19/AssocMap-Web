<x-dashboard-layout title="Project Details">
<div class="fo-coverage fo-projects space-y-6">
    <a class="fo-action" href="{{ route('officer.projects.index') }}">
        Back to Projects and Delivery
    </a>

    {{-- Delivery saves and validation errors return to this existing page. --}}
    @include('shared.membership.partials.feedback')

    {{-- Preserve the existing project, training, and delivery information. --}}
    @include('field-officer-user.projects.details')
</div>
</x-dashboard-layout>