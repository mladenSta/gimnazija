@php
    use App\Support\SerbianDate;
@endphp
<x-site.layout :seo="$seo">

{{-- ============ HERO SLIDER (gallery: Početna – slajder) ============ --}}
<section class="hero">
    <div class="container">
        <div class="slider" id="slider">
            @forelse ($slides as $slide)
                <div @class(['slide', 'active' => $loop->first])>
                    <x-site.img :media="$slide->media" variant="large" sizes="(max-width: 1280px) 100vw, 1240px"
                        :alt="$slide->title ?? $slide->media->alt" :position="$slide->object_position" :eager="$loop->first" />
                    @if ($slide->badge || $slide->title || $slide->subtitle || $slide->link_url)
                        <div class="slide-content">
                            @if ($slide->badge)<span class="tag">{{ $slide->badge }}</span>@endif
                            @if ($slide->title)
                                @if ($loop->first)<h1>{{ $slide->title }}</h1>@else<h2 class="h1-like">{{ $slide->title }}</h2>@endif
                            @endif
                            @if ($slide->subtitle)<p>{{ $slide->subtitle }}</p>@endif
                            @if ($slide->link_url)
                                <div class="actions">
                                    <a href="{{ $slide->link_url }}" class="btn btn-primary">{{ $slide->link_label ?: 'Saznajte više' }} <svg><use href="#i-arrow"/></svg></a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="slide active">
                    <div class="slide-fallback"></div>
                    <div class="slide-content">
                        <span class="tag">🎓 Najduža tradicija u Doboju</span>
                        <h1>Na pravom ste mjestu. <span class="hl">Dobrodošli!</span></h1>
                        <p>Škola sa najdužom vaspitno-obrazovnom tradicijom u Doboju – mjesto gdje se znanje, prijateljstvo i kreativnost susreću svaki dan.</p>
                        <div class="actions"><a href="/istorijat-skole/" class="btn btn-ghost" style="color:#fff">Upoznajte nas</a></div>
                    </div>
                </div>
            @endforelse

            <div class="slider-ui">
                <button class="arrow" id="prev" type="button" aria-label="Prethodni"><svg><use href="#i-left"/></svg></button>
                <div class="dots" id="dots"></div>
                <button class="arrow" id="next" type="button" aria-label="Sljedeći"><svg><use href="#i-right"/></svg></button>
            </div>
        </div>

        <div class="hero-cards">
            <a href="/upis-2020/" class="hero-card"><div class="ico"><svg><use href="#i-cap"/></svg></div><div><strong>Upis 2026.</strong><span>4 odjeljenja, 3 smjera</span></div></a>
            <a href="/o-jovanu-ducicu/" class="hero-card"><div class="ico"><svg><use href="#i-feather"/></svg></div><div><strong>O Jovanu Dučiću</strong><span>Pjesnik čije ime nosimo</span></div></a>
            <a href="/biblioteka/" class="hero-card"><div class="ico"><svg><use href="#i-book"/></svg></div><div><strong>Biblioteka</strong><span>Hiljade naslova za vas</span></div></a>
            <a href="#najave" class="hero-card"><div class="ico"><svg><use href="#i-cal"/></svg></div><div><strong>Najave</strong><span>Šta nas čeka uskoro</span></div></a>
        </div>
    </div>
</section>

{{-- ============ PHOTO RIBBON (gallery: Početna – gore) ============ --}}
@if ($ribbon->isNotEmpty())
    <section class="ribbon" aria-label="Fotografije iz školskog života">
        <div class="ribbon-track">
            {{-- The list is rendered twice for a seamless loop; the copy is hidden from screen readers. --}}
            @foreach ([false, true] as $copy)
                @foreach ($ribbon as $i => $item)
                    <x-site.img :media="$item->media" variant="medium" sizes="300px"
                        :alt="$copy ? '' : ($item->caption ?? $item->media->alt ?? '')"
                        :aria-hidden="$copy ? 'true' : null"
                        :position="$item->object_position"
                        style="--ar:{{ ['4/3', '3/4', '1/1', '16/10', '4/5'][$i % 5] }};--r:{{ (($i % 5) - 2) * 1.4 }}deg" />
                @endforeach
            @endforeach
        </div>
        <p class="ribbon-caption">~ jedan dan u našoj gimnaziji ~</p>
    </section>
@endif

{{-- ============ UPIS 2026 (hardcoded) ============ --}}
<section class="enroll" id="upis">
    <div class="container">
        <div class="enroll-box reveal">
            <div>
                <span class="eyebrow">Upis 2026.</span>
                <h2>Tvoja priča počinje ovdje.</h2>
                <p>Gimnazija „Jovan Dučić" ove godine upisuje četiri odjeljenja kroz tri smjera. Dođi na Dan otvorenih vrata, upoznaj profesore, učenike i prostor u kom ćeš provesti četiri najljepše godine.</p>
                <a href="/upis-2020/" class="btn btn-white">Kako se upisati <svg><use href="#i-arrow"/></svg></a>
            </div>
            <div class="tracks">
                <a href="/upis-2020/" class="track"><span class="num">01</span><div><strong>Opšti smjer</strong><span>Široko obrazovanje za sve fakultete</span></div></a>
                <a href="/upis-2020/" class="track"><span class="num">02</span><div><strong>Društveno-jezički smjer</strong><span>Jezici, istorija, komunikacija</span></div></a>
                <a href="/category/racunarski-smjer/" class="track"><span class="num">03</span><div><strong>Računarsko-informatički smjer</strong><span>Programiranje i digitalne vještine</span></div></a>
            </div>
        </div>
    </div>
</section>

{{-- ============ NEWS (latest posts) ============ --}}
<section class="section news" id="vijesti">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">Vijesti</span>
                <h2>Šta je <em>novo</em> u školi</h2>
            </div>
            <a href="/vijesti/" class="btn btn-outline">Sve vijesti <svg><use href="#i-arrow"/></svg></a>
        </div>

        @if ($posts->isEmpty())
            <p class="empty">Uskoro ovdje objavljujemo novosti iz škole.</p>
        @else
            @php $main = $posts->first(); @endphp
            <div class="news-grid">
                <a href="{{ $main->path() }}" class="news-main reveal">
                    <x-site.img :media="$main->featuredImage" variant="large" sizes="(max-width: 860px) 100vw, 720px" :alt="$main->title" />
                    <div class="body">
                        <span class="date-chip">{{ SerbianDate::short($main->published_at) }}</span>
                        <h3>{{ $main->title }}</h3>
                        <p>{{ $main->summary(200) }}</p>
                    </div>
                </a>
                <div class="news-list">
                    @foreach ($posts->slice(1) as $post)
                        <a href="{{ $post->path() }}" class="news-item reveal">
                            @if ($post->featuredImage)
                                <x-site.img :media="$post->featuredImage" variant="thumb" sizes="150px" alt="" />
                            @else
                                <span style="display:block;height:118px;border-radius:10px;background:linear-gradient(135deg,var(--teal-100),var(--plum-soft))"></span>
                            @endif
                            <div><div class="meta">{{ SerbianDate::short($post->published_at) }}</div><h4>{{ $post->title }}</h4><p>{{ $post->summary(120) }}</p></div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>

{{-- ============ ABOUT (hardcoded text; photos from Početna – gore) ============ --}}
<section class="section" id="o-nama">
    <div class="container">
        <div @class(['about-grid', 'no-photos' => $about->isEmpty()])>
            @if ($about->isNotEmpty())
            <div class="about-collage reveal">
                @foreach ($about as $i => $item)
                    <x-site.img :media="$item->media" variant="medium" sizes="(max-width: 860px) 60vw, 380px" :alt="$item->caption ?? $item->media->alt ?? ''" :position="$item->object_position" class="a{{ $i + 1 }}" />
                @endforeach
                <div class="badge">
                    <svg viewBox="0 0 150 150" aria-hidden="true"><defs><path id="circ" d="M75,75 m-58,0 a58,58 0 1,1 116,0 a58,58 0 1,1 -116,0"/></defs><text font-size="11" fill="#d9c8e6" letter-spacing="3.2" font-family="DM Sans" font-weight="700"><textPath href="#circ">GIMNAZIJA • JOVAN DUČIĆ • DOBOJ • </textPath></text></svg>
                    <div><strong>80</strong><span>godina<br>tradicije</span></div>
                </div>
            </div>
            @endif

            <div class="about-text reveal">
                <span class="eyebrow">O nama</span>
                <h2>Tradicija koja <em>gleda naprijed</em></h2>
                <p>Gimnazija „Jovan Dučić" je škola sa najdužom vaspitno-obrazovnom tradicijom u Doboju. Generacije učenika su ovdje stekle znanje, prijatelje za cijeli život i hrabrost da sanjaju veliko. Danas spajamo to nasljeđe sa savremenom nastavom, digitalnim učionicama i bogatim vannastavnim životom.</p>
                <div class="about-links">
                    <a href="/istorijat-skole/"><i><svg><use href="#i-history"/></svg></i>Istorijat škole</a>
                    <a href="/misija-i-vizija/"><i><svg><use href="#i-target"/></svg></i>Misija i vizija</a>
                    <a href="/o-jovanu-ducicu/"><i><svg><use href="#i-feather"/></svg></i>O Jovanu Dučiću</a>
                    <a href="/category/racunarski-smjer/"><i><svg><use href="#i-code"/></svg></i>Računarski smjer</a>
                </div>
                <a href="/istorijat-skole/" class="btn btn-primary">Saznajte više <svg><use href="#i-arrow"/></svg></a>
            </div>
        </div>

        {{-- TODO: replace placeholder numbers with real ones --}}
        <div class="stats reveal">
            <div class="stat"><strong data-count="500">500+</strong><span>učenika</span></div>
            <div class="stat"><strong data-count="50">50+</strong><span>profesora</span></div>
            <div class="stat"><strong data-count="3">3</strong><span>smjera</span></div>
            <div class="stat"><strong data-count="10">10+</strong><span>sekcija i klubova</span></div>
        </div>
    </div>
</section>

{{-- ============ IZDVAJAMO + SEKCIJE (hardcoded) ============ --}}
@php
    $featured = [
        ['href' => '/koraci2/', 'title' => 'Školski časopis „Koraci"', 'text' => 'Naši mladi novinari, pisci i fotografi pripremaju časopis od prve do posljednje stranice.', 'image' => 'wp-content/uploads/2020/05/koraci-izdvojeno-600x600.jpg'],
        ['href' => '/dramska-sekcija/', 'title' => 'Dramska sekcija', 'text' => 'Predstave, festivali i aplauzi – scena na kojoj učenici pokazuju hrabrost i talenat.', 'image' => 'images/izdvajamo/dramska-ruke.jpg', 'position' => '50% 30%'],
        ['href' => '/category/takmicenja/', 'title' => 'Takmičenja', 'text' => 'Od matematike do Crvenog krsta – rezultati koji nas čine ponosnim iz godine u godinu.', 'image' => 'wp-content/uploads/2026/05/takmicenja-1-1.jpg', 'position' => '50% 15%'],
    ];
    $clubs = [
        ['href' => '/dramska-sekcija/', 'image' => 'wp-content/uploads/2026/04/667684622_122179618670770111_7068596852317087493_n-150x150.jpg', 'name' => 'Dramska sekcija'],
        ['href' => '/category/istorijska-sekcija/', 'image' => 'wp-content/uploads/2025/02/480527504_122100981884770111_1510790567258759360_n-150x150.jpg', 'name' => 'Istorijska sekcija'],
        ['href' => '/psiholoska-sekcija/', 'image' => 'wp-content/uploads/2023/12/411198807_853581403229513_4547516130048883423_n-150x150.jpg', 'name' => 'Psihološka sekcija'],
        ['href' => '/muzicka-kultura/', 'image' => 'wp-content/uploads/2026/04/678580617_122181572036770111_1286488492449824675_n-150x150.jpg', 'name' => 'Muzička kultura'],
        ['href' => '/engleski-jezik/', 'image' => 'wp-content/uploads/2026/02/640998800_122174747966770111_5923621085678546391_n-150x150.jpg', 'name' => 'Engleski jezik'],
    ];
@endphp
<section class="section featured" id="sekcije">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">Izdvajamo</span>
                <h2>Život van <em>klupe</em></h2>
            </div>
            <p>Škola nije samo nastava. Ovo su mjesta gdje naši učenici otkrivaju talente, stvaraju i rastu zajedno.</p>
        </div>

        <div class="feat-grid">
            @foreach ($featured as $f)
                <a href="{{ $f['href'] }}" class="feat reveal">
                    @if (file_exists(public_path($f['image'])))
                        <div class="img"><img src="/{{ $f['image'] }}" alt="{{ $f['title'] }}" loading="lazy" @isset($f['position']) style="object-position: {{ $f['position'] }}" @endisset></div>
                    @else
                        <div class="img placeholder" aria-hidden="true"></div>
                    @endif
                    <div class="body"><h3>{{ $f['title'] }}</h3><p>{{ $f['text'] }}</p><span class="more">Pročitaj <svg><use href="#i-arrow"/></svg></span></div>
                </a>
            @endforeach
        </div>

        <div class="clubs reveal">
            @foreach ($clubs as $c)
                <a href="{{ $c['href'] }}" class="club">@if (file_exists(public_path($c['image'])))<img src="/{{ $c['image'] }}" alt="" loading="lazy" width="48" height="48">@endif{{ $c['name'] }}</a>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ GALLERY (gallery: Početna – dolje, filtered by tags) ============ --}}
@if ($gallery->isNotEmpty())
    <section class="section gallery" id="galerija">
        <div class="container">
            <div class="section-head reveal">
                <div>
                    <span class="eyebrow">Galerija</span>
                    <h2>Uspomene u <em>kadru</em></h2>
                </div>
                <p>Ekskurzije, priredbe, takmičenja, maturska veče i obični, lijepi školski dani – kliknite na fotografiju da je uvećate.</p>
            </div>

            @if ($filterTags->isNotEmpty())
                <div class="filters" id="filters">
                    <button type="button" class="active" data-f="all">Sve</button>
                    @foreach ($filterTags as $tag)
                        <button type="button" data-f="{{ $tag->slug }}">{{ $tag->name }}</button>
                    @endforeach
                </div>
            @endif

            <div class="masonry" id="masonry">
                @foreach ($gallery as $i => $item)
                    <figure class="polaroid" data-tags="{{ $item->tags->pluck('slug')->implode(' ') }}" style="--r:{{ ((($i * 7) % 5) - 2) * 0.9 }}deg">
                        <a href="{{ $item->media->url() }}" data-lightbox="home" data-caption="{{ $item->caption }}">
                            <x-site.img :media="$item->media" variant="medium" sizes="(max-width: 600px) 100vw, 300px" :alt="$item->caption ?? $item->media->alt ?? ''" />
                        </a>
                        @if ($item->caption)<figcaption>{{ $item->caption }}</figcaption>@endif
                    </figure>
                @endforeach
            </div>

            <div class="gallery-cta"><a href="{{ config('cms.social.facebook') }}" target="_blank" rel="noopener" class="btn">Još fotografija na Facebooku <svg><use href="#i-arrow"/></svg></a></div>
        </div>
    </section>
@endif

{{-- ============ ALUMNI (admin: Bivši učenici) ============ --}}
@if ($testimonials->isNotEmpty())
    <section class="section">
        <div class="container">
            <div @class(['testi-wrap', 'no-photo' => ! $alumniPhoto])>
                @if ($alumniPhoto)
                    <div class="testi-photo reveal">
                        <x-site.img :media="$alumniPhoto->media" variant="large" sizes="(max-width: 860px) 100vw, 520px" alt="" :position="$alumniPhoto->object_position" />
                        <div class="note">„Gimnazija je bila moj drugi dom" ♥</div>
                    </div>
                @endif
                @php($testiPages = $testimonials->chunk(3)->values())
                <div class="testi-carousel" data-carousel>
                    <div class="section-head reveal" style="margin-bottom:30px">
                        <div>
                            <span class="eyebrow">Poruke bivših učenika</span>
                            <h2>Šta kažu oni koji su <em>bili ovdje</em></h2>
                        </div>
                        @if ($testiPages->count() > 1)
                            <div class="carousel-dots" aria-label="Poruke bivših učenika">
                                @foreach ($testiPages as $i => $group)
                                    <button type="button" @class(['is-active' => $i === 0]) data-carousel-dot="{{ $i }}" aria-label="Poruke {{ $i * 3 + 1 }}–{{ $i * 3 + $group->count() }} od {{ $testimonials->count() }}" @if ($i === 0) aria-current="true" @endif></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="testi-pages reveal">
                        @foreach ($testiPages as $i => $group)
                            <div @class(['testi-cards', 'testi-page', 'is-active' => $i === 0]) data-carousel-page @if ($i > 0) aria-hidden="true" inert @endif>
                                @foreach ($group as $t)
                                    <div class="testi">
                                        <span class="q" aria-hidden="true">“</span>
                                        <blockquote class="is-clamped">{{ $t->quote }}</blockquote>
                                        <button type="button" class="testi-more" data-testi-more hidden aria-expanded="false">Pročitaj više</button>
                                        <div class="who">
                                            <x-site.img :media="$t->photo" variant="thumb" sizes="50px" alt="" />
                                            <div>
                                                <strong>{{ $t->name }}</strong>
                                                <span>
                                                    @if ($t->generation)Generacija {{ $t->generation }}.@endif
                                                    @if ($t->generation && $t->profession) · @endif
                                                    {{ $t->profession }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif

{{-- ============ EVENTS / NAJAVE ============ --}}
<section class="section events" id="najave">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">Najave</span>
                <h2>Uskoro u <em>gimnaziji</em></h2>
            </div>
        </div>
        @if ($events->isEmpty())
            <p class="events-empty">Trenutno nema najava. Pratite nas na Facebooku za najnovije informacije.</p>
        @else
            <div class="events-grid">
                @foreach ($events as $event)
                    <div class="event reveal">
                        <div class="cal"><small>{{ SerbianDate::monthShort($event->date) }}</small><strong>{{ $event->date->format('d') }}</strong></div>
                        <div>
                            @if ($event->label)<span class="cat">{{ $event->label }}</span>@endif
                            <h4>{{ $event->title }}</h4>
                            @if ($event->short_description)<p>{{ $event->short_description }}</p>@endif
                            @if ($event->location || $event->time)
                                <p>{{ collect([$event->location, $event->time ? substr($event->time, 0, 5) : null])->filter()->implode(', ') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

</x-site.layout>
