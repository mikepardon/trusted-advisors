<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (!$sigHeader) {
            return response('Missing signature.', 400);
        }

        try {
            $this->paymentService->handleStripeWebhook($payload, $sigHeader);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
            return response('Invalid signature.', 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook processing error', ['error' => $e->getMessage()]);
            return response('Webhook processing error.', 500);
        }

        return response('OK', 200);
    }
}
