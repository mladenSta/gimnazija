{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ $base }}/</loc><changefreq>daily</changefreq><priority>1.0</priority></url>
    <url><loc>{{ $base }}/vijesti/</loc><changefreq>daily</changefreq><priority>0.8</priority></url>
    @foreach ($pages as $page)
        <url><loc>{{ $base }}{{ $page->path() }}</loc><lastmod>{{ $page->updated_at?->toAtomString() }}</lastmod><priority>0.7</priority></url>
    @endforeach
    @foreach ($categories as $category)
        <url><loc>{{ $base }}{{ $category->path() }}</loc><changefreq>weekly</changefreq><priority>0.5</priority></url>
    @endforeach
    @foreach ($posts as $post)
        <url><loc>{{ $base }}{{ $post->path() }}</loc><lastmod>{{ $post->updated_at?->toAtomString() }}</lastmod><priority>0.6</priority></url>
    @endforeach
</urlset>
