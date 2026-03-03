<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        if ($request->is('api/*') || $request->expectsJson() || $request->wantsJson()) {
            return null;
        }

        return route('client.login');
    }
}
