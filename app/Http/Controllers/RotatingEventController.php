<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\RotatingEvent;
use App\Models\RotatingEventEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RotatingEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $events = RotatingEvent::currentlyActive()
            ->orderBy('ends_at')
            ->get()
            ->filter(function ($event) use ($user) {
                if ($event->visibility === 'admin') {
                    return $user && $user->is_admin;
                }
                if ($event->visibility === 'premium') {
                    return $user && ($user->isPremium() || $user->is_admin);
                }
                return true; // 'all'
            })
            ->map(function ($event) use ($user) {
                $data = $event->toArray();
                $data['theme_color'] = $event->theme_color;
                $data['total_rounds'] = $event->total_rounds;
                $data['character_pool'] = $event->character_pool;
                $data['affects_elo'] = $event->affects_elo;
                $data['visibility'] = $event->visibility;
                $data['max_attempts'] = $event->max_attempts;
                if ($user && $event->max_attempts) {
                    $data['user_attempts'] = RotatingEventEntry::where('rotating_event_id', $event->id)
                        ->where('user_id', $user->id)
                        ->count();
                }
                return $data;
            })
            ->values();

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

        // Build enriched event data
        $eventData = $rotatingEvent->toArray();
        $eventData['card_count'] = $rotatingEvent->card_pool ? count($rotatingEvent->card_pool) : null;
        $eventData['item_count'] = $rotatingEvent->item_pool ? count($rotatingEvent->item_pool) : null;
        $eventData['event_count'] = $rotatingEvent->event_pool ? count($rotatingEvent->event_pool) : null;
        $eventData['character_count'] = $rotatingEvent->character_pool ? count($rotatingEvent->character_pool) : null;
        $eventData['curse_count'] = $rotatingEvent->curse_pool ? count($rotatingEvent->curse_pool) : null;
        $eventData['fixed_event_name'] = $rotatingEvent->fixed_event_id
            ? $rotatingEvent->fixedEvent?->name
            : null;
        $eventData['has_custom_xp'] = !empty($rotatingEvent->xp_config);
        $eventData['affects_elo'] = $rotatingEvent->affects_elo;
        $eventData['theme_color'] = $rotatingEvent->theme_color;
        $eventData['total_rounds'] = $rotatingEvent->total_rounds;
        $eventData['max_attempts'] = $rotatingEvent->max_attempts;
        if ($rotatingEvent->max_attempts) {
            $eventData['user_attempts'] = RotatingEventEntry::where('rotating_event_id', $rotatingEvent->id)
                ->where('user_id', $userId)
                ->count();
        }

        // Include allowed characters if character_pool is set
        if ($rotatingEvent->character_pool) {
            $eventData['characters'] = Character::whereIn('id', $rotatingEvent->character_pool)
                ->select('id', 'name', 'image_url')
                ->get();
        }

        return response()->json([
            'event' => $eventData,
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
