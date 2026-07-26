<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <a class="brand-lockup footer-brand" href="{{ route('homepage') }}"><span class="brand-mark" aria-hidden="true"></span><span>Cyber <strong>Blog</strong></span></a>
                <p>Approfondimenti, analisi e notizie per comprendere la sicurezza digitale con chiarezza.</p>
            </div>
            <div>
                <h2>Esplora</h2>
                <a href="{{ route('articles.index') }}">Tutti gli articoli</a>
                <a href="{{ route('articles.search') }}">Ricerca</a>
                @auth<a href="{{ route('careers') }}">Collabora con noi</a>@endauth
            </div>
            <div>
                <h2>Account</h2>
                @guest
                    <a href="{{ route('login') }}">Accedi</a>
                    <a href="{{ route('register') }}">Crea account</a>
                @else
                    <a href="{{ route('profile.edit') }}">Profilo</a>
                    @if (Auth::user()->is_writer)<a href="{{ route('writer.dashboard') }}">Area writer</a>@endif
                @endguest
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ now()->year }} Cyber Blog</span>
            <span>Progetto educativo dedicato alla cybersecurity.</span>
        </div>
    </div>
</footer>
