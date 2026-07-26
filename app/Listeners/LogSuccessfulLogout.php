<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        $request = app()->bound('request') ? request() : null;

        Log::info('User logged out', [
            'event' => 'user_logout',
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->user?->email,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'result' => 'success',
        ]);
    }
}
