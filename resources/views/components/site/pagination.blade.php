@props(['paginator', 'base' => null])
{{--
    WordPress-style links: base + "page/N/" (base = "/vijesti/").
    Without base, ordinary ?page=N links are used (search).
--}}
@php
    $last = $paginator->lastPage();
    $current = $paginator->currentPage();
    $link = fn (int $n) => $base !== null
        ? ($n === 1 ? $base : "{$base}page/{$n}/")
        : $paginator->url($n);
    $window = collect(range(max(1, $current - 2), min($last, $current + 2)));
@endphp
@if ($last > 1)
    <nav aria-label="Stranice">
        <ul class="pagination">
            @if ($current > 1)
                <li><a href="{{ $link($current - 1) }}" rel="prev" aria-label="Prethodna">‹</a></li>
            @endif
            @if ($window->first() > 1)
                <li><a href="{{ $link(1) }}">1</a></li>
                @if ($window->first() > 2)<li><span class="gap">…</span></li>@endif
            @endif
            @foreach ($window as $n)
                <li>
                    @if ($n === $current)
                        <span class="current" aria-current="page">{{ $n }}</span>
                    @else
                        <a href="{{ $link($n) }}">{{ $n }}</a>
                    @endif
                </li>
            @endforeach
            @if ($window->last() < $last)
                @if ($window->last() < $last - 1)<li><span class="gap">…</span></li>@endif
                <li><a href="{{ $link($last) }}">{{ $last }}</a></li>
            @endif
            @if ($current < $last)
                <li><a href="{{ $link($current + 1) }}" rel="next" aria-label="Sljedeća">›</a></li>
            @endif
        </ul>
    </nav>
@endif
