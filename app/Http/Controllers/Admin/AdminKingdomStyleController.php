<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KingdomStyle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminKingdomStyleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(KingdomStyle::orderBy('name')->get());
    }

    public function update(Request $request, KingdomStyle $kingdomStyle): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'is_default_unlocked' => 'sometimes|boolean',
            'css_vars' => 'sometimes|array',
        ]);

        $kingdomStyle->update($validated);

        return response()->json($kingdomStyle->fresh());
    }

    public function removeImage(KingdomStyle $kingdomStyle): JsonResponse
    {
        if ($kingdomStyle->background_image_path) {
            try {
                Storage::disk('s3')->delete($kingdomStyle->background_image_path);
            } catch (\Exception $e) {
                // ignore delete errors
            }
        }

        $kingdomStyle->update(['background_image_path' => null]);

        return response()->json($kingdomStyle->fresh());
    }

    public function uploadImage(Request $request, KingdomStyle $kingdomStyle): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|max:5120',
        ]);

        if ($kingdomStyle->background_image_path) {
            try {
                Storage::disk('s3')->delete($kingdomStyle->background_image_path);
            } catch (\Exception $e) {
                // ignore delete errors
            }
        }

        try {
            $path = $request->file('image')->store('kingdom-styles', 's3');

            if (!$path) {
                return response()->json(['error' => 'Upload failed — check S3/Minio configuration'], 500);
            }

            $kingdomStyle->update(['background_image_path' => $path]);

            return response()->json($kingdomStyle->fresh());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }
}
