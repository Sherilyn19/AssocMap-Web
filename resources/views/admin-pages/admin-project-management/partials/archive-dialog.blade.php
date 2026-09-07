{{-- Include once per page. JavaScript submits the selected archive form after confirmation; Cancel is the initial focus. --}}
<dialog data-pm-archive-dialog aria-labelledby="archive-title" aria-describedby="archive-description" class="pm-dialog w-[calc(100%-2rem)] max-w-lg rounded-xl border border-slate-200 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <h2 id="archive-title" class="text-lg font-bold">Archive Project?</h2>
    <p class="mt-3 break-words font-semibold" data-pm-archive-name>
</p>
    <p id="archive-description" class="mt-3 text-sm text-slate-600">This project will no longer appear in active project records. Its historical records and audit trail will be preserved. Archived projects are read-only.</p>
    <div class="mt-5 flex flex-wrap justify-end gap-2">
<button type="button" class="pm-action border" data-pm-archive-cancel autofocus>Cancel</button>
<button type="button" class="pm-primary !bg-red-800" data-pm-archive-confirm>Archive Project</button>
</div>
</dialog>
