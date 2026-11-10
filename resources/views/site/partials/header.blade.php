@php
    $menu = \App\Support\SiteMenu::tree();
    $current = '/'.ltrim(request()->getPathInfo(), '/');
    $isActive = fn (?string $href) => $href !== null && $href !== '/' && str_starts_with($current, $href);
@endphp
<div class="topbar">
    <div class="container">
        <div class="info">
            <span><svg><use href="#i-pin"/></svg>Hilandarska 2, 74000 Doboj</span>
            <span><svg><use href="#i-phone"/></svg><a href="tel:+38753200970">053 200 970</a></span>
            <span><svg><use href="#i-mail"/></svg><a href="mailto:ss38@skolers.org">ss38@skolers.org</a></span>
        </div>
        <div class="socials-top"><a href="https://www.facebook.com/profile.php?id=61573103333031" target="_blank" rel="noopener">Pratite nas na Facebooku →</a></div>
    </div>
</div>

<header class="site" id="top">
    <div class="container nav">
        <a href="/" class="brand" aria-label="Početna">
            @include('site.partials.logo')
            <span class="brand-text"><strong>Gimnazija „Jovan Dučić" Doboj</strong></span>
        </a>

        <nav aria-label="Glavni meni">
            <ul class="menu" id="menu">
                <li><a href="/" @class(['active' => $current === '/'])>Početna</a></li>
                @foreach ($menu as $item)
                    @if ($item['children'])
                        <li class="has-dd">
                            <a href="{{ $item['href'] ?? '#' }}" @class(['active' => $isActive($item['href']) || collect($item['children'])->contains(fn ($c) => $isActive($c['href']))])>
                                {{ $item['label'] }} <svg class="caret"><use href="#i-down"/></svg>
                            </a>
                            <ul class="dropdown">
                                @foreach ($item['children'] as $child)
                                    <li><a href="{{ $child['href'] ?? '#' }}">{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li><a href="{{ $item['href'] }}" @class(['active' => $isActive($item['href'])])>{{ $item['label'] }}</a></li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="nav-cta">
            <a href="/upis-2020/" class="btn btn-primary">Upis 2026. <svg><use href="#i-arrow"/></svg></a>
            <button class="burger" id="burger" type="button" aria-label="Meni" aria-controls="menu" aria-expanded="false"><span></span></button>
        </div>
    </div>
</header>
