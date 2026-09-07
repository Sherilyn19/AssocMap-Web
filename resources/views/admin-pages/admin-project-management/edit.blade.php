{{-- The shared form emits a PUT override for this edit route; old input takes priority after validation errors. --}}
<x-dashboard-layout title="Edit Project">
<div data-pm-page class="pm-page mx-auto max-w-4xl space-y-5">
<nav aria-label="Breadcrumb" class="flex flex-wrap gap-1 text-sm text-slate-600">
<a class="hover:underline" href="{{ route('projects.index') }}">Project Management</a>
<span aria-hidden="true"> / </span>
<a class="hover:underline" href="{{ route('projects.show', $project) }}">{{ $project->title }}</a>
<span aria-hidden="true"> / </span>
<span aria-current="page">Edit</span>
</nav>
@include('admin-pages.admin-project-management.partials.feedback')
@include('admin-pages.admin-project-management.partials.form', ['action'=>route('projects.update',$project),'method'=>'PUT','submitLabel'=>'Save Changes','project'=>$project])
</div>
</x-dashboard-layout>
