<section class="news-panel" aria-labelledby="news-title">
    <div class="news-panel-heading"><div><p class="eyebrow">Fonte verificata</p><h2 id="news-title">Suggerimenti dalle ultime notizie</h2><p>Lasciati ispirare da fonti selezionate. Il server accetta soltanto identificatori autorizzati.</p></div></div>
    <form wire:submit="fetchNews" class="news-search">
        <div class="form-group"><label for="apiSelect">Edizione NewsAPI</label><div class="d-flex gap-2"><select wire:model="selectedSource" id="apiSelect" class="form-select"><option value="">Scegli un paese</option><option value="it">Italia</option><option value="gb">Regno Unito</option><option value="us">Stati Uniti</option></select><button type="submit" class="btn btn-secondary" wire:loading.attr="disabled"><span wire:loading.remove>Cerca</span><span wire:loading>Caricamento…</span></button></div>@error('selectedSource')<span class="field-error">Fonte non consentita.</span>@enderror</div>
    </form>
    <div wire:loading class="skeleton-list" aria-label="Caricamento notizie"><span></span><span></span><span></span></div>
    <div wire:loading.remove>
        @if($errorMessage)<div class="alert alert-warning" role="alert">{{ $errorMessage }}</div>
        @elseif(isset($news['articles']))<div class="news-list">@forelse($news['articles'] as $article)<article class="news-item"><div><small>NewsAPI</small><h3>{{ $article['title'] }}</h3><p>{{ $article['description'] }}</p></div><a href="{{ $article['url'] }}" target="_blank" rel="noopener noreferrer">Apri la fonte <span aria-hidden="true">↗</span></a></article>@empty<x-empty-state title="Nessuna notizia" message="Non sono disponibili suggerimenti per questa fonte." />@endforelse</div>@endif
    </div>
</section>
