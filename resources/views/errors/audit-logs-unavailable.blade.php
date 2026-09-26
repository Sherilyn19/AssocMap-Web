<x-dashboard-layout title="Audit Logs">
    <section role="alert" class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6">
        <h1 class="text-xl font-bold text-slate-900">Audit Logs temporarily unavailable</h1>
        <p class="mt-2 text-sm text-slate-600">The activity history could not be loaded. Please try again.</p>
        <a href="{{ route('admin.audit-logs.index') }}" class="pm-primary mt-4 inline-flex">Try again</a>
    </section>
</x-dashboard-layout>
