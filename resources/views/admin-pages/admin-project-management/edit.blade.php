<x-dashboard-layout title="Edit Project">
    {{-- Archived projects are intentionally excluded from the edit route. --}}
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <a href="{{ route('projects.show', $project) }}" class="text-sm font-semibold text-assocmap-primary hover:underline">&larr; Back to Project Details</a>
            <h1 class="mt-2 text-2xl font-bold text-assocmap-text">Edit Project</h1>
            <p class="mt-1 text-sm text-assocmap-secondary">
                Update project information while preserving the existing database relationships.
            </p>
        </div>

        @include('admin-pages.admin-project-management.partials.form', [
            'action' => route('projects.update', $project),
            'method' => 'PUT',
            'submitLabel' => 'Save Changes',
            'project' => $project,
        ])
    </div>
</x-dashboard-layout>