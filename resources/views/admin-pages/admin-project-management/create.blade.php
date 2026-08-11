<x-dashboard-layout title="Create Project">
    {{-- Create form keeps Program Component and Commodity Type visibly distinct. --}}
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <a href="{{ route('projects.index') }}" class="text-sm font-semibold text-assocmap-primary hover:underline">&larr; Back to Project Management</a>
            <h1 class="mt-2 text-2xl font-bold text-assocmap-text">Create Project</h1>
            <p class="mt-1 text-sm text-assocmap-secondary">
                Enter the project record and select its Program Component and actual fisheries commodity.
            </p>
        </div>

        @include('admin-pages.admin-project-management.partials.form', [
            'action' => route('projects.store'),
            'method' => 'POST',
            'submitLabel' => 'Create Project',
            'project' => null,
        ])
    </div>
</x-dashboard-layout>