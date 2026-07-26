<article class="article-card reveal">
    <a class="article-card-media" href="{{ route('articles.show', $article) }}" aria-label="Leggi {{ $article->title }}">
        @if ($article->image)
            <img src="{{ Storage::url($article->image) }}" alt="Immagine dell'articolo {{ $article->title }}" loading="lazy">
        @else
            <span class="article-fallback" aria-hidden="true"><span>CB</span></span>
        @endif
    </a>
    <div class="article-card-body">
        <div class="article-card-topline">
            @if ($article->category)
                <a class="category-pill" href="{{ route('articles.byCategory', $article->category) }}">{{ $article->category->name }}</a>
            @else
                <span class="category-pill">Cybersecurity</span>
            @endif
            <span>{{ $article->readDuration() }} min</span>
        </div>
        <h2><a href="{{ route('articles.show', $article) }}">{{ $article->title }}</a></h2>
        <p>{{ $article->subtitle }}</p>
        @if ($article->tags->isNotEmpty())
            <div class="tag-list" aria-label="Tag">
                @foreach ($article->tags->take(3) as $tag)<span>#{{ $tag->name }}</span>@endforeach
            </div>
        @endif
    </div>
    <footer class="article-card-footer">
        <a href="{{ route('articles.byUser', $article->user) }}">{{ $article->user->name }}</a>
        <time datetime="{{ $article->created_at->toDateString() }}">{{ $article->created_at->format('d.m.Y') }}</time>
        <a class="article-arrow" href="{{ route('articles.show', $article) }}" aria-label="Leggi l'articolo">→</a>
    </footer>
</article>
