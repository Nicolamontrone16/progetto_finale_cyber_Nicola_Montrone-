<x-layout :title="$user->name">
    <section class="content-section"><div class="container">
        <nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="{{ route('articles.index') }}">Articoli</a><span aria-hidden="true">/</span><span aria-current="page">Autore</span></nav>
        <x-page-header eyebrow="Autore" :title="$user->name" description="Tutti gli articoli pubblicati da questo autore." />
        <div class="article-grid">@forelse ($articles as $article)<x-article-card :article="$article" />@empty<x-empty-state title="Nessun articolo" message="Questo autore non ha ancora pubblicato contenuti." />@endforelse</div>
    </div></section>
</x-layout>
