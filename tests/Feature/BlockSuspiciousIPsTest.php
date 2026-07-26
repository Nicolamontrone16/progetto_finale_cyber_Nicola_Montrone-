<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockSuspiciousIPs;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class BlockSuspiciousIPsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        Carbon::setTestNow('2026-01-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();

        parent::tearDown();
    }

    public function test_first_five_requests_from_the_same_ip_are_allowed(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->sendThroughMiddleware('192.0.2.10');

            $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        }
    }

    public function test_sixth_request_from_the_same_ip_is_rejected(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->sendThroughMiddleware('192.0.2.10');
        }

        $response = $this->sendThroughMiddleware('192.0.2.10');

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertStringContainsString('temporaneamente bloccato', $response->getContent());
    }

    public function test_a_second_ip_is_not_blocked_by_the_first_ip_limit(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->sendThroughMiddleware('192.0.2.10');
        }

        $response = $this->sendThroughMiddleware('198.51.100.20');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_blocked_ip_can_make_requests_after_the_block_expires(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->sendThroughMiddleware('192.0.2.10');
        }

        Carbon::setTestNow(now()->addMinutes(1)->addSecond());

        $response = $this->sendThroughMiddleware('192.0.2.10');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_search_route_uses_the_suspicious_ip_middleware(): void
    {
        $route = Route::getRoutes()->getByName('articles.search');

        $this->assertNotNull($route);
        $this->assertSame('articles/search', $route->uri());
        $this->assertContains('block.suspicious', $route->gatherMiddleware());
    }

    private function sendThroughMiddleware(string $ip): Response
    {
        $request = Request::create('/articles/search?query=test', 'GET', server: [
            'REMOTE_ADDR' => $ip,
        ]);

        return app(BlockSuspiciousIPs::class)->handle(
            $request,
            fn () => response('OK')
        );
    }
}
