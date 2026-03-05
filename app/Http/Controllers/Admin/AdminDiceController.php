<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiceTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AdminDiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(DiceTheme::orderBy('name')->get());
    }

    public function update(Request $request, DiceTheme $diceTheme): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'is_default_unlocked' => 'boolean',
        ]);

        $diceTheme->update($validated);

        return response()->json($diceTheme);
    }

    public function sync(): JsonResponse
    {
        $apiKey = config('services.dddice.api_key');

        if (!$apiKey) {
            return response()->json(['error' => 'DDDICE_API_KEY not configured'], 500);
        }

        $page = 1;
        $synced = 0;

        do {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ])->get('https://dddice.com/api/1.0/theme', [
                'page' => $page,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'dddice API error: ' . $response->status(),
                    'body' => $response->json(),
                ], 502);
            }

            $json = $response->json();
            $themes = $json['data'] ?? [];

            foreach ($themes as $theme) {
                $slug = $theme['id'] ?? null;
                if (!$slug) continue;

                // Extract preview URL — API returns preview.d6 / preview.preview
                $previewUrl = $this->extractPreviewUrl($theme);

                // Download preview image and upload to S3
                $s3Url = null;
                if ($previewUrl) {
                    $s3Url = $this->uploadPreviewToS3($slug, $previewUrl);
                }

                DiceTheme::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $theme['name'] ?? $slug,
                        'preview_image' => $s3Url ?? $previewUrl,
                        'data' => $theme,
                    ]
                );
                $synced++;
            }

            $lastPage = $json['last_page'] ?? $json['meta']['last_page'] ?? $page;
            $page++;
        } while ($page <= $lastPage);

        return response()->json([
            'message' => "Synced {$synced} themes",
            'themes' => DiceTheme::orderBy('name')->get(),
        ]);
    }

    private function extractPreviewUrl(array $theme): ?string
    {
        $preview = $theme['preview'] ?? null;

        if (is_string($preview)) {
            return $preview;
        }

        if (is_array($preview)) {
            // Prefer d6 preview (our game uses d6), then generic preview, then first available
            return $preview['d6'] ?? $preview['preview'] ?? (reset($preview) ?: null);
        }

        // Fallback: check preview_image key
        $alt = $theme['preview_image'] ?? null;
        if (is_string($alt)) {
            return $alt;
        }

        return null;
    }

    private function uploadPreviewToS3(string $slug, string $url): ?string
    {
        try {
            $imageResponse = Http::timeout(10)->get($url);
            if (!$imageResponse->successful()) {
                return null;
            }

            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
            $s3Path = "dice-previews/{$slug}.{$extension}";

            Storage::disk('s3')->put($s3Path, $imageResponse->body(), 'public');

            return Storage::disk('s3')->url($s3Path);
        } catch (\Exception $e) {
            // S3 upload failed — return null so we fall back to the original URL
            return null;
        }
    }
}
