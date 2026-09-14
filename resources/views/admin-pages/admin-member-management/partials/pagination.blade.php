{{-- Laravel supplies a bounded page window and URLs that retain the current filters. --}}
<nav class="flex flex-wrap items-center gap-2" role="navigation" aria-label="Member pagination">
    @if($paginator->onFirstPage())
        <button disabled class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-400">Previous</button>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500">Previous</a>
    @endif

    @foreach($elements as $element)
        @if(is_string($element))
            <span class="px-2 text-sm text-slate-500" aria-hidden="true">{{ $element }}</span>
        @else
            @foreach($element as $page => $url)
                @if($page === $paginator->currentPage())
                    <span aria-current="page" aria-label="Page {{ $page }}" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" aria-label="Go to page {{ $page }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500">Next</a>
    @else
        <button disabled class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-400">Next</button>
    @endif
</nav>
