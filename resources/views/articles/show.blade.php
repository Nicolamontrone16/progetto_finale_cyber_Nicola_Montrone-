<x-layout :title="$article->title">
    <article class="article-page">
        <header class="article-header container container-reading reveal">
            <nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="{{ route('articles.index') }}">Articoli</a><span aria-hidden="true">/</span>@if($article->category)<a href="{{ route('articles.byCategory', $article->category) }}">{{ $article->category->name }}</a><span aria-hidden="true">/</span>@endif<span aria-current="page">Lettura</span></nav>
            @if ($article->category)<a class="category-pill" href="{{ route('articles.byCategory', $article->category) }}">{{ $article->category->name }}</a>@endif
            <h1>{{ $article->title }}</h1>
            <p class="article-subtitle">{{ $article->subtitle }}</p>
            <div class="article-byline"><span class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($article->user->name, 0, 1)) }}</span><div><a href="{{ route('articles.byUser', $article->user) }}">{{ $article->user->name }}</a><p><time datetime="{{ $article->created_at->toDateString() }}">{{ $article->created_at->format('d F Y') }}</time><span>·</span>{{ $article->readDuration() }} min di lettura</p></div></div>
        </header>
        <div class="container article-cover reveal">@if($article->image)<img src="{{ Storage::url($article->image) }}" alt="Immagine di copertina per {{ $article->title }}">@else<span class="article-fallback" aria-hidden="true"><span>CYBER BLOG</span></span>@endif</div>
        <div class="container container-reading article-layout">
            <div class="article-prose">{!! $safeBody !!}</div>
            @if ($article->tags->isNotEmpty())<div class="article-tags"><strong>Argomenti</strong><div class="tag-list">@foreach($article->tags as $tag)<span>#{{ $tag->name }}</span>@endforeach</div></div>@endif
            @if (Auth::user() && Auth::user()->is_revisor && !$article->is_accepted)
                <section class="review-action-panel" aria-labelledby="review-title"><div><p class="eyebrow">Area revisore</p><h2 id="review-title">Valuta questo articolo</h2></div><div class="d-flex gap-2 flex-wrap"><form action="{{ route('revisor.acceptArticle', $article) }}" method="POST">@csrf<button type="submit" class="btn btn-success">Approva</button></form><form action="{{ route('revisor.rejectArticle', $article) }}" method="POST">@csrf<button type="submit" class="btn btn-danger">Rifiuta</button></form></div></section>
            @endif
            <aside class="author-panel"><span class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($article->user->name, 0, 1)) }}</span><div><small>Scritto da</small><h2>{{ $article->user->name }}</h2><a href="{{ route('articles.byUser', $article->user) }}">Scopri tutti gli articoli →</a></div></aside>
        </div>
    </article>
</x-layout>
