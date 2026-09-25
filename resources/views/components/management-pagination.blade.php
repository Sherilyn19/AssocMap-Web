@props(['records', 'numbered' => false, 'label' => 'Record pagination'])
{{-- Keep orientation and navigation visible even for an empty or single-page register. --}}
{{-- Each list can set its own navigation label while sharing this layout.
     Laravel creates the page links from the supplied records. --}}
<footer class="space-y-3 border-t border-slate-200 bg-slate-50 px-4 py-4 sm:px-5" aria-label="{{ $label }}">
    <p class="text-sm text-slate-600">Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }} records · Page {{ $records->currentPage() }} of {{ $records->lastPage() }}</p>
    @if($records->hasPages())
        {{ $numbered ? $records->onEachSide(1)->links('admin-pages.admin-member-management.partials.pagination', ['paginationLabel' => $label]) : $records->links() }}
    @else
        <div class="flex flex-wrap gap-2" aria-label="{{ $label }}">
            <button disabled class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-400">Previous</button>
            <span aria-current="page" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white">1</span>
            <button disabled class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-400">Next</button>
        </div>
    @endif
</footer>
