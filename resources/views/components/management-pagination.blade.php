@props(['records'])
{{-- Keep orientation and navigation visible even for an empty or single-page register. --}}
<footer class="space-y-3 border-t border-slate-200 bg-slate-50 px-4 py-4 sm:px-5" aria-label="Record pagination">
    <p class="text-sm text-slate-600">Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }} records · Page {{ $records->currentPage() }} of {{ $records->lastPage() }}</p>
    @if($records->hasPages())
        {{ $records->links() }}
    @else
        <div class="flex gap-2" aria-label="Pagination">
            <button disabled class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-400">Previous</button>
            <span aria-current="page" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white">1</span>
            <button disabled class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-400">Next</button>
        </div>
    @endif
</footer>
