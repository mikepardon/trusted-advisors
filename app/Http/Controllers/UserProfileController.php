<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\GamePlayer;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function show(Request $request, User $user): JsonResponse
    {
        $currentUserId = $request->user()->id;

        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        $games = \App\Models\Game::where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->where('status', 'completed')
            ->get();

        $totalGames = $games->count();
        $totalWins = $games->where('win', true)->count();
        $totalLosses = $games->where('win', false)->count();
        $duelWins = $games->where('game_type', 'duel')->where('win', true)->count();

        $recentAchievements = UserAchievement::where('user_id', $user->id)
            ->with('achievement:id,name,description,icon')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($ua) => $ua->achievement);

        // Resolve active dice theme name
        $diceThemeName = null;
        if ($user->active_dice_theme_slug) {
            $diceTheme = \App\Models\DiceTheme::where('slug', $user->active_dice_theme_slug)->first();
            $diceThemeName = $diceTheme?->name;
        }

        // Resolve active kingdom style name
        $kingdomStyleName = null;
        if ($user->active_kingdom_style_slug) {
            $kingdomStyle = \App\Models\KingdomStyle::where('slug', $user->active_kingdom_style_slug)->first();
            $kingdomStyleName = $kingdomStyle?->name;
        }

        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'level' => $user->level,
            'xp' => $user->xp,
            'xp_for_next_level' => User::xpForLevel($user->level + 1),
            'elo_rating' => $user->elo_rating,
            'login_streak' => $user->login_streak,
            'total_games' => $totalGames,
            'total_wins' => $totalWins,
            'total_losses' => $totalLosses,
            'duel_wins' => $duelWins,
            'recent_achievements' => $recentAchievements,
            'active_dice_theme' => $diceThemeName,
            'active_kingdom_style' => $kingdomStyleName,
        ];

        // Check if friends
        $isFriend = Friendship::where('status', 'accepted')
            ->where(function ($q) use ($currentUserId, $user) {
                $q->where(function ($q2) use ($currentUserId, $user) {
                    $q2->where('sender_id', $currentUserId)->where('receiver_id', $user->id);
                })->orWhere(function ($q2) use ($currentUserId, $user) {
                    $q2->where('sender_id', $user->id)->where('receiver_id', $currentUserId);
                });
            })
            ->exists();

        if ($isFriend) {
            $myGameIds = GamePlayer::where('user_id', $currentUserId)->pluck('game_id');
            $profile['games_together'] = $participantGameIds->intersect($myGameIds)->count();
        }

        return response()->json($profile);
    }
}
