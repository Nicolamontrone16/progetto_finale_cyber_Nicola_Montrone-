<nav class="navbar navbar-expand-lg site-navbar" aria-label="Navigazione principale">
    <div class="container">
        <a class="navbar-brand brand-lockup" href="{{ route('homepage') }}" aria-label="Cyber Blog, home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 2 4 5v6c0 5.1 3.4 9.8 8 11 4.6-1.2 8-5.9 8-11V5l-8-3Zm0 3.1 5 1.8V11c0 3.7-2.2 7.1-5 8.2-2.8-1.1-5-4.5-5-8.2V6.9l5-1.8Zm-1 3.4v2H9v3h2v2h3v-2h2v-3h-2v-2h-3Z"/></svg>
            </span>
            <span>Cyber <strong>Blog</strong></span>
        </a>

        <div class="d-flex align-items-center gap-2 order-lg-3">
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Passa al tema chiaro" title="Cambia tema">
                <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4V2m0 20v-2M4 12H2m20 0h-2M5.6 5.6 4.2 4.2m15.6 15.6-1.4-1.4m0-12.8 1.4-1.4M4.2 19.8l1.4-1.4M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Z"/></svg>
            </button>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Apri il menu">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav mx-lg-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('homepage') ? 'active' : '' }}" href="{{ route('homepage') }}">Home</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('articles.index', 'articles.show', 'articles.byCategory', 'articles.byUser') ? 'active' : '' }}" href="{{ route('articles.index') }}">Articoli</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('articles.search') ? 'active' : '' }}" href="{{ route('articles.search') }}">Ricerca</a></li>
                @auth
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('careers') ? 'active' : '' }}" href="{{ route('careers') }}">Collabora</a></li>
                    @if (Auth::user()->is_writer)
                        <li class="nav-item"><a class="btn btn-primary btn-sm ms-lg-2" href="{{ route('articles.create') }}">Nuovo articolo</a></li>
                    @endif
                @endauth
            </ul>

            <div class="navbar-actions ms-lg-3">
                @guest
                    <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Accedi</a>
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('register') }}">Registrati</a>
                @endguest
                @auth
                    <div class="dropdown">
                        <button class="profile-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
                            <span class="profile-copy"><small>Area personale</small>{{ Auth::user()->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profilo e sicurezza</a></li>
                            @if (Auth::user()->is_writer)<li><a class="dropdown-item" href="{{ route('writer.dashboard') }}">Dashboard writer</a></li>@endif
                            @if (Auth::user()->is_revisor)<li><a class="dropdown-item" href="{{ route('revisor.dashboard') }}">Dashboard revisore</a></li>@endif
                            @if (Auth::user()->is_admin)<li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Dashboard admin</a></li>@endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button class="dropdown-item text-danger" type="submit">Esci</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</nav>
