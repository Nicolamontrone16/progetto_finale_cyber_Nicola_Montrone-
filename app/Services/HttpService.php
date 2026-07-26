<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HttpService
{
    private const NEWS_ENDPOINT = 'https://newsapi.org/v2/top-headlines';

    private const FINANCIAL_ENDPOINT = 'http://internal.finance:8001/user-data.php';

    private const NEWS_SOURCES = [
        'it' => 'it',
        'gb' => 'gb',
        'us' => 'us',
    ];

    private const MAX_RESPONSE_BYTES = 1_000_000;

    public function __construct(private ?Closure $dnsResolver = null)
    {
    }

    public static function newsSourceKeys(): array
    {
        return array_keys(self::NEWS_SOURCES);
    }

    public function fetchLatestNews(string $sourceKey): array
    {
        if (! array_key_exists($sourceKey, self::NEWS_SOURCES)) {
            throw new RuntimeException('News source is not allowed.');
        }

        $apiKey = config('services.newsapi.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('News service is not configured.');
        }

        $addresses = $this->assertSafeExternalEndpoint(self::NEWS_ENDPOINT);
        $pinnedAddress = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];

        try {
            $response = Http::acceptJson()
                ->withHeaders(['Referer' => config('app.url')])
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => ['newsapi.org:443:'.$pinnedAddress]],
                ])
                ->connectTimeout(3)
                ->timeout(5)
                ->get(self::NEWS_ENDPOINT, [
                    'country' => self::NEWS_SOURCES[$sourceKey],
                    'apiKey' => $apiKey,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('News service is unavailable.', previous: $exception);
        }

        return $this->validatedJson($response);
    }

    public function fetchFinancialDataForAdmin(User $user): array
    {
        if (! Auth::check() || Auth::id() !== $user->id || ! $user->is_admin) {
            Log::warning('Unauthorized Financial App access attempt', [
                'event' => 'unauthorized_financial_access',
                'actor_user_id' => Auth::id(),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'result' => 'denied',
            ]);

            abort(403);
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['Referer' => config('app.url')])
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(3)
                ->timeout(5)
                ->get(self::FINANCIAL_ENDPOINT);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Financial service is unavailable.', previous: $exception);
        }

        return $this->validatedJson($response);
    }

    private function assertSafeExternalEndpoint(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false
            || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'newsapi.org'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || ($parts['path'] ?? null) !== '/v2/top-headlines') {
            throw new RuntimeException('Unsafe news endpoint configuration.');
        }

        $addresses = $this->resolveHost($parts['host']);

        if ($addresses === []) {
            throw new RuntimeException('News host could not be resolved safely.');
        }

        foreach ($addresses as $address) {
            if (filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false) {
                throw new RuntimeException('News host resolved to a forbidden address.');
            }
        }

        return $addresses;
    }

    private function resolveHost(string $host): array
    {
        if ($this->dnsResolver !== null) {
            return array_values(array_unique(($this->dnsResolver)($host)));
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            }

            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }

    private function validatedJson(Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('Remote service request failed.');
        }

        $contentType = strtolower($response->header('Content-Type'));

        if (! str_starts_with($contentType, 'application/json')) {
            throw new RuntimeException('Remote service returned an unexpected content type.');
        }

        if (strlen($response->body()) > self::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('Remote service response is too large.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Remote service returned invalid JSON.');
        }

        return $data;
    }
}
