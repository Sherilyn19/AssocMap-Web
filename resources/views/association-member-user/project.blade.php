<x-dashboard-layout title="Project Details" topbar-title="Project Details">
<div data-member-workspace class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <a class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-800 underline" href="{{ route('member.projects') }}">← Back to projects</a>
    @include('shared.projects.details', ['readOnly' => true])
</div>
</x-dashboard-layout>
