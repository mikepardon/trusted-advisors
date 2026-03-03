<?php

namespace App\Http\Controllers;

use App\Models\RotatingEvent;
use App\Models\RotatingEventEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RotatingEventController extends Controller
{
    public function index(): JsonResponse
    {
        $events = RotatingEvent::currentlyActive()
            ->orderBy('ends_at')
            ->get();

        return response()->json($events);
    }

    public function show(Request $request, RotatingEvent $rotatingEvent): JsonResponse
    {
        $userId = $request->user()->id;

        $userEntries = $rotatingEvent->entries()
            ->where('user_id', $userId)
            ->orderByDesc('score')
            ->limit(10)
            ->get();

        // Leaderboard: best score per user, top 20
        $leaderboard = RotatingEventEntry::where('rotating_event_id', $rotatingEvent->id)
            ->select('user_id', DB::raw('MAX(score) as best_score'), DB::raw('COUNT(*) as games_played'))
            ->groupBy('user_id')
            ->orderByDesc('best_score')
            ->limit(20)
            ->get()
            ->map(function ($entry, $index) {
                $user = \App\Models\User::find($entry->user_id);
                return [
                    'rank' => $index + 1,
                    'user_id' => $entry->user_id,
                    'username' => $user?->name ?? 'Unknown',
                    'best_score' => $entry->best_score,
                    'games_played' => $entry->games_played,
                ];
            });

        return response()->json([
            'event' => $rotatingEvent,
            'user_entries' => $userEntries,
            'leaderboard' => $leaderboard,
        ]);
    }

    public function leaderboard(Request $request, RotatingEvent $rotatingEvent): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $query = RotatingEventEntry::where('rotating_event_id', $rotatingEvent->id)
            ->select('user_id', DB::raw('MAX(score) as best_score'), DB::raw('COUNT(*) as games_played'))
            ->groupBy('user_id')
            ->orderByDesc('best_score');

        $total = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query->getQuery())
            ->count();

        $entries = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        $leaderboard = $entries->map(function ($entry, $index) use ($page, $perPage) {
            $user = \App\Models\User::find($entry->user_id);
            return [
                'rank' => ($page - 1) * $perPage + $index + 1,
                'user_id' => $entry->user_id,
                'username' => $user?->name ?? 'Unknown',
                'best_score' => $entry->best_score,
                'games_played' => $entry->games_played,
            ];
        });

        return response()->json([
            'data' => $leaderboard,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }
}
