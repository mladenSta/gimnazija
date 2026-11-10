@php
    use App\Support\SerbianDate;
    $primary = $post->primary_category;
@endphp
<x-site.layout :seo="$seo" :is-preview="$isPreview">
    <article>
        <header class="page-hero">
            <div class="container">
                <ol class="breadcrumbs">
                    <li><a href="/">Početna</a></li>
                    @if ($primary)
                        <li><a href="{{ $primary->path() }}">{{ $primary->name }}</a></li>
                    @else
                        <li><a href="/vijesti/">Vijesti</a></li>
                    @endif
                    <li aria-current="page">{{ \Illuminate\Support\Str::limit($post->title, 60) }}</li>
                </ol>
                <h1>{{ $post->title }}</h1>
                <div class="meta">
                    @if ($post->published_at)
                        <time class="date-chip" datetime="{{ $post->published_at->toDateString() }}">{{ SerbianDate::short($post->published_at) }}</time>
                    @endif
                    @foreach ($post->categories as $category)
                        <a class="cat" href="{{ $category->path() }}">{{ $category->name }}</a>
                    @endforeach
                </div>
            </div>
        </header>

        <div class="article">
            @if ($post->featuredImage)
                <figure class="featured-image">
                    <x-site.img :media="$post->featuredImage" variant="large" sizes="(max-width: 1080px) 100vw, 1040px" :alt="$post->featuredImage->alt ?? $post->title" eager />
                </figure>
            @endif

            <div class="narrow">
                <div class="entry-content">{!! $post->content !!}</div>

                <x-site.post-gallery :images="$post->galleryImages" />

                <footer class="post-footer">
                    <div class="chips">
                        @foreach ($post->categories as $category)
                            <a class="chip" href="{{ $category->path() }}">{{ $category->name }}</a>
                        @endforeach
                    </div>
                    <a href="/vijesti/" class="btn btn-outline">Sve vijesti <svg><use href="#i-arrow"/></svg></a>
                </footer>
            </div>
        </div>
    </article>

    @if ($more->isNotEmpty())
        <section class="more-news">
            <div class="container">
                <h2>Još vijesti</h2>
                <div class="card-grid">
                    @foreach ($more as $item)
                        <x-site.post-card :post="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-site.layout>
