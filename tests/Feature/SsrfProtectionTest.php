<?php

namespace Tests\Feature;

use App\Livewire\LatestNews;
use App\Models\User;
use App\Services\HttpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('services.newsapi.api_key', 'test-news-key');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            HttpService::class,
            new HttpService(fn (string $host) => ['93.184.216.34'])
        );
    }

    public function test_valid_source_key_fetches_news_from_fixed_newsapi_endpoint(): void
    {
        Http::fake([
            'https://newsapi.org/*' => Http::response([
                'status' => 'ok',
                'articles' => [['title' => 'Security news', 'description' => 'Safe result', 'url' => 'https://example.test/news']],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $writer = User::factory()->create(['is_writer' => true]);

        Livewire::actingAs($writer)
            ->test(LatestNews::class)
            ->set('selectedSource', 'it')
            ->call('fetchNews')
            ->assertHasNoErrors()
            ->assertSet('news.status', 'ok')
            ->assertSet('errorMessage', null);

        Http::assertSent(function (Request $request) {
            $parts = parse_url($request->url());
            parse_str($parts['query'] ?? '', $query);

            return $parts['scheme'] === 'https'
                && $parts['host'] === 'newsapi.org'
                && ($parts['path'] ?? '') === '/v2/top-headlines'
                && ($query['country'] ?? null) === 'it'
                && ($query['apiKey'] ?? null) === config('services.newsapi.api_key');
        });
    }

    public function test_manipulated_sources_are_rejected_logged_and_never_requested(): void
    {
        Http::fake();
        Log::spy();
        $writer = User::factory()->create(['is_writer' => true]);
        $blockedSources = [
            'http://internal.finance:8001/user-data.php',
            'http://127.0.0.1:8001/user-data.php',
            'http://localhost/user-data.php',
            'file:///etc/passwd',
            'ftp://newsapi.org/file',
            'gopher://127.0.0.1:70/_payload',
            'https://example.com/news',
            'https://newsapi.org:8443/v2/top-headlines',
        ];

        foreach ($blockedSources as $source) {
            Livewire::actingAs($writer)
                ->test(LatestNews::class)
                ->set('selectedSource', $source)
                ->call('fetchNews')
                ->assertHasErrors('selectedSource')
                ->assertSet('news', []);
        }

        Http::assertNothingSent();

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $message === 'Potential SSRF attempt blocked'
                && $context['event'] === 'ssrf_attempt_blocked'
                && $context['actor_user_id'] === $writer->id
                && $context['result'] === 'blocked'
                && ! str_contains($context['selected_source_preview'], '?')
        )->times(count($blockedSources));
    }

    public function test_redirect_to_internal_address_is_not_followed(): void
    {
        Http::fake([
            'https://newsapi.org/*' => Http::response('', 302, [
                'Location' => 'http://internal.finance:8001/user-data.php',
                'Content-Type' => 'application/json',
            ]),
        ]);

        try {
            app(HttpService::class)->fetchLatestNews('it');
            $this->fail('An internal redirect was accepted.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }

        Http::assertSentCount(1);
    }

    public function test_news_host_resolving_to_private_address_is_rejected_before_request(): void
    {
        Http::fake();
        $service = new HttpService(fn (string $host) => ['127.0.0.1', '10.0.0.10']);

        try {
            $service->fetchLatestNews('it');
            $this->fail('A private DNS result was accepted.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }

        Http::assertNothingSent();
    }

    public function test_writer_cannot_request_financial_data(): void
    {
        Http::fake();
        Log::spy();
        $writer = User::factory()->create(['is_writer' => true, 'is_admin' => false]);
        $this->actingAs($writer);

        try {
            app(HttpService::class)->fetchFinancialDataForAdmin($writer);
            $this->fail('A writer was allowed to request Financial App data.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $context['event'] === 'unauthorized_financial_access'
                && $context['actor_user_id'] === $writer->id
                && $context['result'] === 'denied'
        )->once();
    }

    public function test_admin_dashboard_can_fetch_financial_data_from_fixed_internal_endpoint(): void
    {
        Http::fake([
            'http://internal.finance:8001/user-data.php' => Http::response(
                ['users' => []],
                200,
                ['Content-Type' => 'application/json']
            ),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('http://internal.admin:8000/admin/dashboard')
            ->assertOk()
            ->assertViewHas('financialData', ['users' => []]);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://internal.finance:8001/user-data.php');
    }

    public function test_latest_news_never_exposes_financial_data_for_invalid_source(): void
    {
        Http::fake();
        $writer = User::factory()->create(['is_writer' => true]);

        Livewire::actingAs($writer)
            ->test(LatestNews::class)
            ->set('selectedSource', 'http://internal.finance:8001/user-data.php')
            ->call('fetchNews')
            ->assertHasErrors('selectedSource')
            ->assertSet('news', []);

        Http::assertNothingSent();
        $this->assertFalse(method_exists(HttpService::class, 'getRequest'));
    }
}
