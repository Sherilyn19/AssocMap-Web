{{-- Keep this dialog open on a failed save so the error remains visible outside the More menu. --}}
<dialog data-association-confirm aria-labelledby="association-confirm-title" aria-describedby="association-confirm-description" class="am-association-dialog w-[calc(100%-2rem)] max-w-lg rounded-xl border border-slate-200 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <h2 id="association-confirm-title" class="text-lg font-bold">Confirm association change</h2>
    <p class="mt-3 break-words font-semibold" data-confirm-record></p>
    <p id="association-confirm-description" class="mt-3 text-sm leading-6 text-slate-600"></p>
    <div data-form-error role="alert" class="mt-4 hidden rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"></div>
    <div class="mt-5 flex flex-wrap justify-end gap-2">
        <button type="button" data-confirm-cancel class="am-association-action border border-slate-300" autofocus>Cancel</button>
        <button type="button" data-confirm-submit class="am-association-primary">Confirm</button>
    </div>
</dialog>
