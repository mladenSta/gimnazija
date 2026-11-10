<x-site.layout :seo="\App\Support\Seo::make(title: 'Stranica nije pronađena', noindex: true)">
    <section class="error-page">
        <div class="container">
            <div class="code">404</div>
            <h1>Stranica nije pronađena</h1>
            <p>Možda je premještena ili više ne postoji. Pokušajte pretragu ili se vratite na početnu.</p>
            <form class="search-form" action="/" method="get" role="search" style="margin:0 auto 28px">
                <input type="search" name="s" placeholder="Šta tražite?" aria-label="Pojam za pretragu" minlength="2" required>
                <button class="btn btn-primary" type="submit">Traži</button>
            </form>
            <a href="/" class="btn btn-outline">Na početnu</a>
        </div>
    </section>
</x-site.layout>
