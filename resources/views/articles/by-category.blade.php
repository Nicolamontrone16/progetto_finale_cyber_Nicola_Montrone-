<x-layout :title="$category->name">
    <section class="content-section"><div class="container">
        <nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="{{ route('articles.index') }}">Articoli</a><span aria-hidden="true">/</span><span aria-current="page">{{ $category->name }}</span></nav>
        <x-page-header eyebrow="Categoria" :title="$category->name" description="Approfondimenti selezionati per questo argomento." />
        <div class="article-grid">@forelse ($articles as $article)<x-article-card :article="$article" />@empty<x-empty-state title="Categoria ancora vuota" message="Non ci sono articoli pubblicati in questa categoria." />@endforelse</div>
    </div></section>
</x-layout>
