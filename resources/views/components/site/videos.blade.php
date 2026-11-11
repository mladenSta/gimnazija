@props(['ids', 'title' => ''])
{{-- YouTube videos of a post/page: below the text, above the gallery. --}}
@if ($ids !== [])
    <section class="post-videos" aria-label="Video">
        @foreach ($ids as $i => $id)
            <div class="video-embed">
                <iframe
                    src="{{ \App\Support\YouTube::embedUrl($id) }}"
                    title="{{ $title }}{{ count($ids) > 1 ? ' – video '.($i + 1) : ' – video' }}"
                    loading="lazy"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                ></iframe>
            </div>
        @endforeach
    </section>
@endif
