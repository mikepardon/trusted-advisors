<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Local-only development convenience: when IS_LOGGED_IN=true and the app is
 * running in the local environment, auto-authenticate as user 1 so the OAuth
 * handshake can be skipped while developing. Double-gated (local env + flag)
 * so it can never fire in staging or production.
 */
class DevAutoLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            app()->environment('local')
            && config('auth.dev_auto_login') === true
            && ! Auth::check()
        ) {
            $user = User::find(1);
            if ($user !== null) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
