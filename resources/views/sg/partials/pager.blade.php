@if ($p->lastPage() > 1)
    <div class="pager">
        @if ($p->onFirstPage())<span class="dis">‹</span>@else<a href="{{ $p->previousPageUrl() }}">‹</a>@endif
        @foreach (range(max(1, $p->currentPage() - 2), min($p->lastPage(), $p->currentPage() + 2)) as $i)
            @if ($i === $p->currentPage())<span class="on">{{ $i }}</span>@else<a href="{{ $p->url($i) }}">{{ $i }}</a>@endif
        @endforeach
        @if ($p->currentPage() + 2 < $p->lastPage())<span>…</span><a href="{{ $p->url($p->lastPage()) }}">{{ $p->lastPage() }}</a>@endif
        @if ($p->hasMorePages())<a href="{{ $p->nextPageUrl() }}">›</a>@else<span class="dis">›</span>@endif
    </div>
@endif
