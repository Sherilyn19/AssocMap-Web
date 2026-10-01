{{-- Native dialog provides keyboard focus containment and Escape support. --}}
<dialog data-gis-publication class="gis-dialog rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl" aria-labelledby="gis-publication-title" aria-describedby="gis-publication-description">
    <header class="border-b border-slate-200 px-5 py-4">
        <h2 id="gis-publication-title" class="text-lg font-bold">Change publication</h2>
    </header>
    <div class="space-y-3 p-5">
        <p data-publication-name class="font-semibold break-words"></p>
        <p id="gis-publication-description" class="text-sm text-slate-600"></p>
        <p data-publication-message hidden role="alert" tabindex="-1" class="rounded-lg bg-amber-50 p-3 text-sm text-slate-800"></p>
        <a data-publication-reload hidden href="{{ route(request()->routeIs('gis.officer.*') ? 'gis.officer.index' : 'gis.index') }}" class="text-sm text-assocmap-primary underline">Reload GIS Mapping</a>
    </div>
    <footer class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
        <button data-publication-cancel type="button" class="gis-button">Cancel</button>
        <button data-publication-confirm type="button" class="gis-button gis-save-button">Confirm</button>
    </footer>
</dialog>
