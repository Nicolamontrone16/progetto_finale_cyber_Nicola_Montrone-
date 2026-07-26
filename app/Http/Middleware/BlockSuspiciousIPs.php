<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BlockSuspiciousIPs
{
    protected int $maxAttempts = 5;

    protected int $decayMinutes = 1;

    protected int $blockMinutes = 1;

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $clientHash = sha1($ip);
        $attemptsKey = 'throttle:'.$clientHash;
        $blockedKey = 'blocked:'.$clientHash;

        if (Cache::has($blockedKey)) {
            return $this->tooManyRequestsResponse();
        }

        $attempts = Cache::add(
            $attemptsKey,
            1,
            now()->addMinutes($this->decayMinutes)
        ) ? 1 : Cache::increment($attemptsKey);

        if ($attempts > $this->maxAttempts) {
            Cache::put(
                $blockedKey,
                true,
                now()->addMinutes($this->blockMinutes)
            );
            Cache::forget($attemptsKey);

            Log::warning('Indirizzo IP temporaneamente bloccato per troppe richieste.', [
                'event' => 'ip_temporarily_blocked',
                'ip_address' => $ip,
                'route' => $request->route()?->getName() ?? $request->path(),
                'method' => $request->method(),
                'reason' => 'rate_limit_exceeded',
                'block_duration_minutes' => $this->blockMinutes,
                'attempts' => $attempts,
                'result' => 'blocked',
            ]);

            return $this->tooManyRequestsResponse();
        }

        return $next($request);
    }

    protected function tooManyRequestsResponse(): Response
    {
        return response()->json([
            'message' => 'Troppe richieste. Indirizzo IP temporaneamente bloccato. Riprova tra un minuto.',
        ], Response::HTTP_TOO_MANY_REQUESTS);
    }
}
