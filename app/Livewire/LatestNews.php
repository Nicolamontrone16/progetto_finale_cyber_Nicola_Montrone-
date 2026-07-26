<?php

namespace App\Livewire;

use App\Services\HttpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

class LatestNews extends Component
{
    public $selectedSource = '';

    public array $news = [];

    public ?string $errorMessage = null;

    protected function rules(): array
    {
        return [
            'selectedSource' => ['required', 'string', Rule::in(HttpService::newsSourceKeys())],
        ];
    }

    public function fetchNews(HttpService $httpService): void
    {
        if (! Auth::check() || ! Auth::user()->is_writer) {
            Log::warning('Unauthorized news retrieval attempt', [
                'event' => 'unauthorized_news_access',
                'actor_user_id' => Auth::id(),
                'ip_address' => request()->ip(),
                'route' => request()->route()?->getName() ?? request()->path(),
                'result' => 'denied',
            ]);

            abort(403);
        }

        if (! in_array($this->selectedSource, HttpService::newsSourceKeys(), true)) {
            $sourceWithoutQuery = Str::before((string) $this->selectedSource, '?');

            Log::warning('Potential SSRF attempt blocked', [
                'event' => 'ssrf_attempt_blocked',
                'actor_user_id' => Auth::id(),
                'selected_source_preview' => Str::limit($sourceWithoutQuery, 100, ''),
                'selected_source_fingerprint' => sha1((string) $this->selectedSource),
                'ip_address' => request()->ip(),
                'route' => request()->route()?->getName() ?? request()->path(),
                'result' => 'blocked',
            ]);
        }

        $this->validate();
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->news = [];

        try {
            $this->news = $httpService->fetchLatestNews($this->selectedSource);
        } catch (Throwable) {
            $this->errorMessage = 'Impossibile recuperare le notizie in questo momento.';
        }
    }

    public function render()
    {
        return view('livewire.latest-news');
    }
}
