<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaLibraryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaLibraryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MediaLibraryItem::query()->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'like', "%{$search}%")
                  ->orWhere('original_filename', 'like', "%{$search}%");
            });
        }

        if ($tag = $request->input('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $items = $query->paginate(24);

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|image|max:10240|mimes:jpg,jpeg,png,gif,webp,svg',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store('media', 's3');

            if (!$path) {
                return response()->json(['error' => 'Upload failed — check S3/Minio configuration'], 500);
            }

            $item = MediaLibraryItem::create([
                'original_filename' => $file->getClientOriginalName(),
                'display_name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'tags' => [],
                'uploaded_by' => $request->user()?->id,
            ]);

            return response()->json($item, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function show(MediaLibraryItem $mediaLibrary): JsonResponse
    {
        return response()->json($mediaLibrary);
    }

    public function update(Request $request, MediaLibraryItem $mediaLibrary): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
        ]);

        $mediaLibrary->update($validated);

        return response()->json($mediaLibrary->fresh());
    }

    public function destroy(MediaLibraryItem $mediaLibrary): JsonResponse
    {
        try {
            Storage::disk('s3')->delete($mediaLibrary->path);
        } catch (\Exception $e) {
            // ignore delete errors
        }

        $mediaLibrary->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function tags(): JsonResponse
    {
        $tags = MediaLibraryItem::whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        return response()->json($tags);
    }
}
