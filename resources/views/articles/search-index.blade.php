<x-layout title="Ricerca articoli">
    <section class="content-section"><div class="container container-narrow">
        <x-page-header eyebrow="Ricerca protetta" title="Trova un approfondimento" description="Esplora l'archivio per titolo, argomento o parola chiave." />
        <form action="{{ route('articles.search') }}" method="GET" class="search-panel" role="search">
            <label for="article-query">Cosa vuoi cercare?</label>
            <div class="search-control">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.4-4.4m2.4-5.1A7.5 7.5 0 1 1 4 11.5a7.5 7.5 0 0 1 15 0Z"/></svg>
                <input id="article-query" class="form-control" type="search" name="query" value="{{ $query ?? '' }}" placeholder="Es. phishing, zero trust, malware…" autocomplete="off">
                <button class="btn btn-primary" type="submit">Cerca</button>
            </div>
            <small>La ricerca pubblica è protetta da limiti anti-abuso.</small>
        </form>
    </div></section>
    <section class="content-section section-tight"><div class="container">
        @if (!empty($query))<p class="results-label">Risultati per <strong>“{{ $query }}”</strong> · {{ $articles->count() }} trovati</p>@endif
        <div class="article-grid">
            @forelse ($articles as $article)<x-article-card :article="$article" />
            @empty<x-empty-state :title="empty($query) ? 'Inizia la ricerca' : 'Nessun risultato'" :message="empty($query) ? 'Inserisci una parola chiave per esplorare gli articoli.' : 'Prova con parole più generiche o consulta tutto l’archivio.'"><a class="btn btn-outline-primary" href="{{ route('articles.index') }}">Sfoglia l'archivio</a></x-empty-state>@endforelse
        </div>
    </div></section>
</x-layout>
