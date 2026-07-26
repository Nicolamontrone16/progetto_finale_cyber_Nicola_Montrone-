<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $request = app()->bound('request') ? request() : null;

        Log::info('User logged in', [
            'event' => 'user_login',
            'user_id' => $event->user->getAuthIdentifier(),
            'email' => $event->user->email,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'result' => 'success',
        ]);
    }
}
