<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

class LogUserRegistered
{
    public function handle(Registered $event): void
    {
        $request = app()->bound('request') ? request() : null;

        Log::notice('User registered', [
            'event' => 'user_registered',
            'user_id' => $event->user->getAuthIdentifier(),
            'email' => $event->user->email,
            'name' => $event->user->name,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'result' => 'success',
        ]);
    }
}
