<x-site.layout :seo="$seo">
    <header class="page-hero">
        <div class="container">
            <ol class="breadcrumbs">
                <li><a href="/">Početna</a></li>
                <li aria-current="page">Pretraga</li>
            </ol>
            <h1>Pretraga</h1>
            <form class="search-form" action="/" method="get" role="search">
                <label for="s" class="sr-only" style="position:absolute;left:-9999px">Pojam za pretragu</label>
                <input id="s" type="search" name="s" value="{{ $term }}" placeholder="Šta tražite?" minlength="2" required>
                <button class="btn btn-primary" type="submit">Traži</button>
            </form>
        </div>
    </header>

    <section class="archive">
        <div class="container" style="max-width:860px">
            @if ($term === '')
                <p class="empty">Unesite pojam za pretragu.</p>
            @elseif (mb_strlen($term) < 2)
                <p class="empty">Unesite najmanje dva slova.</p>
            @elseif ($results->total() === 0)
                <p class="empty">Nema rezultata za „{{ $term }}".</p>
            @else
                <p style="color:var(--muted);margin-bottom:20px">Pronađeno: {{ $results->total() }}</p>
                <ul class="search-results">
                    @foreach ($results as $result)
                        <li>
                            <a href="{{ $result['url'] }}">
                                <small>{{ $result['type'] }}@if ($result['date']) · {{ \App\Support\SerbianDate::short($result['date']) }}@endif</small>
                                <h3>{{ $result['title'] }}</h3>
                                <p>{{ $result['summary'] }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <x-site.pagination :paginator="$results" />
            @endif
        </div>
    </section>
</x-site.layout>
