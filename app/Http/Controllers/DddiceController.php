<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class DddiceController extends Controller
{
    public function guestToken(): JsonResponse
    {
        $cached = session('dddice_guest');
        if ($cached && $cached['expires_at'] > now()->timestamp) {
            return response()->json([
                'token' => $cached['token'],
            ]);
        }

        $apiKey = config('services.dddice.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'dddice not configured'], 503);
        }

        // Create guest user
        $userRes = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
        ])->post('https://dddice.com/api/1.0/user');

        if (!$userRes->successful()) {
            return response()->json(['error' => 'Failed to create dddice guest'], 502);
        }

        // Response format: {"type":"token","data":"<token_string>"}
        $guestToken = $userRes->json('data');
        if (!$guestToken || !is_string($guestToken)) {
            return response()->json(['error' => 'No token in dddice response'], 502);
        }

        // Cache in session for 30 minutes
        session(['dddice_guest' => [
            'token' => $guestToken,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]]);

        return response()->json([
            'token' => $guestToken,
        ]);
    }
}
