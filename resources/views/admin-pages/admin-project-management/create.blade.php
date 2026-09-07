{{-- Create and edit share the form partial so field rules and error presentation stay aligned. --}}
<x-dashboard-layout title="Create Project">
<div data-pm-page class="pm-page mx-auto max-w-4xl space-y-5">
<nav aria-label="Breadcrumb" class="flex flex-wrap gap-1 text-sm text-slate-600">
<a class="hover:underline" href="{{ route('projects.index') }}">Project Management</a>
<span aria-hidden="true"> / </span>
<span aria-current="page">Create Project</span>
</nav>
@include('admin-pages.admin-project-management.partials.feedback')
@include('admin-pages.admin-project-management.partials.form', ['action'=>route('projects.store'),'method'=>'POST','submitLabel'=>'Create Project','project'=>null])
</div>
</x-dashboard-layout>
