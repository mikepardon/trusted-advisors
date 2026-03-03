<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementDismissal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $announcements = Announcement::currentlyVisible()
            ->notDismissedBy($request->user()->id)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'description', 'link_url', 'link_label']);

        return response()->json($announcements);
    }

    public function dismiss(Request $request, Announcement $announcement): JsonResponse
    {
        AnnouncementDismissal::firstOrCreate([
            'user_id' => $request->user()->id,
            'announcement_id' => $announcement->id,
        ], [
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
