{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ \App\Support\Seo::SITE_NAME }}</title>
    <link>{{ $base }}/</link>
    <atom:link href="{{ $base }}/feed/" rel="self" type="application/rss+xml"/>
    <description>Vijesti iz Gimnazije „Jovan Dučić" Doboj</description>
    <language>sr-Latn</language>
    @if ($posts->first())
        <lastBuildDate>{{ $posts->first()->published_at?->toRfc2822String() }}</lastBuildDate>
    @endif
    @foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ $base }}{{ $post->path() }}</link>
            <guid isPermaLink="true">{{ $base }}{{ $post->path() }}</guid>
            <pubDate>{{ $post->published_at?->toRfc2822String() }}</pubDate>
            @foreach ($post->categories as $category)
                <category>{{ $category->name }}</category>
            @endforeach
            <description>{{ $post->summary(300) }}</description>
            @if ($post->featuredImage)
                <enclosure url="{{ $post->featuredImage->url('medium') }}" type="image/webp" length="0"/>
            @endif
        </item>
    @endforeach
</channel>
</rss>
