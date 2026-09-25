<x-dashboard-layout title="Area Management" topbar-title="Area Management">
    @include('admin-pages.admin-area-management.page-header')
    <section class="rounded-xl border border-assocmap-border bg-white p-6" role="alert">
        <h2 class="text-lg font-bold text-slate-900">Area Management is temporarily unavailable</h2>
        <p class="my-4">Records could not be loaded. Please try again.</p>
        <a href="{{ route('areas.index') }}" class="text-assocmap-primary underline">Reload Area Management</a>
    </section>
</x-dashboard-layout>
