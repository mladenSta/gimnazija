<x-site.layout :seo="$seo" :is-preview="$isPreview">
    <article>
        <header class="page-hero">
            <div class="container">
                <ol class="breadcrumbs">
                    <li><a href="/">Početna</a></li>
                    <li aria-current="page">{{ \Illuminate\Support\Str::limit($page->title, 60) }}</li>
                </ol>
                <h1>{{ $page->title }}</h1>
            </div>
        </header>

        <div class="article">
            @if ($page->featuredImage)
                <figure class="featured-image">
                    <x-site.img :media="$page->featuredImage" variant="large" sizes="(max-width: 1080px) 100vw, 1040px" :alt="$page->featuredImage->alt ?? $page->title" eager />
                </figure>
            @endif

            <div class="narrow">
                <div class="entry-content">{!! $page->content !!}</div>

                <x-site.post-gallery :images="$page->galleryImages" />
            </div>
        </div>
    </article>
</x-site.layout>
