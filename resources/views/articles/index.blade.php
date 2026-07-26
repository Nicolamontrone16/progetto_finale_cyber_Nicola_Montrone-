<x-layout title="Articoli">
    <section class="content-section"><div class="container">
        <x-page-header eyebrow="Knowledge base" title="Tutti gli articoli" description="Analisi tecniche, notizie e prospettive sul panorama della sicurezza digitale." />
        <div class="article-grid">
            @forelse ($articles as $article)<x-article-card :article="$article" />
            @empty<x-empty-state title="Archivio in aggiornamento" message="Non ci sono ancora articoli disponibili." />@endforelse
        </div>
    </div></section>
</x-layout>
