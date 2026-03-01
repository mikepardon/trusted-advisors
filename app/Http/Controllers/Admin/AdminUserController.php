<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\LoginLog;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('id')
            ->paginate(25)
            ->through(function ($user) {
                $lastLogin = LoginLog::where('user_id', $user->id)->latest('logged_in_at')->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'level' => $user->level,
                    'xp' => $user->xp,
                    'elo_rating' => $user->elo_rating,
                    'coins' => $user->coins,
                    'is_admin' => $user->is_admin,
                    'banned_at' => $user->banned_at?->toDateTimeString(),
                    'last_login_at' => $lastLogin?->logged_in_at?->toDateTimeString(),
                    'created_at' => $user->created_at->toDateTimeString(),
                ];
            });

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $gamesPlayed = Game::whereHas('players', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'completed')->count();

        $gamesWon = Game::whereHas('players', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('is_winner', true);
        })->where('status', 'completed')->count();

        $achievementCount = UserAchievement::where('user_id', $user->id)->count();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'level' => $user->level,
            'xp' => $user->xp,
            'elo_rating' => $user->elo_rating,
            'coins' => $user->coins,
            'is_admin' => $user->is_admin,
            'banned_at' => $user->banned_at?->toDateTimeString(),
            'login_streak' => $user->login_streak,
            'max_login_streak' => $user->max_login_streak,
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
            'created_at' => $user->created_at->toDateTimeString(),
            'games_played' => $gamesPlayed,
            'games_won' => $gamesWon,
            'achievement_count' => $achievementCount,
        ]);
    }

    public function ban(User $user): JsonResponse
    {
        if ($user->is_admin) {
            return response()->json(['error' => 'Cannot ban an admin user'], 422);
        }

        if ($user->banned_at) {
            $user->update(['banned_at' => null]);

            return response()->json(['message' => 'User unbanned', 'banned_at' => null]);
        }

        $user->update(['banned_at' => now()]);

        return response()->json(['message' => 'User banned', 'banned_at' => $user->fresh()->banned_at->toDateTimeString()]);
    }

    public function loginLogs(User $user): JsonResponse
    {
        $logs = LoginLog::where('user_id', $user->id)
            ->orderByDesc('logged_in_at')
            ->limit(50)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'logged_in_at' => $log->logged_in_at->toDateTimeString(),
                ];
            });

        return response()->json($logs);
    }
}
