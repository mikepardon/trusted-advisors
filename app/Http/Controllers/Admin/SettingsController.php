<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Return which admin-managed settings are configured, without ever exposing the
     * raw secret. The Anthropic key is masked to its last four characters.
     */
    public function index(): JsonResponse
    {
        $anthropicKey = (string) (GameRule::getValue('anthropic_api_key') ?? '');

        return response()->json([
            'anthropic' => [
                'set' => $anthropicKey !== '',
                'masked' => $this->mask($anthropicKey),
                'env_fallback' => filled(config('services.anthropic.api_key')),
            ],
        ]);
    }

    public function updateAnthropicKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        GameRule::updateOrCreate(
            ['key' => 'anthropic_api_key'],
            ['value' => trim($validated['api_key'])],
        );

        $key = trim($validated['api_key']);

        return response()->json([
            'message' => 'Anthropic API key saved.',
            'anthropic' => [
                'set' => true,
                'masked' => $this->mask($key),
                'env_fallback' => filled(config('services.anthropic.api_key')),
            ],
        ]);
    }

    public function clearAnthropicKey(): JsonResponse
    {
        GameRule::where('key', 'anthropic_api_key')->delete();

        return response()->json([
            'message' => 'Anthropic API key cleared.',
            'anthropic' => [
                'set' => false,
                'masked' => '',
                'env_fallback' => filled(config('services.anthropic.api_key')),
            ],
        ]);
    }

    private function mask(string $key): string
    {
        if ($key === '') {
            return '';
        }

        $length = mb_strlen($key);
        $visible = mb_substr($key, -4);

        return str_repeat('•', min($length - 4, 8)) . $visible;
    }
}
