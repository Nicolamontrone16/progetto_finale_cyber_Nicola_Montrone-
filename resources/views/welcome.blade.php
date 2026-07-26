<x-layout>
    <section class="hero-section">
        <div class="container hero-grid">
            <div class="hero-copy reveal">
                <p class="eyebrow"><span class="pulse-dot"></span> Intelligence for a safer web</p>
                <h1>Security knowledge,<br><span>shared.</span></h1>
                <p class="hero-lead">Approfondimenti, analisi e notizie dal mondo della cybersecurity, raccontati da una community attenta e competente.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary btn-lg" href="{{ route('articles.index') }}">Esplora gli articoli</a>
                    @auth
                        @if (Auth::user()->is_writer)<a class="btn btn-outline-primary btn-lg" href="{{ route('articles.create') }}">Inizia a scrivere</a>@endif
                    @else
                        <a class="btn btn-ghost btn-lg" href="{{ route('register') }}">Unisciti alla community</a>
                    @endauth
                </div>
                <div class="hero-trust"><span>Analisi</span><span>Threat intelligence</span><span>Best practice</span></div>
            </div>
            <div class="cyber-visual" aria-hidden="true">
                <div class="visual-grid"></div>
                <div class="orbit orbit-one"><i></i></div><div class="orbit orbit-two"><i></i></div>
                <svg class="shield-visual" viewBox="0 0 240 280"><path class="shield-outer" d="M120 14 218 50v76c0 70-43 122-98 140-55-18-98-70-98-140V50l98-36Z"/><path class="shield-inner" d="M120 47 188 72v55c0 48-28 85-68 102-40-17-68-54-68-102V72l68-25Z"/><path class="shield-lock" d="M91 126v-16c0-17 13-30 29-30s29 13 29 30v16h10v55H81v-55h10Zm18 0h22v-16c0-7-5-12-11-12s-11 5-11 12v16Z"/></svg>
                <span class="data-label label-one">TLS 1.3</span><span class="data-label label-two">ZERO TRUST</span><span class="data-label label-three">ENCRYPTED</span>
            </div>
        </div>
    </section>

    <section class="content-section">
        <div class="container">
            <x-page-header eyebrow="Dal network" title="Ultime analisi" description="Una selezione aggiornata di contenuti dalla community Cyber Blog.">
                <a class="btn btn-outline-primary" href="{{ route('articles.index') }}">Vedi tutti</a>
            </x-page-header>
            <div class="article-grid">
                @forelse ($articles as $article)<x-article-card :article="$article" />
                @empty<x-empty-state title="Nessun articolo pubblicato" message="La community sta preparando nuovi approfondimenti." />@endforelse
            </div>
        </div>
    </section>
</x-layout>
