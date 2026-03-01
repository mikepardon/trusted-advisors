<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handleAuthWebhook(Request $request): JsonResponse
    {
        $event = $request->input('event');
        $data = $request->input('data');

        match ($event) {
            'user.deleted' => $this->handleUserDeleted($data),
            default => Log::info("Unhandled webhook event: {$event}"),
        };

        return response()->json(['status' => 'ok']);
    }

    private function handleUserDeleted(array $data): void
    {
        $authId = $data['user_id'] ?? null;

        if (!$authId) {
            Log::warning('user.deleted webhook missing user_id');
            return;
        }

        $user = User::where('auth_id', $authId)->first();

        if (!$user) {
            Log::info("user.deleted: no local user found for auth_id {$authId}");
            return;
        }

        Log::info("user.deleted: deleting local user {$user->id} (auth_id: {$authId})");

        $user->delete();
    }
}