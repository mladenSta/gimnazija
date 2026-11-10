@props(['post'])
<a href="{{ $post->path() }}" class="card reveal">
    <div class="img">
        <x-site.img :media="$post->featuredImage" variant="medium" sizes="(max-width: 600px) 100vw, (max-width: 1100px) 50vw, 400px" :alt="$post->title" />
    </div>
    <div class="body">
        <span class="date">{{ \App\Support\SerbianDate::short($post->published_at) }}</span>
        <h3>{{ $post->title }}</h3>
        <p>{{ $post->summary(180) }}</p>
    </div>
</a>
