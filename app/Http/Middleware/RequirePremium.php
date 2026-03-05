<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePremium
{
    public function handle(Request $request, Closure $next): Response
    {
        // If no payment providers are configured, skip premium gating entirely
        $paymentsEnabled = !empty(config('services.stripe.key'))
            || !empty(config('services.apple.shared_secret'))
            || !empty(config('services.google_play.package_name'));

        if (!$paymentsEnabled) {
            return $next($request);
        }

        if (!$request->user() || !$request->user()->isPremium()) {
            return response()->json([
                'message' => 'Premium subscription required.',
                'premium_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
