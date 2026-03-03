<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $announcements = Announcement::with('creator:id,name')
            ->withCount('dismissedByUsers as dismissal_count')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($announcements);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'link_url' => 'nullable|string|max:500',
            'link_label' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'sort_order' => 'integer',
        ]);

        $announcement = Announcement::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($announcement->load('creator:id,name'), 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'link_url' => 'nullable|string|max:500',
            'link_label' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'sort_order' => 'integer',
        ]);

        $announcement->update($validated);

        return response()->json($announcement->fresh()->load('creator:id,name'));
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(['success' => true]);
    }
}
