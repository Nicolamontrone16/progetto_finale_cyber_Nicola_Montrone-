<div>
    <h3>Articles suggestions for you, get inspired!</h3>
    <form wire:submit="fetchNews">
        <label for="apiSelect">Breaking news aroud the world</label>
        <div class="d-flex">
            <select wire:model="selectedSource" id="apiSelect" class="form-select">
                <option value="">Choose country</option>
                <option value="it">NewsAPI - IT</option>
                <option value="gb">NewsAPI - UK</option>
                <option value="us">NewsAPI - US</option>
            </select>
            <button type="submit" class="btn btn-info">Go</button>
        </div>
        @error('selectedSource')
            <p class="text-danger">Fonte non consentita.</p>
        @enderror
    </form>
    <div>
        @if($errorMessage)
            <p class="text-danger">{{ $errorMessage }}</p>
        @elseif(isset($news['articles']))
            @forelse($news['articles'] as $article)
                <div class="news-article">
                    <h4>{{ $article['title'] }}</h4>
                    <p>{{ $article['description'] }}</p>
                    <a href="{{ $article['url'] }}" target="_blank">Read more</a>
                </div>
            @empty
            <h3>No articles around you</h3>
            @endforelse
        @endif
    </div>
</div>
