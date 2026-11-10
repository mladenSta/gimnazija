<x-site.layout :seo="$seo">
    <header class="page-hero">
        <div class="container">
            <ol class="breadcrumbs">
                <li><a href="/">Početna</a></li>
                @if ($base !== '/vijesti/')
                    <li><a href="/vijesti/">Vijesti</a></li>
                @endif
                <li aria-current="page">{{ $title }}</li>
            </ol>
            <h1>{{ $title }}</h1>
            @if ($lead)<p class="lead">{{ $lead }}</p>@endif
        </div>
    </header>

    <section class="archive">
        <div class="container">
            @if ($posts->isEmpty())
                <p class="empty">Još nema objava.</p>
            @else
                <div class="card-grid">
                    @foreach ($posts as $post)
                        <x-site.post-card :post="$post" />
                    @endforeach
                </div>
                <x-site.pagination :paginator="$posts" :base="$base" />
            @endif
        </div>
    </section>
</x-site.layout>
