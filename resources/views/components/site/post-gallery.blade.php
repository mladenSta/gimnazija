@props(['images', 'group' => 'content'])
{{-- "Other images" of a post/page: grid below the text, opens in the lightbox. --}}
@if ($images->isNotEmpty())
    <section class="post-gallery" aria-label="Galerija">
        <h2>Galerija</h2>
        <div class="post-gallery-grid">
            @foreach ($images as $image)
                <a href="{{ $image->url() }}" data-lightbox="{{ $group }}" data-caption="{{ $image->caption ?? $image->alt }}">
                    <x-site.img :media="$image" variant="thumb" sizes="(max-width: 600px) 50vw, 200px" :alt="$image->alt ?? ''" />
                </a>
            @endforeach
        </div>
    </section>
@endif
