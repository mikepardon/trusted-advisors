<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SoundAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SoundAssetController extends Controller
{
    public function index(): JsonResponse
    {
        $sounds = SoundAsset::orderBy('category')->orderBy('label')->get();

        $sounds->transform(function ($sound) {
            $sound->url = $sound->url;
            return $sound;
        });

        return response()->json($sounds);
    }

    public function publicIndex(): JsonResponse
    {
        $sounds = SoundAsset::whereNotNull('path')->get();
        $map = [];

        foreach ($sounds as $sound) {
            $url = $sound->url;
            if ($url) {
                $map[$sound->key] = $url;
            }
        }

        return response()->json($map);
    }

    public function upload(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:wav,mp3,ogg,webm|max:5120',
        ]);

        $sound = SoundAsset::where('key', $key)->firstOrFail();

        if ($sound->path) {
            try {
                Storage::disk('s3')->delete($sound->path);
            } catch (\Exception $e) {
                // ignore delete errors
            }
        }

        $path = $request->file('file')->store('sounds', 's3');
        $sound->update(['path' => $path]);

        return response()->json([
            'path' => $path,
            'url' => '/api/storage/' . $path,
        ]);
    }
}
