<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.auth_webhook.secret');

        if (!$secret) {
            abort(500, 'Webhook secret not configured.');
        }

        $signature = $request->header('X-Webhook-Signature');

        if (!$signature) {
            abort(401, 'Missing webhook signature.');
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}