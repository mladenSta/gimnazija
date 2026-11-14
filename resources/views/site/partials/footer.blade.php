<footer class="site-footer" id="kontakt">
    <div class="container">
        <div class="foot-grid">
            <div>
                <a href="/" class="brand">
                    @include('site.partials.logo', ['inverse' => true])
                    <span class="brand-text"><strong>Gimnazija „Jovan Dučić"</strong><small>Doboj</small></span>
                </a>
                <p>"Nikad čovjek ne može da kaže toliko mudrosti, koliko može da prećuti ludosti". Jovan Dučić</p>
                <div class="socials">
                    <a href="{{ config('cms.social.facebook') }}" target="_blank" rel="noopener" aria-label="Facebook" title="Facebook"><svg><use href="#i-fb"/></svg></a>
                    <a href="{{ config('cms.social.instagram') }}" target="_blank" rel="noopener" aria-label="Instagram" title="Instagram"><svg><use href="#i-ig"/></svg></a>
                </div>
            </div>
            <div>
                <h5>O školi</h5>
                <ul>
                    <li><a href="/istorijat-skole/">Istorijat škole</a></li>
                    <li><a href="/upis-2020/">Upis 2026.</a></li>
                    <li><a href="/koraci2/">Koraci</a></li>
                    <li><a href="/biblioteka/">Biblioteka</a></li>
                    <li><a href="/misija-i-vizija/">Misija i vizija</a></li>
                </ul>
            </div>
            <div>
                <h5>Sekcije</h5>
                <ul>
                    <li><a href="/dramska-sekcija/">Dramska sekcija</a></li>
                    <li><a href="/psiholoska-sekcija/">Psihološka sekcija</a></li>
                    <li><a href="/muzicka-kultura/">Muzička kultura</a></li>
                    <li><a href="/engleski-jezik/">Engleski jezik</a></li>
                    <li><a href="/category/istorijska-sekcija/">Istorijska sekcija</a></li>
                </ul>
            </div>
            <div>
                <h5>Kontakt</h5>
                <div class="contact-line"><svg><use href="#i-pin"/></svg><span>Hilandarska 2<br>74000 Doboj</span></div>
                <div class="contact-line"><svg><use href="#i-phone"/></svg><a href="tel:+38753200970">053 200 970</a></div>
                <div class="contact-line"><svg><use href="#i-mail"/></svg><a href="mailto:ss38@skolers.org">ss38@skolers.org</a></div>
            </div>
        </div>
        <div class="copy">
            <span>© {{ now()->year }} JU Gimnazija „Jovan Dučić" Doboj. Sva prava zadržana.</span>
            <span><a href="/kontakt/">Kontakt</a> · <a href="/feed/">RSS</a></span>
        </div>
    </div>
</footer>
