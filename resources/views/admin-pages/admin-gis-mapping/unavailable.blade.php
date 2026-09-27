<x-dashboard-layout title="GIS Mapping" topbar-title="GIS Mapping">
    <section class="rounded-xl border border-slate-200 bg-white p-6" role="alert">
        <h1 class="text-xl font-bold">GIS Mapping is temporarily unavailable</h1>
        <p class="my-4 text-slate-600">Location records could not be loaded. Please try again.</p>
        <a href="{{ route('gis.index') }}" class="text-assocmap-primary underline">Reload GIS Mapping</a>
    </section>
</x-dashboard-layout>
